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
     * @param \Throwable $previous Previous exception
     */
    public function __construct(array $items, \Throwable $previous = null)
    {
        parent::__construct($items, 'Reduce-reduce conflict', $previous);
    }
}
