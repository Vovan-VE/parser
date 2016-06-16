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
