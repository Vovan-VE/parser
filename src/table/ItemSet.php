<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\Rule;

/**
 * Set of Items for a parser state
 *
 * Each parser state has a set of Items based on source rules and
 * a current parsing position inside rules.
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class ItemSet extends BaseObject
{
    /** @var Item[] All Items in the set */
    public $items;
    /** @var Item[] Original Items which this set is initiated from */
    protected $initialItems;

    /**
     * Create a Set from an Items using a grammar
     * @param Item[] $items Initial Items to expand
     * @param Grammar $grammar Grammar to expand non-terminals
     * @return static Returns new Set
     * @throws ConflictShiftReduceException Shift-reduce conflict is detected
     * @throws ConflictReduceReduceException Reduce-reduce conflict is detected
     */
    public static function createFromItems(array $items, Grammar $grammar): self
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
                if (!$next_symbol || $next_symbol->isTerminal()) {
                    continue;
                }
                $name = $next_symbol->getName();
                if (isset($known_next_symbols[$name])) {
                    continue;
                }
                $next_symbols[$name] = $next_symbol;

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
     * @param Item[] $items All Items in the set
     * @param Item[] $initialItems Original Items which this set is initiated from
     * @param Grammar $grammar Source grammar
     * @throws ConflictShiftReduceException Shift-reduce conflict is detected
     * @throws ConflictReduceReduceException Reduce-reduce conflict is detected
     * @uses Item::compare()
     */
    public function __construct(array $items, array $initialItems, Grammar $grammar)
    {
        $items_list = array_values($items);

        // sort items to simplify equality comparison between sets itself
        usort($items_list, [Item::class, 'compare']);

        $this->items = $items_list;
        $this->initialItems = array_values($initialItems);

        $this->validateDeterministic($grammar);
    }

    /**
     * Original Items which this set is initiated from
     * @return Item[]
     */
    public function getInitialItems(): array
    {
        return $this->initialItems;
    }

    /**
     * Create list all all next Sets for next states by shifting current position in Items
     * @param Grammar $grammar Source grammar
     * @return static[] New Sets for other states
     */
    public function getNextSets(Grammar $grammar): array
    {
        $next_map = [];
        foreach ($this->items as $item) {
            $symbol = $item->getExpected();
            if (!$symbol) {
                continue;
            }
            $name = $symbol->getName();
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
     * Check if this set contains an equal Item
     * @param Item $item Item to search
     * @param bool $initialOnly Whether to search in initial items only
     * @return bool Returns `true` when the set contains equal item
     */
    public function hasItem(Item $item, bool $initialOnly = true): bool
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
     * Check if this set has item where the next token must be EOF
     * @return bool
     */
    public function hasFinalItem(): bool
    {
        foreach ($this->items as $item) {
            if ($item->hasEofMark() && !$item->further) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get reduction rule of this set if any
     * @return Rule|null
     */
    public function getReduceRule(): ?Rule
    {
        foreach ($this->items as $item) {
            if (!$item->further) {
                return $item->getAsRule();
            }
        }
        return null;
    }

    /**
     * Check equality with another set
     *
     * Two sets are equal when both contains only equal items in any order.
     * In fact items are already sorted before for this comparison.
     * @param ItemSet $that Another set to compare with
     * @return bool Returns `true` when both sets are equal
     */
    public function isSame(ItemSet $that): bool
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

    private const DUMP_PREFIX_MAIN = '';
    private const DUMP_PREFIX_SUB = '> ';

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
            /** @var Item[] $items */
            $prefix = ($ex) ? self::DUMP_PREFIX_SUB : self::DUMP_PREFIX_MAIN;
            foreach ($items as $item) {
                $out[] = $prefix . $item;
            }
        }

        return join(PHP_EOL, $out);
    }

    /**
     * Validate if this set is deterministic
     * @param Grammar $grammar Source grammar
     * @throws ConflictShiftReduceException Shift-reduce conflict is detected
     * @throws ConflictReduceReduceException Reduce-reduce conflict is detected
     */
    private function validateDeterministic(Grammar $grammar): void
    {
        /** @var Item[] */
        $finite = [];
        $terminals = [];
        $non_terminals = [];
        foreach ($this->items as $item) {
            $next_symbol = $item->getExpected();
            if (!$next_symbol) {
                $finite[] = $item;
            } elseif ($next_symbol->isTerminal()) {
                $terminals[] = $item;
            } else {
                $non_terminals[] = $item;
            }
        }

        $this->validateDeterministicShiftReduce(
            $finite,
            $terminals,
            $non_terminals,
            $grammar
        );
        $this->validateDeterministicReduceReduce($finite);
    }

    /**
     * Check for Shift-Reduce conflicts
     * @param Item[] $finite Items where all symbols are already passed
     * @param Item[] $terminals Items where next expected symbol is terminal
     * @param Item[] $nonTerminals Items where next expected symbol is non-terminal
     * @param Grammar $grammar Source grammar
     * @throws ConflictShiftReduceException Shift-reduce conflict is detected
     */
    private function validateDeterministicShiftReduce(
        array $finite,
        array $terminals,
        array $nonTerminals,
        Grammar $grammar
    ): void {
        if ($finite && $terminals) {
            $left_terminals = $grammar->getTerminals();
            foreach ($terminals as $item) {
                unset($left_terminals[$item->getExpected()->getName()]);
            }
            if (!$left_terminals) {
                throw new ConflictShiftReduceException(
                    array_merge($finite, $terminals, $nonTerminals)
                );
            }
        }
    }

    /**
     * Check for Reduce-Reduce conflicts
     * @param Item[] $finite Items where all symbols are already passed
     * @throws ConflictReduceReduceException Reduce-reduce conflict is detected
     */
    private function validateDeterministicReduceReduce(array $finite): void
    {
        if (count($finite) > 1) {
            throw new ConflictReduceReduceException($finite);
        }
    }
}
