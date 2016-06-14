<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\DevException;
use VovanVE\parser\grammar\Rule;

class BadStateException extends DevException
{
    /** @var Item[] */
    protected $items;
    /** @var Rule[] */
    private $rules;

    /**
     * @param Item[] $items
     * @param string $message
     * @param \Exception $previous
     */
    public function __construct(array $items, $message, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->items = $items;
    }

    /**
     * @return Item[]
     */
    final public function getItems()
    {
        return $this->items;
    }

    /**
     * @return Rule[]
     */
    final public function getRules()
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
