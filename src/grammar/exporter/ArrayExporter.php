<?php
namespace VovanVE\parser\grammar\exporter;

use VovanVE\parser\common\BaseObject;
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
        $terminals = [];

        $add_symbol = function (Symbol $symbol) use (&$terminals): void {
            $name = $symbol->getName();

            if ($symbol->isTerminal()) {
                if (!isset($terminals[$name])) {
                    $terminals[$name] = [
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

        $terminals = $inlines_map + $terminals;

        foreach ($grammar->getFixed() as $name => $text) {
            $terminals[$name] += [
                'match' => $text,
                'isText' => true,
            ];
        }

        foreach ($grammar->getRegExpMap() as $name => $regexp) {
            $terminals[$name]['match'] = $regexp;
        }

        ksort($terminals);

        $result = [
            'rules' => $rules,
            'terminals' => array_values($terminals),
        ];

        $defines = $grammar->getDefines();
        if ($defines) {
            ksort($defines);
            $result['defines'] = $defines;
        }

        $whitespaces = $grammar->getWhitespaces();
        if ($whitespaces) {
            sort($whitespaces);
            $result['whitespaces'] = $whitespaces;
        }

        $modifiers = $grammar->getModifiers();
        if ('' !== $modifiers) {
            $result['modifiers'] = $modifiers;
        }

        return $result;
    }
}
