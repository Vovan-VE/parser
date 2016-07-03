<?php
namespace VovanVE\parser\common;

class Token extends BaseObject implements TreeNodeInterface
{
    /** @var string */
    public $type;
    /** @var string */
    public $content;
    /** @var array */
    public $match;
    /** @var integer */
    public $offset;

    public function __construct($type, $content, $match = null, $offset = null)
    {
        $this->type = $type;
        $this->content = $content;
        $this->match = $match;
        $this->offset = $offset;
    }

    /**
     * @inheritdoc
     */
    final public function getNodeName()
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
