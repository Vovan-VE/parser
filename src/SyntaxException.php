<?php
namespace VovanVE\parser;

/**
 * Exception signals about syntax error in analyzing source text according to the grammar
 * @package VovanVE\parser
 */
class SyntaxException extends \RuntimeException
{
    /** @var int Position of the error in the source text */
    private $offset;

    /**
     * @param string $message Message text
     * @param int $offset Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct($message, $offset, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->offset = (int)$offset;
    }

    /**
     * Position of the error in the source text
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }
}
