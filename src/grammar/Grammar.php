<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;

/**
 * Grammar to define input syntax
 *
 * Grammar configured by end developer is used internally to construct parsing states table.
 *
 * Grammar definition is a set of rules according to terms for [LR(0) grammar](https://en.wikipedia.org/wiki/LR_parser).
 * Each rule is a definition for a Symbol. Symbols are divided into terminal and non-terminal.
 *
 * Terminal symbols are atomic items of a grammar. Lexer knows how to parse
 * input text into atomic tokens by terminals definitions. So, terminals definition
 * are a part of a Lexer.
 *
 * Non-terminal symbols are defined by rules in grammar.
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
 * *   Order of rules in a grammar does not matter.
 * *   All symbols which are subjects of rules will become non-terminal.
 * *   Others symbols and inline tokens will become terminals. Named terminals must
 *     be defined in Lexer.
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
 * @link https://en.wikipedia.org/wiki/LR_parser
 */
class Grammar extends BaseObject
{
    // REFACT: minimal PHP >= 7.0: const expression: extract and reuse defines

    // REFACT: minimal PHP >= 7.1: private const
    const RE_RULE_LINE = '~
        \\G
        \\h*+
        (?<rule>
            (?:
                [^\\v;"\'<>/]++
            |
                " [^\\v"]*+
                (?: " | $ )
            |
                \' [^\\v\']*+
                (?: \' | $ )
            |
                < [^\\v<>]*+
                (?: > | $ )
            |
                /
                (?: [^\\v/\\\\]++ | \\\\[^\\v] )*+
                \\\\?+
                (?: / | $ )
            )*+
        )
        (?= $ | [\\v;])
        [\\v;]*+
        (?<eof> $ )?
    ~xD';

    // REFACT: minimal PHP >= 7.1: private const
    const RE_INPUT_RULE = '/
        (?(DEFINE)
            (?<name> [a-z][a-z_0-9]*+ )
        )
        ^
        (?<subj> (?&name) )
        \\s*+
        (?:
            \(
            \\s*+
            (?<tag> (?&name) )
            \\s*+
            \)
            \\s*+
        )?
        :
        \\s*+
        (?<def>
            (?:
                [^$]++
            |
                \\$ (?! \\s*+ $ )
            )++
        )
        (?<eof> \\$ )?
        $
    /xi';

    // REFACT: minimal PHP >= 7.1: private const
    const RE_RULE_DEF_ITEM = '/
        \\G
        \\s*+
        (?:
            (?:
                (?<word> \\.? [a-z][a-z_0-9]*+  )
            |   (?<q>    " [^"]+ "
                |       \' [^\']+ \'
                |        < [^<>]+ >  )
            )
            \\s*+
        |
            (?<end> $ )
        )
    /xi';

    /** @var Rule[] Rules in the grammar */
    private $rules;
    /** @var string[] Strings list of defined inline tokens, unquoted */
    private $inlines = [];
    /** @var array Fixed tokens definition map */
    private $fixed = [];
    /** @var array RegExp tokens definition map */
    private $regexpMap = [];
    /** @var Rule Reference to the mail rule */
    private $mainRule;
    /** @var Symbol[] Map of all Symbols from all rules. Key is a symbol name. */
    private $symbols;
    /** @var Symbol[] Map of terminal Symbols from all rules. Key is a symbol name. */
    private $terminals;
    /** @var Symbol[] Map of non-terminal Symbols from all rules. Key is a symbol name. */
    private $nonTerminals;

    /**
     * Create grammar object from a text
     *
     * See class description for details.
     * @param string $text Grammar definition text
     * @return static Grammar object
     * @throws GrammarException Errors in grammar syntax or logic
     */
    public static function create($text)
    {
        $rules_strings = self::splitIntoRules($text);

        $inlines = [];
        $rules = [];

        $inline_ref_count = [];
        /** @var Rule[] $inline_ref_rule */
        $inline_ref_rule = [];
        $subject_rules_count = [];

        /** @var Symbol[][] $symbols */
        $symbols = [];
        $get_symbol = function ($name, $isInline = false) use (&$symbols, &$inlines) {
            if ($isInline) {
                if (!isset($inlines[$name]) && isset($symbols[$name])) {
                    throw new GrammarException(
                        "Inline '$name' conflicts with token <$name> defined previously"
                    );
                }
                $inlines[$name] = $name;
                $plain_name = $name;
                $is_hidden = true;
            } else {
                $is_hidden = '.' === substr($name, 0, 1);
                $plain_name = $is_hidden
                    ? substr($name, 1)
                    : $name;

                if (isset($inlines[$plain_name])) {
                    throw new GrammarException(
                        "Token <$plain_name> conflicts with inline '$plain_name' defined previously"
                    );
                }
            }

            return (isset($symbols[$plain_name][$is_hidden]))
                ? $symbols[$plain_name][$is_hidden]
                : ($symbols[$plain_name][$is_hidden] = new Symbol($plain_name, true, $is_hidden));
        };

        $non_terminals_names = [];
        $regexp_map = [];

        foreach ($rules_strings as $rule_string) {
            if ('' === $rule_string) {
                continue;
            }

            try {
                if (!preg_match(self::RE_INPUT_RULE, $rule_string, $match)) {
                    throw new GrammarException("Invalid rule format");
                }

                $subject_name = $match['subj'];

                /** @var Symbol $subject */

                $regexp_definition = self::matchRegexpDefinition($match['def']);
                if (null !== $regexp_definition) {
                    if (isset($match['tag']) && '' !== $match['tag']) {
                        throw new GrammarException("Rule tag cannot be used in RegExp rules");
                    }

                    if (isset($regexp_map[$subject_name])) {
                        throw new GrammarException("Duplicate RegExp rule for symbol `$subject_name`");
                    }

                    $get_symbol($subject_name);
                    $regexp_map[$subject_name] = $regexp_definition;
                    continue;
                }

                $subject = $get_symbol($subject_name);
                $subject->setIsTerminal(false);
                $non_terminals_names[$subject->getName()] = true;

                $rule_inlines = [];
                $definition_list = self::parseDefinitionItems($match['def'], $rule_inlines);

                $definition = [];
                foreach ($definition_list as $definition_item) {
                    $definition[] = $get_symbol(
                        $definition_item,
                        isset($rule_inlines[$definition_item])
                    );
                }

                $eof = !empty($match['eof']);
            } catch (GrammarException $e) {
                throw new GrammarException($e->getMessage() . " - rule '$rule_string'");
            }

            $rule = new Rule(
                $subject,
                $definition,
                $eof,
                isset($match['tag']) ? $match['tag'] : null
            );
            $rules[] = $rule;

            // REFACT: PHP >= 7.0: use `??`
            if (isset($subject_rules_count[$subject_name])) {
                ++$subject_rules_count[$subject_name];
            } else {
                $subject_rules_count[$subject_name] = 1;
            }

            foreach ($rule_inlines as $inline_token) {
                // REFACT: PHP >= 7.0: use `??`
                if (isset($inline_ref_count[$inline_token])) {
                    ++$inline_ref_count[$inline_token];
                } else {
                    $inline_ref_count[$inline_token] = 1;
                }

                // it is needed in case of the only reference,
                // so array of an subject is not needed
                $inline_ref_rule[$inline_token] = $rule;
            }
        }

        // Hidden symbols all are terminals still
        foreach ($non_terminals_names as $name => $_) {
            if (isset($symbols[$name][true])) {
                $symbols[$name][true]->setIsTerminal(false);
            }
        }

        // RegExp tokens cannot be non-terminals in the same time
        // since there is no anonymous RegExp terminals
        foreach (array_intersect_key($regexp_map, $non_terminals_names) as $name => $_) {
            throw new GrammarException(
                "Symbol `$name` defined as non-terminal and as regexp terminal in the same time"
            );
        }

        // Convert non-terminals defined only once with inline tokens
        // `Subject: "inline"`
        // into fixed terminals
        $fixed = [];
        foreach ($inline_ref_count as $inline_token => $ref_count) {
            if (1 === $ref_count) {
                $rule = $inline_ref_rule[$inline_token];
                if (1 === count($rule->getDefinition()) && null === $rule->getTag()) {
                    $subject = $rule->getSubject();
                    $name = $subject->getName();

                    if (1 === $subject_rules_count[$name]) {
                        // remove rule
                        $key = array_search($rule, $rules, true);
                        if (false !== $key) {
                            unset($rules[$key]);
                        }

                        // remove inline
                        unset($inlines[$inline_token]);

                        // make terminal
                        $subject->setIsTerminal(true);
                        if (isset($symbols[$name][true])) {
                            $symbols[$name][true]->setIsTerminal(true);
                        }

                        $fixed[$name] = $inline_token;
                    }
                }
            }
        }

        return new static($rules, $inlines, $fixed, $regexp_map);
    }

    /**
     * Constructor
     *
     * You should to use `create()` instead.
     * @param Rule[] $rules Manually constructed rules
     * @param string[] $inlines [since 1.4.0] List of inline token values
     * @param string[] $fixed [since 1.5.0] Fixed tokens map
     * @param string[] $regexpMap [since 1.5.0] RegExp tokens map
     * @throws GrammarException Errors in grammar syntax or logic
     * @see create()
     */
    public function __construct(
        array $rules,
        array $inlines = [],
        array $fixed = [],
        array $regexpMap = []
    )
    {
        $this->rules = array_values($rules);
        $this->inlines = array_values($inlines);
        $this->fixed = $fixed;
        $this->regexpMap = $regexpMap;
        $symbols = [];
        $terminals = [];
        $non_terminals = [];

        foreach ($rules as $rule) {
            if ($rule->hasEofMark()) {
                if ($this->mainRule) {
                    throw new GrammarException(
                        'Only one rule must to allow EOF'
                    );
                } else {
                    $this->mainRule = $rule;
                }
            }
            foreach (
                array_merge([$rule->getSubject()], $rule->getDefinition())
                as $symbol
            ) {
                /** @var Symbol $symbol */
                $symbol_name = $symbol->getName();
                if (!isset($symbols[$symbol_name])) {
                    $symbols[$symbol_name] = $symbol;
                    if ($symbol->isTerminal()) {
                        $terminals[$symbol_name] = $symbol;
                    } else {
                        $non_terminals[$symbol_name] = $symbol;
                    }
                }
            }
        }
        if (!$this->mainRule) {
            throw new GrammarException(
                'Exactly one rule must to allow EOF - it will be main rule'
            );
        }
        if (!$terminals) {
            throw new GrammarException('No terminals');
        }
        $this->symbols = $symbols;
        $this->terminals = $terminals;
        $this->nonTerminals = $non_terminals;
    }

    /**
     * Strings list of defined inline tokens, unquoted
     * @return string[]
     * @since 1.4.0
     */
    public function getInlines()
    {
        return $this->inlines;
    }

    /**
     * Fixed tokens definition map
     * @return string[]
     * @since 1.5.0
     */
    public function getFixed()
    {
        return $this->fixed;
    }

    /**
     * RegExp tokens definition map
     * @return string[]
     * @since 1.5.0
     */
    public function getRegExpMap()
    {
        return $this->regexpMap;
    }

    /**
     * Rules in the grammar
     * @return Rule[]
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Reference to the mail rule
     * @return Rule
     */
    public function getMainRule()
    {
        return $this->mainRule;
    }

    /**
     * Map of terminal Symbols from all rules
     *
     * Key is a symbol name.
     * @return Symbol[]
     */
    public function getTerminals()
    {
        return $this->terminals;
    }

    /**
     * Map of non-terminal Symbols from all rules
     *
     * Key is a symbol name.
     * @return Symbol[]
     */
    public function getNonTerminals()
    {
        return $this->nonTerminals;
    }

    /**
     * Get one Symbol by its name
     * @param string $name Symbol name to search
     * @return Symbol|null
     */
    public function getSymbol($name)
    {
        return isset($this->symbols[$name]) ? $this->symbols[$name] : null;
    }

    /**
     * Get rules defining a subject Symbol
     * @param Symbol $subject Symbol to search
     * @return Rule[]
     */
    public function getRulesFor($subject)
    {
        $rules = [];
        if (!$subject->isTerminal()) {
            foreach ($this->rules as $rule) {
                if (0 === Symbol::compare($rule->getSubject(), $subject)) {
                    $rules[] = $rule;
                }
            }
        }
        return $rules;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return join(PHP_EOL, $this->rules);
    }

    /**
     * @param string $grammarText
     * @return array|false|mixed|string[]
     */
    private static function splitIntoRules($grammarText)
    {
        if (false === preg_match_all(self::RE_RULE_LINE, $grammarText, $matches, PREG_SET_ORDER)) {
            throw new InternalException('PCRE error', preg_last_error());
        }

        if (!$matches) {
            throw new GrammarException('Cannot parse grammar text near start');
        }

        $last_match = $matches[count($matches) - 1];

        $rules = array_column($matches, 'rule');
        $rules = preg_replace('/^\\s+|\\s+$/u', '', $rules);
        $rules = array_filter($rules, 'strlen');

        if (!isset($last_match['eof'])) {
            if ($rules) {
                throw new GrammarException(
                    "Cannot parse grammar after rule `{$rules[count($rules) - 1]}`"
                );
            }
            throw new GrammarException('Could not parse grammar after some empty start');
        }
        return $rules;
    }

    /**
     * Parse rule definition body into tokens as strings
     * @param string $input Input string with rule definition body
     * @param array $inlines Variable to store values of inline tokens. Key are same as values
     * @return string[] List of tokens strings
     * @since 1.4.0
     */
    private static function parseDefinitionItems($input, array &$inlines)
    {
        if (!preg_match_all(self::RE_RULE_DEF_ITEM, $input, $matches, PREG_SET_ORDER)) {
            throw new GrammarException("Invalid rule definition");
        }

        $last_match = array_pop($matches);
        if (!isset($last_match['end'])) {
            throw new GrammarException("Invalid rule definition");
        }

        $items = [];
        foreach ($matches as $match) {
            if (isset($match['q'])) {
                $inline = substr($match['q'], 1, -1);
                if (!isset($inlines[$inline]) && in_array($inline, $items, true)) {
                    throw new GrammarException(
                        "Inline token {$match['q']} conflicts with token <$inline>"
                    );
                }

                $items[] = $inline;
                $inlines[$inline] = $inline;
            } elseif (isset($match['word'])) {
                $word = $match['word'];
                $items[] = $word;

                $name = ltrim($word, '.');
                if (isset($inlines[$name])) {
                    throw new GrammarException(
                        "Token <$word> conflicts with inline token '$name'"
                    );
                }
            } else {
                throw new InternalException('Unexpected item match');
            }
        }

        return $items;
    }

    // REFACT: minimal PHP >= 7.1: private const
    const RE_RULE_DEF_REGEXP = '~
        ^
        /
        (?<re>
            (?:
                [^/\\\\]++
            |
                \\\\.
            )*+
        )
        (?<closed> / )?+
        (?<end> $ )?
    ~x';

    /**
     * Try to parse rule definition as RegExp rule
     *
     * RegExp rule must to consist of a RegExp only: `/.../`.
     *
     * If input definition does not begin with delimiter `/`,
     * parsing is successfully discarded with returning `null`.
     *
     * Otherwise parsed RegExp given between delimiters will
     * be returned is it parsed successfully. In case of error
     * an `GrammarException` will be thrown.
     *
     * > Note: RegExp body itself will not be checked or parsed
     * > in details.
     *
     * > Note: Escaped slash `\/` (`'\\/'` in string literal) **can** be used.
     * @param string $input Input rule body from grammar text
     * @return string|null RegExp body without delimiters. `null` will be returned
     * when input definition does not begin with RegExp delimiter `/`.
     * @throws GrammarException RegExp syntax is started with delimiter but then comes an error.
     */
    private static function matchRegexpDefinition($input)
    {
        if (!preg_match(self::RE_RULE_DEF_REGEXP, $input, $match)) {
            // self failure check
            if ('' !== $input && '/' === $input[0]) {
                throw new InternalException('RegExp definition start `/...` did not match');
            }

            return null;
        }

        if (!isset($match['closed']) || '' === $match['closed']) {
            throw new GrammarException('RegExp definition is not terminated with final delimiter');
        }
        if (!isset($match['end'])) {
            throw new GrammarException('RegExp definition must be the only in a rule');
        }

        $regexp_part = $match['re'];
        if ('' === $regexp_part) {
            throw new GrammarException('Empty RegExp definition');
        }

        return $regexp_part;
    }
}
