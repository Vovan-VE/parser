<?php
namespace VovanVE\parser\common;

class Token extends BaseObject implements TreeNodeInterface
{
    /** @var string */
    private $type;
    /** @var string */
    private $content;
    /** @var bool */
    private $isHidden = false;
    /** @var array */
    private $match;
    /** @var integer */
    private $offset;
    /** @var mixed */
    private $made;

    /**
     * @param string $type
     * @param string $content
     * @param null $match
     * @param null $offset
     * @param bool $isHidden [since 1.3.2]
     */
    public function __construct($type, $content, $match = null, $offset = null, $isHidden = false)
    {
        $this->type = $type;
        $this->content = $content;
        $this->match = $match;
        $this->offset = $offset;
        $this->isHidden = $isHidden;
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
     * @return bool
     * @since 1.3.2
     */
    public function isHidden()
    {
        return $this->isHidden;
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
     * @since 1.3.0
     */
    public function getNodeTag()
    {
        return null;
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildrenCount()
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getChild($index)
    {
        throw new \OutOfBoundsException('No children');
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildren()
    {
        return [];
    }

    /**
     * @param string[] $nodeNames
     * @return bool
     * @since 1.2.0
     */
    public function areChildrenMatch($nodeNames)
    {
        return [] === $nodeNames;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString($indent = '', $last = true)
    {
        return $indent . ' `- ' . $this->type . ' <' . $this->content . '>'
        . PHP_EOL;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function make($value)
    {
        $this->made = $value;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function made()
    {
        return $this->made;
    }
}
