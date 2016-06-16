<?php
namespace VovanVE\parser;

class SyntaxException extends \RuntimeException
{
    private $offset;

    public function __construct($message, $offset, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->offset = (int)$offset;
    }

    final public function getOffset()
    {
        return $this->offset;
    }
}
