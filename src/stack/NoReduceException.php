<?php
namespace VovanVE\parser\stack;

/**
 * No rule to reduce by
 *
 * This exception is used internally by tokens stack while parsing. It happens
 * when next token is not expected in the current stack state. So, a found token
 * is not expected currently.
 * @package VovanVE\parser
 */
class NoReduceException extends StateException
{
    /**
     * @param \Exception|null $previous
     */
    public function __construct(\Exception $previous = null)
    {
        // REFACT: minimal PHP >= 7.0: use \Throwable
        parent::__construct('No rule to reduce', 0, $previous);
    }
}
