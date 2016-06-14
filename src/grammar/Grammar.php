<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;

class Grammar extends BaseObject
{
    /** @var Rule[] */
    public $rules;
    /** @var Rule */
    protected $mainRule;
    /** @var Symbol[] */
    protected $symbols;
    /** @var Symbol[] */
    protected $terminals;
    /** @var Symbol[] */
    protected $nonTerminals;

    /**
     * @param string $text
     * @return static
     */
    public static function create($text)
    {
        $rules_strings = preg_split('/[;\\r\\n\\f]+/u', $text, 0, PREG_SPLIT_NO_EMPTY);
        $rules_strings = preg_replace('/^\\s+|\\s+$/u', '', $rules_strings);

        $rules = [];

        $symbols = [];
        $get_symbol = function ($name) use (&$symbols) {
            return (isset($symbols[$name]))
                ? $symbols[$name]
                : ($symbols[$name] = new Symbol($name, true));
        };

        foreach ($rules_strings as $rule_string) {
            if ('' === $rule_string) {
                continue;
            }

            if (!preg_match('/^(?<subj>\\w++)\\s*+:\\s*+(?<def>[\\w\\s]++)(?<eof>\\$)?$/u', $rule_string, $match)) {
                throw new \InvalidArgumentException("Invalid rule format: '$rule_string'");
            }

            $subject = $get_symbol($match['subj']);
            $subject->isTerminal = false;

            $definition_strings = preg_split('/\\s++/u', $match['def'], 0, PREG_SPLIT_NO_EMPTY);
            $definition = array_map($get_symbol, $definition_strings);

            $eof = !empty($match['eof']);

            $rules[] = new Rule($subject, $definition, $eof);
        }

        return new static($rules);
    }

    /**
     * @param Rule[] $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
        $symbols = [];
        $terminals = [];
        $non_terminals = [];

        foreach ($rules as $rule) {
            if ($rule->eof) {
                if ($this->mainRule) {
                    throw new GrammarException('Only one rule must to allow EOF');
                } else {
                    $this->mainRule = $rule;
                }
            }
            foreach (array_merge([$rule->subject], $rule->definition) as $symbol) {
                $symbol_name = $symbol->name;
                if (!isset($symbols[$symbol_name])) {
                    $symbols[$symbol_name] = $symbol;
                    if ($symbol->isTerminal) {
                        $terminals[$symbol_name] = $symbol;
                    } else {
                        $non_terminals[$symbol_name] = $symbol;
                    }
                }
            }
        }
        if (!$this->mainRule) {
            throw new GrammarException('Exactly one rule must to allow EOF - it will be main rule');
        }
        if (!$non_terminals) {
            throw new GrammarException('No terminals');
        }
        $this->symbols = $symbols;
        $this->terminals = $terminals;
        $this->nonTerminals = $non_terminals;
    }

    /**
     * @return Rule
     */
    public function getMainRule()
    {
        return $this->mainRule;
    }

    /**
     * @return Symbol[]
     */
    public function getTerminals()
    {
        return $this->terminals;
    }

    /**
     * @return Symbol[]
     */
    public function getNonTerminals()
    {
        return $this->terminals;
    }

    /**
     * @param string $name
     * @return Symbol
     */
    public function getSymbol($name)
    {
        return isset($this->symbols[$name]) ? $this->symbols[$name] : null;
    }

    /**
     * @param Symbol $subject
     * @return Rule[]
     */
    public function getRulesFor($subject)
    {
        $rules = [];
        if (!$subject->isTerminal) {
            foreach ($this->rules as $rule) {
                if (0 === Symbol::compare($rule->subject, $subject)) {
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
