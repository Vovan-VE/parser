<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;

class Grammar extends BaseObject
{
    /** @var Rule[] */
    public $rules;

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
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return join(PHP_EOL, $this->rules);
    }
}
