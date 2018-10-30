<?php
namespace VovanVE\parser\grammar\exporter;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;

/**
 * Class ArrayExporter
 * @package VovanVE\parser
 * @since 1.7.0
 */
class ArrayExporter extends BaseObject
{
    /**
     * @param Grammar $grammar
     * @return array
     */
    public function exportGrammar(Grammar $grammar)
    {
        $rules = [];
        $mentioned_terminals = [];

        $add_symbol = function (Symbol $symbol) use (&$mentioned_terminals): void {
            $name = $symbol->getName();

            if ($symbol->isTerminal()) {
                if (!isset($mentioned_terminals[$name])) {
                    $mentioned_terminals[$name] = [
                        'name' => $name,
                    ];
                }
            }
        };

        $inlines = $grammar->getInlines();
        $inlines_map = array_combine($inlines, $inlines);

        foreach ($grammar->getRules() as $rule) {
            $subject = $rule->getSubject();

            $add_symbol($subject);

            $definition = [];

            foreach ($rule->getDefinition() as $symbol) {
                $add_symbol($symbol);

                $name = $symbol->getName();
                if (isset($inlines_map[$name]) || !$symbol->isHidden()) {
                    $item = $name;
                } else {
                    $item = [
                        'name' => $name,
                        'hidden' => true,
                    ];
                }

                $definition[] = $item;
            }

            $rule_array = [
                'name' => $subject->getName(),
            ];

            if (null !== $rule->getTag()) {
                $rule_array['tag'] = $rule->getTag();
            }

            if ($rule->hasEofMark()) {
                $rule_array['eof'] = true;
            }

            $rule_array['definition'] = $definition;

            $rules[] = $rule_array;
        }

        $fixed_map = [];
        foreach ($grammar->getFixed() as $name => $text) {
            $fixed_map[$name] = [
                'name' => $name,
                'match' => $text,
                'isText' => true,
            ];
        }

        ksort($inlines_map, SORT_STRING);
        ksort($fixed_map, SORT_STRING);

        $regexp_map = [];
        foreach ($grammar->getRegExpMap() as $name => $regexp) {
            $regexp_map[$name] = [
                'name' => $name,
                'match' => $regexp,
            ];
        }

        $defined_terminals = $inlines_map + $fixed_map + $regexp_map;

        $missing = array_diff_key($mentioned_terminals, $defined_terminals);
        if ($missing) {
            throw new InternalException('Missing terminals: ' . join(', ', array_keys($missing)));
        }

        $result = [
            'rules' => $rules,
            'terminals' => array_values($defined_terminals),
        ];

        $defines = $grammar->getDefines();
        if ($defines) {
            $result['defines'] = $defines;
        }

        $whitespaces = $grammar->getWhitespaces();
        if ($whitespaces) {
            $result['whitespaces'] = $whitespaces;
        }

        $modifiers = $grammar->getModifiers();
        if ('' !== $modifiers) {
            $result['modifiers'] = $modifiers;
        }

        return $result;
    }
}
