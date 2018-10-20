<?php
namespace VovanVE\parser\actions;

/**
 * Class AbortNodeException
 * @package VovanVE\parser
 * @since 1.7.0
 */
class AbortNodeException extends \Exception
{
    /** @var int */
    private $nodeIndex;

    /**
     * AbortNodeException constructor.
     * @param string $message
     * @param int $nodeIndex Node index starting from 1 to point error to
     * @param \Throwable|null $previous
     */
    public function __construct($message = "", $nodeIndex = 0, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->nodeIndex = $nodeIndex;
    }

    /**
     * @return int
     */
    public function getNodeIndex()
    {
        return $this->nodeIndex;
    }
}
