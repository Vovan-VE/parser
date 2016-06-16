<?php
namespace VovanVE\parser\stack;

class NoReduceException extends StateException
{
    public function __construct(\Exception $previous = null)
    {
        parent::__construct('No rule to reduce', 0, $previous);
    }
}
