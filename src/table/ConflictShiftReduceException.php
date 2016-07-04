<?php
namespace VovanVE\parser\table;

class ConflictShiftReduceException extends BadStateException
{
    /**
     * @param Item[] $items
     * @param \Exception $previous
     */
    public function __construct(array $items, \Exception $previous = null)
    {
        parent::__construct($items, 'Shift-reduce conflict', $previous);
    }
}
