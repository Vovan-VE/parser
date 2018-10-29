<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\BaseLexer;

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
class Grammar extends BaseLexer
{
    /** @var Rule[] Rules in the grammar */
    private $rules;

    /** @var Rule Reference to the mail rule */
    private $mainRule;
    /** @var Symbol[] Map of all Symbols from all rules. Key is a symbol name. */
    private $symbols;
    /** @var Symbol[] Map of terminal Symbols from all rules. Key is a symbol name. */
    private $terminals;
    /** @var Symbol[] Map of non-terminal Symbols from all rules. Key is a symbol name. */
    private $nonTerminals;

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
        string $modifiers = ''
    ) {
        if (!$rules) {
            throw new GrammarException('No rules defined');
        }

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

        $undefined = array_diff_key(
            $terminals,
            array_flip($this->inlines),
            $this->fixed,
            $this->regexpMap
        );

        if ($undefined) {
            $undefined = array_keys($undefined);
            sort($undefined);
            throw new GrammarException('There are terminals without definitions: ' . join(', ', $undefined));
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
    public function getInlines(): array
    {
        return $this->inlines;
    }

    /**
     * Fixed tokens definition map
     * @return string[]
     * @since 1.5.0
     */
    public function getFixed(): array
    {
        return $this->fixed;
    }

    /**
     * RegExp tokens definition map
     * @return string[]
     * @since 1.5.0
     */
    public function getRegExpMap(): array
    {
        return $this->regexpMap;
    }

    /**
     * RegExp map for DEFINEs
     * @return string[]
     * @since 1.7.0
     */
    public function getDefines(): array
    {
        return $this->defines;
    }

    /**
     * RegExp list for whitespaces
     * @return string[]
     * @since 1.7.0
     */
    public function getWhitespaces(): array
    {
        return $this->whitespaces;
    }

    /**
     * Top RegExp modifiers
     * @return string
     * @since 1.7.0
     */
    public function getModifiers(): string
    {
        return $this->modifiers;
    }

    /**
     * Rules in the grammar
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Reference to the mail rule
     * @return Rule
     */
    public function getMainRule(): Rule
    {
        return $this->mainRule;
    }

    /**
     * Map of terminal Symbols from all rules
     *
     * Key is a symbol name.
     * @return Symbol[]
     */
    public function getTerminals(): array
    {
        return $this->terminals;
    }

    /**
     * Map of non-terminal Symbols from all rules
     *
     * Key is a symbol name.
     * @return Symbol[]
     */
    public function getNonTerminals(): array
    {
        return $this->nonTerminals;
    }

    /**
     * Get one Symbol by its name
     * @param string $name Symbol name to search
     * @return Symbol|null
     */
    public function getSymbol(string $name): ?Symbol
    {
        return $this->symbols[$name] ?? null;
    }

    /**
     * Get rules defining a subject Symbol
     * @param Symbol $subject Symbol to search
     * @return Rule[]
     */
    public function getRulesFor(Symbol $subject): array
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
}
