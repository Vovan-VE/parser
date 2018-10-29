<?php
namespace VovanVE\parser\grammar\loaders;

use VovanVE\parser\actions\AbortNodeException;
use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\errors\AbortedException;
use VovanVE\parser\errors\UnexpectedInputAfterEndException;
use VovanVE\parser\errors\UnexpectedTokenException;
use VovanVE\parser\errors\UnknownCharacterException;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\Parser;

/**
 * Class TextLoader
 *
 * > **Notice:** since 2.0.0 the text grammar is designed for dev purpose.
 * > You need to convert it to array or JSON grammar for production purpose.
 * > The reason is performance. Text grammar loader uses the whole parse itself
 * > (1 level depth recursion). So, when you use array or JSON grammar, you
 * > skip that recursion completely.
 * >
 * > Use CLI tools from the package to convert your text grammar to array of
 * > JSON grammar.
 *
 * Grammar object can be easily created with plain text like so:
 *
 * ```php
 * $grammar = Grammar::create(<<<'_END'
 *     Goal        : Sum $
 *     Sum(add)    : Sum "+" Product
 *     Sum(sub)    : Sum "-" Product
 *     Sum(P)      : Product
 *     Product(mul): Product "*" Value
 *     Product(div): Product "/" Value
 *     Product(V)  : Value
 *     Value(neg)  : "-" Value
 *     Value       : "+" Value
 *     Value       : "(" Sum ")"
 *     Value       : int
 *     int         : /\d+/
 * _END
 * );
 * ```
 *
 * Rules in grammar text can be separated either by new-line characters or semicolon `;`.
 *
 * Here is an example rule:
 *
 * ```
 * Subject ( tag ) : foo "+" .bar
 * Subject         : "+"
 * Subject         : /regexp/
 * ```
 *
 * Explanation:
 *
 * *   `Subject` - subject symbol of a rule
 * *   `( tag )` - optional tag to reference rule(s) from actions
 * *   `foo "+" .bar` - definition body - space separated list of tokens
 *     *   `foo` - normal symbol
 *     *   `"+"` - inline defined token. Can be `"..."`, `'...'` or `<...>`. There is no escaping
 *         or something similar. So, you cannot use `"` inside `"..."`, `'` inside `'...'`
 *         and `<` or `>` inside `<...>`.
 *     *   `.bar` - hidden symbol
 * *   `/regexp/` - RegExp definition for terminal. Usage of NOWDOC for grammar text
 *         lets you to avoid double escaping. RegExp definition introduced since 1.5.0.
 * *   `$` - EOF marker for main rule.
 *
 * Here is some terms:
 *
 * *   Grammar must to contain exactly one main rule which has EOF marker `$` in the end.
 *     Subject symbol name for the main rule currently does not matter, but must be present for
 *     consistency and must be unique. Say it can be for example `Goal` or `TOP`.
 *     Tag `(tag)` for the main rule also has no usage.
 * *   Order of rules in a grammar does not matter, but regexp definitions order may
 *     have difference in some cases.
 * *   All symbols which are subjects of rules will become non-terminal.
 * *   Named symbols are case sensitive. It is recommended, but not required, to use
 *     lower case names (`foo`) for terminals and upper CamelCase (`FooBar`) for non-terminals.
 * *   Inline quoted terminals are always hidden. It will not produce a nodes in the resulting tree.
 * *   Named symbols can be marked as hidden too. Add a dot `.` in front of name: `.foo`.
 *     This can be done locally in a rule (both for terminals and non-terminals) or "globally"
 *     in Lexer (for terminals only).
 * *   Rule with the only inline token and without tag like `name: "text"` can be removed
 *     internally to define fixed terminal in Lexer instead when the inline has no other
 *     references and subject symbol has no other rules. This feature is introduced since 1.5.0.
 * *   RegExp definition must be the only item in a rule: `name: /regexp/`
 * *   RegExp subject symbol must not have any definitions in another rules.
 *
 * @package VovanVE\parser
 * @since 1.7.0
 * @see ./grammar-text.txt The grammar of text grammar
 */
class TextLoader extends BaseObject
{
    /**
     * Create grammar object from a text
     *
     * See class description for details.
     *
     * > **Notice:** Since 2.0.0 each call to this method will create Parse+Grammar internally
     * > to parse your text grammar. No optimisation is here since text grammar is
     * > for dev purpose only. So, if you are going to load a set of text grammars,
     * > you are recommended to use an instance object of this class instead,
     * > so the only one nested Parser+Grammar will be created.
     * @param string $text Grammar definition text
     * @return Grammar Grammar object
     * @throws GrammarException Errors in grammar syntax or logic
     */
    public static function createGrammar(string $text): Grammar
    {
        return (new self)->loadGrammar($text);
    }

    private const OPT_TYPE_STRING = 'string';
    private const OPT_TYPE_REGEXP = 'regexp';

    /** @var Parser A parser to parse text grammar */
    private $parser;
    /** @var ActionsMadeMap Actions to load text grammar into rules */
    private $actions;
    /** @var Symbol[][] Symbols registry filling while parsing a grammar */
    private $symbols;
    /** @var string[] Map of inline tokens filling while parsing a grammar */
    private $inline;
    /** @var string[] RegExp tokens map filling while parsing a grammar */
    private $regexpMap;
    /** @var string[] RegExp map for DEFINEs */
    private $defines;
    /** @var string[] RegExp list for whitespaces */
    private $whitespaces;
    /** @var string */
    private $modifiers;

    /**
     * TextLoader constructor.
     * @since 2.0.0
     */
    public function __construct()
    {
        // Hey dude, I heard you like Parser. I add a parser into grammar loader,
        // so you will load grammar while you will load grammar.

        $this->parser = new Parser(ArrayLoader::createGrammar(
            require __DIR__ . '/grammar-text.php'
        ));

        $this->actions = new ActionsMadeMap([
            'Definitions(first)' => function (?Rule $rule, array $list): array {
                if (!$rule) {
                    return $list;
                }
                $new = $list;
                array_unshift($new, $rule);
                return $new;
            },
            'Definitions(only)' => $init_list_not_null = function (?Rule $rule): array {
                return $rule ? [$rule] : [];
            },
            'Definitions' => Parser::ACTION_BUBBLE_THE_ONLY,

            'DefinitionsContinue(list)' => function (array $list, ?Rule $rule): array {
                if (!$rule) {
                    return $list;
                }
                $new = $list;
                $new[] = $rule;
                return $new;
            },
            'DefinitionsContinue(first)' => $init_list_not_null,

            'NextDefinition' => Parser::ACTION_BUBBLE_THE_ONLY,
            'NextDefinition(empty)' => function (): ?Rule {
                return null;
            },

            'Definition' => Parser::ACTION_BUBBLE_THE_ONLY,

            'Define' => function (string $name, string $regexp) {
                if (isset($this->defines[$name])) {
                    throw new AbortNodeException(
                        'Conflict',
                        1,
                        new GrammarException("Duplicating DEFINE `$name`")
                    );
                }
                if (isset($this->symbols[$name])) {
                    throw new AbortNodeException(
                        'Conflict',
                        1,
                        new GrammarException("DEFINE `$name` overlaps with a symbol")
                    );
                }

                $this->defines[$name] = $regexp;
                return null;
            },

            'Option' => function (string $name, array $data) {
                /** @var string $type */
                /** @var string $value */
                [$type, $value] = $data;

                try {
                    $this->addOption($name, $type, $value);
                } catch (GrammarException $e) {
                    throw new AbortNodeException('Invalid option', 1, $e);
                }

                return null;
            },
            'OptionValue(str)' => function (string $string): array {
                return [self::OPT_TYPE_STRING, $string];
            },
            'OptionValue(re)' => function (string $regexp): array {
                return [self::OPT_TYPE_REGEXP, $regexp];
            },

            'Rule' => function (array $subject, $definition): ?Rule {
                /** @var string $name */
                /** @var bool $is_hidden */
                /** @var string|null $tag */
                [[$name, $is_hidden], $tag] = $subject;

                if ($definition instanceof \stdClass) {
                    if (isset($definition->regexp)) {
                        if (null !== $tag) {
                            throw new AbortNodeException(
                                'Conflict',
                                1,
                                new GrammarException("Rule tag cannot be used in RegExp rules")
                            );
                        }

                        if (isset($this->regexpMap[$name])) {
                            throw new AbortNodeException(
                                'Conflict',
                                1,
                                new GrammarException("Duplicate RegExp rule for symbol `$name`")
                            );
                        }

                        $this->wantSymbol($name, $is_hidden);
                        if ($is_hidden && isset($this->symbols[$name][false])) {
                            $this->symbols[$name][false]->setIsHidden(true);
                        }

                        $this->regexpMap[$name] = $definition->regexp;
                        return null;
                    }

                    throw new AbortNodeException(
                        'Conflict',
                        1,
                        new InternalException('stdClass with unknown fields')
                    );
                }

                /** @var Symbol[] $symbols */
                /** @var bool $eof */
                [$symbols, $eof] = $definition;

                $subject_symbol = $this->wantSymbol($name, $is_hidden);
                $subject_symbol->setIsTerminal(false);

                // sync hidden/visible counterpart non-terminal symbols
                if (isset($this->symbols[$name][!$is_hidden])) {
                    $this->symbols[$name][!$is_hidden]->setIsTerminal(false);
                }

                return new Rule($subject_symbol, $symbols, $eof, $tag);
            },

            'RuleSubjectTagged(tag)' => function (array $subject, string $tag): array {
                return [$subject, $tag];
            },
            'RuleSubjectTagged' => function (array $subject): array {
                return [$subject, null];
            },

            'RuleDefinition(regexp)' => function (string $regexp) {
                return (object)['regexp' => $regexp];
            },
            'RuleDefinition(main)' => function (array $symbols): array {
                return [$symbols, true];
            },
            'RuleDefinition' => function (array $symbols): array {
                return [$symbols, false];
            },

            'Symbols(list)' => function (array $list, $item): array {
                $new = $list;
                $new[] = $item;
                return $new;
            },
            'Symbols(first)' => function ($item): array {
                return [$item];
            },

            'Symbol(name)' => function (array $data): Symbol {
                /** @var string $name */
                /** @var bool $is_hidden */
                [$name, $is_hidden] = $data;
                try {
                    return $this->wantSymbol($name, $is_hidden);
                } catch (GrammarException $e) {
                    throw new AbortNodeException('Symbol conflict', 1, $e);
                }
            },
            'Symbol(string)' => function (string $string): Symbol {
                try {
                    return $this->wantSymbol($string, true, true);
                } catch (GrammarException $e) {
                    throw new AbortNodeException('Symbol conflict', 1, $e);
                }
            },

            'SymbolNamed(hidden)' => function (string $name): array {
                return [$name, true];
            },
            'SymbolNamed(normal)' => function (string $name): array {
                return [$name, false];
            },

            'String' => Parser::ACTION_BUBBLE_THE_ONLY,

            'qstring' => $unquote = function (string $content): string {
                return substr($content, 1, -1);
            },
            'qqstring' => $unquote,
            'angle_string' => $unquote,
            'regexp' => $unquote,
            'name' => 'strval'
        ]);
    }

    /**
     * Create a Grammar object from text grammar source
     * @param string $text Grammar text
     * @return Grammar
     * @since 2.0.0
     * @throws GrammarException
     */
    public function loadGrammar(string $text): Grammar
    {
        $this->symbols = [];
        $this->inline = [];
        $this->regexpMap = [];
        $this->defines = [];
        $this->whitespaces = [];
        $this->modifiers = null;

        try {
            /** @var Rule[] $rules */
            $rules = $this->parser->parse($text, $this->actions)->made();

            /** @var int[] $subject_rules_count */
            $subject_rules_count = [];
            /** @var int[] $inline_ref_count */
            $inline_ref_count = [];
            /** @var Rule[] $inline_ref_rule */
            $inline_ref_rule = [];

            $non_terminal_is_hidden = [];
            $hidden_non_terminals = [];

            foreach ($rules as $rule) {
                $subject = $rule->getSubject();
                $subject_name = $subject->getName();
                $subject_hidden = $subject->isHidden();

                // RegExp tokens cannot be non-terminals in the same time
                // since there is no anonymous RegExp terminals
                if (isset($this->regexpMap[$subject_name])) {
                    throw new GrammarException(
                        "Symbol `$subject_name` defined as non-terminal and as regexp terminal in the same time"
                    );
                }

                if (isset($non_terminal_is_hidden[$subject_name])) {
                    if ($subject_hidden !== $non_terminal_is_hidden[$subject_name]) {
                        throw new GrammarException(
                            "Symbol `$subject_name` defined both as hidden and as visible"
                        );
                    }
                } else {
                    $non_terminal_is_hidden[$subject_name] = $subject_hidden;
                }

                $subject_rules_count[$subject_name] = ($subject_rules_count[$subject_name] ?? 0) + 1;

                if ($subject_hidden) {
                    $hidden_non_terminals[$subject_name] = true;
                }

                foreach ($rule->getDefinition() as $symbol) {
                    $name = $symbol->getName();
                    if (isset($this->inline[$name])) {
                        $inline_ref_count[$name] = ($inline_ref_count[$name] ?? 0) + 1;

                        // it is needed in case of the only reference,
                        // so array of an subject is not needed
                        $inline_ref_rule[$name] = $rule;
                    }
                }
            }

            foreach ($hidden_non_terminals as $name => $_) {
                if (isset($this->symbols[$name][false])) {
                    $this->symbols[$name][false]->setIsHidden(true);
                }
            }

            // Convert non-terminals defined only once with inline tokens
            // `Subject: "inline"`
            // into fixed terminals
            $fixed = [];
            foreach ($inline_ref_count as $inline_token => $ref_count) {
                if (1 !== $ref_count) {
                    continue;
                }

                $rule = $inline_ref_rule[$inline_token];
                if (null === $rule->getTag() && 1 === count($rule->getDefinition())) {
                    $subject = $rule->getSubject();
                    $name = $subject->getName();

                    if (1 !== $subject_rules_count[$name]) {
                        continue;
                    }

                    // remove rule
                    $key = array_search($rule, $rules, true);
                    if (false !== $key) {
                        unset($rules[$key]);
                    }

                    // remove inline
                    unset($this->inline[$inline_token]);

                    // make terminal
                    $subject->setIsTerminal(true);
                    if (isset($this->symbols[$name][!$subject->isHidden()])) {
                        $this->symbols[$name][!$subject->isHidden()]->setIsTerminal(true);
                    }

                    $fixed[$name] = $inline_token;
                }
            }

            return new Grammar(
                $rules,
                $this->inline,
                $fixed,
                $this->regexpMap,
                $this->whitespaces,
                $this->defines,
                $this->modifiers ?? ''
            );
        } catch (UnknownCharacterException | UnexpectedInputAfterEndException | UnexpectedTokenException $e) {
            throw new GrammarException(
                'Cannot parse grammar: ' . $e->getMessage() . ' at offset ' . $e->getOffset(),
                0,
                $e
            );
        } catch (AbortedException $e) {
            for ($inner = $e; $inner; $inner = $inner->getPrevious()) {
                if ($inner instanceof GrammarException || $inner instanceof InternalException) {
                    throw $inner;
                }
            }
            throw new InternalException('Failure while parsing your grammar text', 0, $e);
        } finally {
            $this->symbols = null;
            $this->inline = null;
            $this->regexpMap = null;
            $this->defines = null;
            $this->whitespaces = null;
            $this->modifiers = null;
        }
    }

    /**
     * Register a Symbol
     * @param string $name Symbol name
     * @param bool $isHidden Whether the hidden symbol is needed
     * @param bool $isInline Whether the symbol is inline string
     * @return Symbol
     * @throws GrammarException
     */
    private function wantSymbol(string $name, bool $isHidden = false, bool $isInline = false): Symbol
    {
        if ($isInline) {
            if (!isset($this->inline[$name]) && isset($this->symbols[$name])) {
                throw new GrammarException(
                    "Inline '$name' conflicts with token <$name> defined previously"
                );
            }
            if (isset($this->defines[$name])) {
                throw new GrammarException("Inline '$name' conflicts with DEFINE");
            }

            $this->inline[$name] = $name;
            $isHidden = true;
        } else {
            if (isset($this->inline[$name])) {
                throw new GrammarException(
                    "Token <$name> conflicts with inline '$name' defined previously"
                );
            }
            if (isset($this->defines[$name])) {
                throw new GrammarException("Token <$name> conflicts with DEFINE");
            }
        }

        return $this->symbols[$name][$isHidden]
            ?? ($this->symbols[$name][$isHidden] = new Symbol($name, true, $isHidden));
    }

    /**
     * @param string $name
     * @param string $type
     * @param string $value
     * @throws GrammarException
     */
    private function addOption(string $name, string $type, string $value): void
    {
        switch ($name) {
            case 'ws':
                if (self::OPT_TYPE_REGEXP !== $type) {
                    throw new GrammarException("Option `-$name` requires a regexp");
                }

                $this->whitespaces[] = $value;
                return;

            case 'mod':
                if (self::OPT_TYPE_STRING !== $type) {
                    throw new GrammarException("Option `-$name` requires a string");
                }
                if (null !== $this->modifiers) {
                    throw new GrammarException("Option `-$name` can be used only once");
                }

                $this->modifiers = $value;
                return;

            default:
                throw new GrammarException("Unknown option `-$name`");
        }
    }
}
