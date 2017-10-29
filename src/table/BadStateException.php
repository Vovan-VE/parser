<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\DevException;
use VovanVE\parser\grammar\Rule;

/**
 * Base exception for end developer errors about stack states.
 *
 * This exception means that the grammar is not deterministic.
 * So the grammar is not LR(0).
 * @package VovanVE\parser
 */
class BadStateException extends DevException
{
    /** @var Item[] Items of state which cause the error */
    protected $items;
    /** @var Rule[] Rules reconstructed from state items */
    private $rules;

    /**
     * @param Item[] $items Items of state which cause the error
     * @param string $message Message
     * @param \Exception $previous Previous exception
     */
    public function __construct($items, $message, \Exception $previous = null)
    {
        // REFACT: minimal PHP >= 7.0: use \Throwable
        parent::__construct($message, 0, $previous);
        $this->items = $items;
    }

    /**
     * @return Item[] Items of state which cause the error
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @return Rule[] Rules reconstructed from state items
     */
    public function getRules()
    {
        if (null === $this->rules) {
            $rules = [];
            foreach ($this->items as $item) {
                $add_rule = $item->getAsRule();
                foreach ($rules as $rule) {
                    if (0 === Rule::compare($rule, $add_rule)) {
                        goto NEXT_ADD_RULE;
                    }
                }

                $rules[] = $add_rule;

                NEXT_ADD_RULE:
            }

            $this->rules = $rules;
        }
        return $this->rules;
    }
}
