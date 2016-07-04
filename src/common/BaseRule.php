<?php
namespace VovanVE\parser\common;

class BaseRule extends BaseObject
{
    /** @var Symbol */
    protected $subject;
    /** @var bool Can EOF be in the end */
    protected $eof = false;

    /**
     * @param Symbol $subject
     * @param bool $eof
     */
    public function __construct($subject, $eof = false)
    {
        $this->subject = $subject;
        $this->eof = $eof;
    }

    /**
     * @return Symbol
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return boolean
     */
    public function hasEofMark()
    {
        return $this->eof;
    }

    const DUMP_SPACE = ' ';
    const DUMP_ARROW = '::=';
    const DUMP_EOF = '<eof>';

    /**
     * @return string
     */
    public function __toString()
    {
        $out = $this->subject . self::DUMP_SPACE . self::DUMP_ARROW
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
