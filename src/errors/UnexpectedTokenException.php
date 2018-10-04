<?php
namespace VovanVE\parser\errors;

use VovanVE\parser\SyntaxException;

/**
 * Unexpected token found where other tokens are expected in the given position
 * @package VovanVE\parser
 * @since 1.6.0
 */
class UnexpectedTokenException extends SyntaxException
{
    /** @var string */
    protected $found;
    /** @var string[] */
    protected $expected;

    public function __construct($found, array $expected, $offset, \Exception $previous = null)
    {
        $this->found = $found;
        $this->expected = $expected;

        if (count($expected) >= 2) {
            $last = array_pop($expected);
            $expected[count($expected) - 1] .= " or $last";
        }

        parent::__construct(
            'Unexpected ' . $found
            . ($expected ? '; expected: ' . join(', ', $expected) : ''),
            $offset,
            $previous
        );
    }

    /**
     * @return string
     */
    public function getFound()
    {
        return $this->found;
    }

    /**
     * @return string[]
     */
    public function getExpected()
    {
        return $this->expected;
    }
}
