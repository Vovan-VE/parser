<?php
namespace VovanVE\parser\actions;

/**
 * Class AbortParsingException
 * @package VovanVE\parser
 * @since 1.7.0
 */
class AbortParsingException extends ActionAbortException
{
    /** @var int|null */
    protected $offset;

    /**
     * AbortParsingException constructor.
     * @param string $message
     * @param int|null $offset Offset in the input text to point error at
     * @param \Exception|null $previous
     */
    public function __construct($message = "", $offset = null, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->offset = $offset;
    }

    /**
     * @return int|null
     */
    public function getOffset()
    {
        return $this->offset;
    }
}
