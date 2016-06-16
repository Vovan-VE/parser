<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\grammar\Grammar;

class Table extends BaseObject
{
    /** @var TableRow[] */
    public $rows;
    /** @var ItemSet[] */
    public $states;

    /**
     * @param Grammar $grammar
     */
    public function __construct($grammar)
    {
        $this->prepareStates($grammar);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $out = '';
        foreach ($this->rows as $index => $row) {
            $out .= 'State #' . $index . PHP_EOL;

            //$out .= join(PHP_EOL, $this->states[$index]->getInitialItems()) . PHP_EOL; /*
            $out .= $this->states[$index] . PHP_EOL; // */

            $out .= '| ' . $row . PHP_EOL;
            $out .= '--------------------' . PHP_EOL;
        }
        return $out;
    }

    /**
     * @param Grammar $grammar
     */
    protected function prepareStates($grammar)
    {
        $rows = [];
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
                        if ($from_symbol->isTerminal) {
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
