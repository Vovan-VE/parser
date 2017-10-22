<?php
namespace VovanVE\parser\common;

class BaseRule extends BaseObject
{
    /** @var Symbol */
    protected $subject;
    /**
     * @var string|null
     * @since 1.3.0
     */
    protected $tag;
    /** @var bool Can EOF be in the end */
    protected $eof = false;

    /**
     * @param string|null $a
     * @param string|null $b
     * @return int
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
     * @param Symbol $subject
     * @param bool $eof
     * @param string|null $tag [since 1.3.0]
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
     * @return Symbol
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string|null
     * @since 1.3.0
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @return boolean
     */
    public function hasEofMark()
    {
        return $this->eof;
    }

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
     * @return string
     */
    protected function toStringContent()
    {
        return '';
    }
}
