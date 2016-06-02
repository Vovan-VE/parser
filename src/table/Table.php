<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\grammar\Grammar;

class Table extends BaseObject
{
    /**
     * @param Grammar $grammar
     */
    public function __construct($grammar)
    {
        $states = static::prepareStates($grammar);
    }

    /**
     * @param Grammar $grammar
     * @return ItemSet[]
     */
    protected static function prepareStates($grammar)
    {
        $states = [];
        $item = Item::createFromRule($grammar->getMainRule());
        $item_set = ItemSet::createFromItems([$item], $grammar);
        $new_states = [$item_set];
        while ($new_states) {
            $next_states_array = [];
            foreach ($new_states as $new_state) {
                foreach ($states as $state) {
                    if ($new_state->isSame($state)) {
                        goto NEXT_NEW_STATE;
                    }
                }

                $states[] = $new_state;
                $next_states_array[] = $new_state->getNextSets($grammar);

                NEXT_NEW_STATE:
            }

            if (!$next_states_array) {
                $new_states = [];
            } elseif (1 === count($next_states_array)) {
                $new_states = $next_states_array[0];
            } else {
                // REFACT: PHP >= 5.6:
                //$new_states = array_merge(...$next_states_array);
                $new_states = call_user_func_array('array_merge', $next_states_array);
            }
        }

        return $states;
    }
}
