<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\grammar\Grammar;

/**
 * Parser states table
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class Table extends BaseObject
{
    /** @var TableRow[] Rows in the table. Keys are state indices. */
    private $rows;
    /** @var ItemSet[] All items sets for all states. Keys are state indices. */
    private $states;

    /**
     * @param Grammar $grammar Source grammar to work with
     */
    public function __construct(Grammar $grammar)
    {
        $this->prepareStates($grammar);
    }

    /**
     * Rows in the table
     *
     * Keys are state indices.
     * @return TableRow[]
     * @since 2.0.0
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Get specific row of table
     * @param int $index State index
     * @return TableRow
     * @since 2.0.0
     */
    public function getRow(int $index): TableRow
    {
        return $this->rows[$index];
    }

    /**
     * All items sets for all states
     *
     * Keys are state indices.
     * @return ItemSet[]
     * @since 2.0.0
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Get items sets for specific state
     * @param int $index State index
     * @return ItemSet
     * @since 2.0.0
     */
    public function getState(int $index): ItemSet
    {
        return $this->states[$index];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $out = '';
        foreach ($this->rows as $index => $row) {
            $out .= 'State #' . $index . PHP_EOL;
            $out .= $this->states[$index] . PHP_EOL;
            $out .= '| ' . $row . PHP_EOL;
            $out .= '--------------------' . PHP_EOL;
        }
        return $out;
    }

    /**
     * Fulfill the table according to specified grammar
     * @param Grammar $grammar
     */
    protected function prepareStates(Grammar $grammar): void
    {
        /** @var TableRow[] $rows */
        $rows = [];
        /** @var ItemSet[] $states */
        $states = [];
        $item = Item::createFromRule($grammar->getMainRule());
        $item_set = ItemSet::createFromItems([$item], $grammar);
        /** @var ItemSet[][] $add_states_map */
        $add_states_map = [0 => ['' => $item_set]];
        while ($add_states_map) {
            $next_states_map = [];
            foreach ($add_states_map as $from_state_index => $to_states_list) {
                foreach ($to_states_list as $from_symbol_name => $new_state) {
                    $from_symbol_term = false;
                    $from_symbol_non_term = false;
                    if ('' !== $from_symbol_name) {
                        $from_symbol = $grammar->getSymbol($from_symbol_name);
                        if ($from_symbol->isTerminal()) {
                            $from_symbol_term = true;
                        } else {
                            $from_symbol_non_term = true;
                        }
                    }
                    foreach ($states as $i => $state) {
                        if ($new_state->isSame($state)) {
                            $row = $rows[$from_state_index];
                            if ($from_symbol_term) {
                                $row->terminalActions[$from_symbol_name] = $i;
                            } elseif ($from_symbol_non_term) {
                                $row->gotoSwitches[$from_symbol_name] = $i;
                            }
                            goto NEXT_NEW_STATE;
                        }
                    }

                    $state_index = count($states);
                    $states[] = $new_state;
                    $rows[] = new TableRow();

                    if ($new_state->hasFinalItem()) {
                        $rows[$state_index]->eofAction = true;
                    }

                    $next_states_map[$state_index] = $new_state->getNextSets($grammar);

                    $row = $rows[$from_state_index];
                    if ($from_symbol_term) {
                        $row->terminalActions[$from_symbol_name] = $state_index;
                    } elseif ($from_symbol_non_term) {
                        $row->gotoSwitches[$from_symbol_name] = $state_index;
                    }

                    NEXT_NEW_STATE:
                }
            }

            $add_states_map = $next_states_map;
        }

        foreach ($states as $index => $state) {
            $rule = $state->getReduceRule();
            if ($rule) {
                $rows[$index]->reduceRule = $rule;
            }
        }

        $this->rows = $rows;
        $this->states = $states;
    }
}
