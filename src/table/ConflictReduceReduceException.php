<?php
namespace VovanVE\parser\table;

class ConflictReduceReduceException extends BadStateException
{
    /**
     * @param Item[] $items
     * @param \Exception $previous
     */
    public function __construct(array $items, \Exception $previous = null)
    {
        parent::__construct($items, 'Reduce-reduce conflict', $previous);
    }
}
