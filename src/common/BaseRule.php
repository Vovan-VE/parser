<?php
namespace VovanVE\parser\common;

/**
 * Basic definition for rule related objects
 * @package VovanVE\parser
 */
class BaseRule extends BaseObject
{
    /** @var Symbol Subject of the rule */
    protected $subject;
    /**
     * @var string|null Tag name in addition to subject.
     * Tag can be used to identify source rule(s) from the result tree node.
     * @since 1.3.0
     */
    protected $tag;
    /** @var bool Whether EOF must be found in the end of input text */
    protected $eof = false;

    /**
     * Compares two tags
     *
     * Two tags are equals if both are the same string or both are `null`.
     * When only one tag is null, it is less then other.
     * When both tags are string, string comparison is used.
     * @param string|null $a One tag to compare
     * @param string|null $b Another tag to compare
     * @return int Negative integer when tag `$a` is less then tag `$b`,
     * positive integer when tag `$a` is great then tag `$b`
     * and zero when tags are equal.
     * @since 1.3.0
     */
    public static function compareTag($a, $b)
    {
        if (null === $a) {
            return null === $b ? 0 : -1;
        }
        return null === $b ? 1 : strcmp($a, $b);
    }

    /**
     * @param Symbol $subject Subject of the rule
     * @param bool $eof Must EOF be found in the end of input text
     * @param string|null $tag [since 1.3.0] Optional tag name in addition to subject
     */
    public function __construct($subject, $eof = false, $tag = null)
    {
        $this->subject = $subject;
        $this->eof = $eof;
        if (null !== $tag) {
            $tag = (string)$tag;
            if ('' !== $tag) {
                $this->tag = $tag;
            }
        }
    }

    /**
     * Subject of the rule
     * @return Symbol
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Tag name in addition to subject if any
     * @return string|null
     * @since 1.3.0
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Whether EOF must be found in the end of input text
     * @return boolean
     */
    public function hasEofMark()
    {
        return $this->eof;
    }

    // REFACT: minimal PHP >= 7.1: protected const
    const DUMP_SPACE = ' ';
    const DUMP_KEY_OPEN = '(';
    const DUMP_KEY_CLOSE = ')';
    const DUMP_ARROW = '::=';
    const DUMP_EOF = '<eof>';

    /**
     * @return string
     */
    public function __toString()
    {
        $out = $this->subject;

        if (null !== $this->tag) {
            $out .= self::DUMP_KEY_OPEN . $this->tag . self::DUMP_KEY_CLOSE;
        }

        $out .= self::DUMP_SPACE . self::DUMP_ARROW
            . self::DUMP_SPACE . $this->toStringContent();
        if ($this->eof) {
            $out .= self::DUMP_SPACE . self::DUMP_EOF;
        }
        return $out;
    }

    /**
     * Dump rule content as string for debug purpose
     * @return string
     */
    protected function toStringContent()
    {
        return '';
    }
}
