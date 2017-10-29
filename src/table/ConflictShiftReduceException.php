<?php
namespace VovanVE\parser\table;

/**
 * Shift-reduce conflict
 *
 * This means that Shift and Reduce are both applicable in the current state.
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class ConflictShiftReduceException extends BadStateException
{
    /**
     * @param Item[] $items Items of state which cause the error
     * @param \Exception $previous Previous exception
     */
    public function __construct(array $items, \Exception $previous = null)
    {
        // REFACT: minimal PHP >= 7.0: use \Throwable
        parent::__construct($items, 'Shift-reduce conflict', $previous);
    }
}
