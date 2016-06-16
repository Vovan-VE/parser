<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\Rule;

class ItemSet extends BaseObject
{
    /** @var Item[] */
    public $items;
    /** @var Item[] */
    protected $initialItems;

    /**
     * @param Item[] $items
     * @param Grammar $grammar
     * @return static
     */
    public static function createFromItems(array $items, $grammar)
    {
        $final_items = [];
        $new_items = $items;
        /** @var Symbol $known_next_symbols */
        $known_next_symbols = [];
        while ($new_items) {
            /** @var Symbol $next_symbols */
            $next_symbols = [];
            foreach ($new_items as $new_item) {
                foreach ($final_items as $item) {
                    if (0 === Item::compare($item, $new_item)) {
                        goto NEXT_NEW_ITEM;
                    }
                }
                $final_items[] = $new_item;

                $next_symbol = $new_item->getExpected();
                if (!$next_symbol || $next_symbol->isTerminal) {
                    continue;
                }
                if (isset($known_next_symbols[$next_symbol->name])) {
                    continue;
                }
                $next_symbols[$next_symbol->name] = $next_symbol;

                NEXT_NEW_ITEM:
            }

            $known_next_symbols += $next_symbols;

            $new_items = [];

            foreach ($next_symbols as $next_symbol) {
                $rules = $grammar->getRulesFor($next_symbol);
                foreach ($rules as $rule) {
                    $new_items[] = Item::createFromRule($rule);
                }
            }
        }

        return new static($final_items, $items, $grammar);
    }

    /**
     * @param Item[] $items
     * @param Item[] $initialItems
     * @param Grammar $grammar
     * @uses Item::compare()
     */
    public function __construct(array $items, array $initialItems, $grammar)
    {
        $items_list = array_values($items);
        usort($items_list, [Item::className(), 'compare']);
        $this->items = $items_list;
        $this->initialItems = array_values($initialItems);

        $this->validateDeterministic($grammar);
    }

    /**
     * @return Item[]
     */
    public function getInitialItems()
    {
        return $this->initialItems;
    }

    /**
     * @param Grammar $grammar
     * @return static[]
     */
    public function getNextSets($grammar)
    {
        $next_map = [];
        foreach ($this->items as $item) {
            $symbol = $item->getExpected();
            if (!$symbol) {
                continue;
            }
            $name = $symbol->name;
            $next_item = $item->shift();
            if (isset($next_map[$name])) {
                foreach ($next_map[$name] as $known_item) {
                    if (0 === Item::compare($next_item, $known_item)) {
                        goto NEXT_ITEM;
                    }
                }
                $next_map[$name][] = $next_item;
            } else {
                $next_map[$name] = [$next_item];
            }

            NEXT_ITEM:
        }

        $sets = [];
        foreach ($next_map as $name => $items) {
            $sets[$name] = static::createFromItems($items, $grammar);
        }
        return $sets;
    }

    /**
     * @param Item $item
     * @param bool $initialOnly
     * @return bool
     */
    public function hasItem($item, $initialOnly = true)
    {
        $list = ($initialOnly) ? $this->initialItems : $this->items;
        foreach ($list as $my_item) {
            if (0 === Item::compare($my_item, $item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function hasFinalItem()
    {
        foreach ($this->items as $item) {
            if ($item->eof && !$item->further) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Rule|null
     */
    public function getReduceRule()
    {
        foreach ($this->items as $item) {
            if (!$item->further) {
                return $item->getAsRule();
            }
        }
        return null;
    }

    /**
     * @param ItemSet $that
     * @return bool
     */
    public function isSame($that)
    {
        if (count($this->items) !== count($that->items)) {
            return false;
        }
        foreach ($this->items as $i => $item) {
            if (0 !== Item::compare($item, $that->items[$i])) {
                return false;
            }
        }
        return true;
    }

    const DUMP_PREFIX_MAIN = '';
    const DUMP_PREFIX_SUB = '> ';

    /**
     * @return string
     */
    public function __toString()
    {
        $expanded_items = [];
        foreach ($this->items as $item) {
            foreach ($this->initialItems as $initial_item) {
                if (0 === Item::compare($item, $initial_item)) {
                    goto NEXT_ITEM;
                }
            }

            $expanded_items[] = $item;

            NEXT_ITEM:
        }

        $out = [];
        foreach ([$this->initialItems, $expanded_items] as $ex => $items) {
            $prefix = ($ex) ? self::DUMP_PREFIX_SUB : self::DUMP_PREFIX_MAIN;
            foreach ($items as $item) {
                $out[] = $prefix . $item;
            }
        }

        return join(PHP_EOL, $out);
    }

    /**
     * @param Grammar $grammar
     */
    private function validateDeterministic($grammar)
    {
        /** @var Item[] */
        $finite = [];
        $terminals = [];
        $non_terminals = [];
        foreach ($this->items as $item) {
            $next_symbol = $item->getExpected();
            if (!$next_symbol) {
                $finite[] = $item;
            } elseif ($next_symbol->isTerminal) {
                $terminals[] = $item;
            } else {
                $non_terminals[] = $item;
            }
        }

        $this->validateDeterministicShiftReduce($finite, $terminals, $non_terminals, $grammar);
        $this->validateDeterministicReduceReduce($finite);
    }

    /**
     * @param Item[] $finite
     * @param Item[] $terminals
     * @param Item[] $nonTerminals
     * @param Grammar $grammar
     */
    private function validateDeterministicShiftReduce($finite, $terminals, $nonTerminals, $grammar)
    {
        if ($finite && $terminals) {
            $left_terminals = $grammar->getTerminals();
            foreach ($terminals as $item) {
                unset($left_terminals[$item->getExpected()->name]);
            }
            if (!$left_terminals) {
                throw new ConflictShiftReduceException(array_merge($finite, $terminals, $nonTerminals));
            }
        }
    }

    /**
     * @param Item[] $finite
     */
    private function validateDeterministicReduceReduce($finite)
    {
        if (count($finite) > 1) {
            throw new ConflictReduceReduceException($finite);
        }
    }
}
