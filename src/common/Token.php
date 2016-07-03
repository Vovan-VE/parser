<?php
namespace VovanVE\parser\common;

class Token extends BaseObject implements TreeNodeInterface
{
    /** @var string */
    private $type;
    /** @var string */
    private $content;
    /** @var array */
    private $match;
    /** @var integer */
    private $offset;

    public function __construct($type, $content, $match = null, $offset = null)
    {
        $this->type = $type;
        $this->content = $content;
        $this->match = $match;
        $this->offset = $offset;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return array
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * @return integer
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @inheritdoc
     */
    public function getNodeName()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString($indent = '', $last = true)
    {
        return $indent . ' `- ' . $this->type . ' <' . $this->content . '>' . PHP_EOL;
    }
}
