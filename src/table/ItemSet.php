<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;

class ItemSet extends BaseObject
{
    /** @var Item[] */
    public $items;

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

        return new static($final_items);
    }

    /**
     * @param Item[] $items
     * @uses Item::compare()
     */
    public function __construct(array $items)
    {
        $items_list = array_values($items);
        usort($items_list, [Item::className(), 'compare']);
        $this->items = $items_list;
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
        foreach ($next_map as $items) {
            $sets[] = static::createFromItems($items, $grammar);
        }
        return $sets;
    }

    /**
     * @param self $that
     * @return bool
     */
    public function isSame(self $that)
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
        return self::DUMP_PREFIX_MAIN . join(PHP_EOL . self::DUMP_PREFIX_SUB, $this->items);
    }
}
