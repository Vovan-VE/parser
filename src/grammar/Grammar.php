<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\loaders\TextLoader;

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
 * Here is some terms:
 *
 * *   Grammar must to contain exactly one main rule which has EOF marker.
 *     Subject symbol name for the main rule currently does not matter, but must be present for
 *     consistency and must be unique. Say it can be for example `Goal` or `TOP`.
 *     Tag for the main rule also has no usage.
 * *   Order of rules in a grammar does not matter.
 * *   All symbols which are subjects of rules will become non-terminal.
 * *   Others symbols and inline tokens will become terminals. Named terminals must
 *     be defined in Lexer.
 * *   Named symbols are case sensitive. It is recommended, but not required, to use
 *     lower case names (`foo`) for terminals and upper CamelCase (`FooBar`) for non-terminals.
 *
 * @package VovanVE\parser
 * @link https://en.wikipedia.org/wiki/LR_parser
 */
class Grammar extends BaseObject
{
    /** @deprecated >= 1.7.0 */
    const RE_RULE_LINE = TextLoader::RE_RULE_LINE;
    /** @deprecated >= 1.7.0 */
    const RE_INPUT_RULE = TextLoader::RE_INPUT_RULE;
    /** @deprecated >= 1.7.0 */
    const RE_RULE_DEF_ITEM = TextLoader::RE_RULE_DEF_REGEXP;

    /** @var Rule[] Rules in the grammar */
    private $rules;
    /** @var string[] Strings list of defined inline tokens, unquoted */
    private $inlines = [];
    /** @var array Fixed tokens definition map */
    private $fixed = [];
    /** @var array RegExp tokens definition map */
    private $regexpMap = [];
    /**
     * @var array Map of RegExp DEFINE's to reference from terminals and whitespaces.
     * Key is name and value is a part of RegExp
     */
    private $defines;
    /**
     * @var array List of RegExp parts to define whitespaces to ignore in an input text.
     * DEFINEs can be referred with `(?&name)` regexp recursion.
     */
    private $whitespaces = [];
    /**
     * @var string Modifiers to whole regexp.
     *
     * Same modifiers will be applied both to tokens and whitespaces regexps.
     *
     * Here only "global" modifiers like `u`, `x`, `D`, etc.
     * should be used. Other modifiers like `i` should (but not required) be used locally
     * in specific parts like `(?i)[a-z]` or `(?i:[a-z])`.
     */
    private $modifiers = '';
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
     * @deprecated >= 1.7.0: use `TextLoader::createGrammar()` instead
     */
    public static function create($text)
    {
        return TextLoader::createGrammar($text);
    }

    /**
     * Constructor
     *
     * You should to use a loader instead.
     *
     * **_DON'T USE CONSTRUCTOR DIRECTLY WITH TONS OF ARGUMENTS. USE A LOADER INSTEAD._**
     * @param Rule[] $rules Manually constructed rules
     * @param string[] $inlines [since 1.4.0] List of inline token values
     * @param string[] $fixed [since 1.5.0] Fixed tokens map
     * @param string[] $regexpMap [since 1.5.0] RegExp tokens map
     * @param string[] $whitespaces [since 1.7.0] List of RegExp for whitespaces to skip
     * @param string[] $defines [since 1.7.0] RegExp DEFINEs map
     * @param string $modifiers [since 1.7.0] Top RegExp modifiers
     * @throws GrammarException Errors in grammar syntax or logic
     * @see TextLoader
     */
    public function __construct(
        array $rules,
        array $inlines = [],
        array $fixed = [],
        array $regexpMap = [],
        array $whitespaces = [],
        array $defines = [],
        $modifiers = ''
    ) {
        $this->rules = array_values($rules);
        $this->inlines = array_values($inlines);
        $this->fixed = $fixed;
        $this->regexpMap = $regexpMap;
        $this->whitespaces = $whitespaces;
        $this->defines = $defines;
        $this->modifiers = $modifiers;
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
     * RegExp map for DEFINEs
     * @return string[]
     * @since 1.7.0
     */
    public function getDefines()
    {
        return $this->defines;
    }

    /**
     * RegExp list for whitespaces
     * @return string[]
     * @since 1.7.0
     */
    public function getWhitespaces()
    {
        return $this->whitespaces;
    }

    /**
     * Top RegExp modifiers
     * @return string
     * @since 1.7.0
     */
    public function getModifiers()
    {
        return $this->modifiers;
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

    /** @deprecated >= 1.7.0 */
    const RE_RULE_DEF_REGEXP = TextLoader::RE_RULE_DEF_REGEXP;
}
