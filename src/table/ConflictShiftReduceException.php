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
     * @param \Throwable $previous Previous exception
     */
    public function __construct(array $items, \Throwable $previous = null)
    {
        parent::__construct($items, 'Shift-reduce conflict', $previous);
    }
}
