<?php
namespace VovanVE\parser\grammar\loaders;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\Rule;

/**
 * Class ArrayLoader
 * @package VovanVE\parser
 * @since 1.7.0
 */
class ArrayLoader
{
    /**
     * Create grammar object from a text
     *
     * See class description for details.
     * @param array $array Grammar definition array
     * @return Grammar Grammar object
     * @throws GrammarException Errors in grammar syntax or logic
     */
    public static function createGrammar($array): Grammar
    {
        if (!is_array($array)) {
            throw new \InvalidArgumentException('Unexpected data type');
        }
        if (!isset($array['rules'], $array['terminals'])) {
            throw new \InvalidArgumentException('Missing required fields: rules, terminals');
        }
        if (!is_array($array['rules']) || !is_array($array['terminals'])) {
            throw new \InvalidArgumentException('Unexpected data type: rules, terminals');
        }

        if (isset($array['defines'])) {
            if (!is_array($array['defines'])) {
                throw new \InvalidArgumentException('Unexpected data type: defines');
            }
            $defines = static::loadRegexps($array['defines'], 'defines');
        } else {
            $defines = [];
        }

        if (isset($array['whitespaces'])) {
            if (!is_array($array['whitespaces'])) {
                throw new \InvalidArgumentException('Unexpected data type: whitespaces');
            }
            $whitespaces = static::loadRegexps($array['whitespaces'], 'whitespaces');
        } else {
            $whitespaces = [];
        }

        if (isset($array['modifiers'])) {
            if (!is_string($array['modifiers'])) {
                throw new \InvalidArgumentException('Unexpected data type: modifiers');
            }
            $modifiers = $array['modifiers'];
        } else {
            $modifiers = '';
        }

        /** @var bool[] $terminals */
        /** @var string[] $inlines */
        /** @var string[] $fixed */
        /** @var string[] $regexpMap */
        static::loadTerminals($array['terminals'], $terminals, $inlines, $fixed, $regexpMap);

        $rules = static::loadRules($array['rules'], $terminals, array_fill_keys($inlines, true));

        return new Grammar($rules, $inlines, $fixed, $regexpMap, $whitespaces, $defines, $modifiers);
    }

    /**
     * @param array $terminals
     * @param array $names
     * @param array $inlines
     * @param array $fixed
     * @param array $regexpMap
     * @return void
     */
    protected static function loadTerminals(array $terminals, &$names, &$inlines, &$fixed, &$regexpMap): void
    {
        $inlines = [];
        $fixed = [];
        $regexpMap = [];

        $names = [];

        foreach ($terminals as $i => $terminal) {
            if (is_string($terminal)) {
                if (isset($names[$terminal])) {
                    throw new \InvalidArgumentException("Inline terminal overlaps with something else: terminals[$i]");
                }

                $inlines[] = $terminal;
                $names[$terminal] = true;
            } elseif (is_array($terminal)) {
                if (!isset($terminal['name'], $terminal['match'])) {
                    throw new \InvalidArgumentException("Missing required fields: terminals[$i]: name, match");
                }
                if (!is_string($terminal['name']) || !is_string($terminal['match'])) {
                    throw new \InvalidArgumentException("Unexpected data type: terminals[$i]: name, match");
                }

                $name = $terminal['name'];

                if (isset($names[$name])) {
                    throw new \InvalidArgumentException("Terminal name overlaps with something else: terminals[$i].name");
                }

                if (empty($terminal['isText'])) {
                    $regexpMap[$name] = $terminal['match'];
                } else {
                    $fixed[$name] = $terminal['match'];
                }

                $names[$name] = true;
            } else {
                throw new \InvalidArgumentException("Unexpected data type: terminals[$i]");
            }
        }
    }

    /**
     * @param array $rules
     * @param bool[] $terminals
     * @param array $hidden
     * @return Rule[]
     */
    protected static function loadRules(array $rules, array $terminals, array $hidden): array
    {
        /** @var Rule[] $result */
        $result = [];

        /** @var Symbol[][] $symbols */
        $symbols = [];

        /**
         * @param string $name
         * @param bool $isHidden
         * @return Symbol
         */
        $get_symbol = function (string $name, bool $isHidden = false) use (&$symbols, $terminals): Symbol {
            return $symbols[$name][$isHidden]
                ?? ($symbols[$name][$isHidden] = new Symbol($name, isset($terminals[$name]), $isHidden));
        };

        $non_terminals_names = [];

        foreach ($rules as $i => $rule) {
            if (!is_array($rule)) {
                throw new \InvalidArgumentException("Unexpected data type: rules[$i]");
            }
            if (!isset($rule['name'], $rule['definition'])) {
                throw new \InvalidArgumentException("Missing required fields: rules[$i]: name, definition");
            }
            if (!is_string($rule['name']) || !is_array($rule['definition'])) {
                throw new \InvalidArgumentException("Unexpected data type: rules[$i]: name, definition");
            }
            if (isset($rule['tag']) && !is_string($rule['tag'])) {
                throw new \InvalidArgumentException("Unexpected data type: rules[$i].tag");
            }

            /** @var Symbol[] $def_items */
            $def_items = [];
            foreach ($rule['definition'] as $j => $item) {
                if (is_string($item)) {
                    $def_items[] = $get_symbol($item, isset($hidden[$item]));
                } elseif (is_array($item)) {
                    if (!isset($item['name'])) {
                        throw new \InvalidArgumentException("Missing required fields: rules[$i].definition[$j]: name");
                    }
                    if (!is_string($item['name'])) {
                        throw new \InvalidArgumentException("Unexpected data type: rules[$i].definition[$j].name");
                    }

                    $def_items[] = $get_symbol($item['name'], !empty($item['hidden']));
                } else {
                    throw new \InvalidArgumentException("Unexpected data type: rules[$i].definition[$j]");
                }
            }

            $subject = $get_symbol($rule['name']);
            $subject->setIsTerminal(false);
            $non_terminals_names[$rule['name']] = true;

            $result[] = new Rule(
                $subject,
                $def_items,
                !empty($rule['eof']),
                isset($rule['tag']) ? $rule['tag'] : null
            );
        }

        foreach ($non_terminals_names as $name => $_) {
            if (isset($symbols[$name][true])) {
                $symbols[$name][true]->setIsTerminal(false);
            }
        }

        return $result;
    }

    /**
     * @param array $regexps
     * @param string $section
     * @return array
     */
    protected static function loadRegexps(array $regexps, string $section): array
    {
        foreach ($regexps as $name => $regexp) {
            if (!is_string($regexp)) {
                throw new \InvalidArgumentException("Unexpected data type: {$section}[$name]");
            }
        }
        return $regexps;
    }
}
