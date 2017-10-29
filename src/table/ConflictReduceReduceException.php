<?php
namespace VovanVE\parser\table;

/**
 * Reduce-reduce conflict
 *
 * This means that there are a number or rules are applicable to reduce in the current state.
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class ConflictReduceReduceException extends BadStateException
{
    /**
     * @param Item[] $items Items of state which cause the error
     * @param \Exception $previous Previous exception
     */
    public function __construct(array $items, \Exception $previous = null)
    {
        // REFACT: minimal PHP >= 7.0: use \Throwable
        parent::__construct($items, 'Reduce-reduce conflict', $previous);
    }
}
