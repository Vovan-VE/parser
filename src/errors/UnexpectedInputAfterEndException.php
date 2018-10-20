<?php
namespace VovanVE\parser\errors;

use VovanVE\parser\SyntaxException;

/**
 * Expected EOF but got something in the given position
 * @package VovanVE\parser
 * @since 1.6.0
 */
class UnexpectedInputAfterEndException extends SyntaxException
{
    /** @var string */
    protected $token;

    /**
     * UnexpectedInputAfterEndException constructor.
     * @param string $token
     * @param int $offset
     * @param \Throwable|null $previous
     */
    public function __construct(string $token, int $offset, \Throwable $previous = null)
    {
        parent::__construct("Expected <EOF> but got $token", $offset, $previous);
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }
}
