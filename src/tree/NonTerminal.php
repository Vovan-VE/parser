<?php
namespace VovanVE\parser\tree;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * Non-terminal node of the resulting tree
 * @package VovanVE\parser
 */
class NonTerminal extends BaseObject implements TreeNodeInterface
{
    /** @var string Symbol name from the grammar */
    public $name;
    /** @var string|null Tag from corresponding grammar rule if one was defined */
    private $tag;
    /** @var TreeNodeInterface[] Children nodes */
    private $children;
    /** @var mixed Value evaluated with actions */
    private $made;
    /** @var int Offset in the source text */
    private $offset;

    /**
     * @param string $name Symbol name from the grammar
     * @param TreeNodeInterface[] $children Children nodes
     * @param string|null $tag Tag from corresponding grammar rule if one was defined
     * @param int $offset >= 1.7.0
     * @since 1.3.0
     */
    public function __construct($name, $children, $tag = null, $offset = null)
    {
        $this->name = $name;
        $this->children = $children;
        if (null !== $tag) {
            $tag = (string)$tag;
            if ('' !== $tag) {
                $this->tag = $tag;
            }
        }
        $this->offset = $offset;
    }

    /**
     * @inheritdoc
     */
    public function getNodeName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getNodeTag()
    {
        return $this->tag;
    }

    /**
     * Position of the token in the input text
     * @return integer
     * @since 1.7.0
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildrenCount()
    {
        return count($this->children);
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getChild($index)
    {
        if ($index >= 0 && $index < count($this->children)) {
            return $this->children[$index];
        }
        throw new \OutOfBoundsException('No children');
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @inheritdoc
     * @since 1.2.0
     */
    public function areChildrenMatch($nodeNames)
    {
        if (count($nodeNames) !== $this->getChildrenCount()) {
            return false;
        }

        $children = $this->children;
        $index = 0;
        foreach ($nodeNames as $name) {
            if ($name !== $children[$index]->getNodeName()) {
                return false;
            }
            ++$index;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString($indent = '', $last = true)
    {
        $out = $indent . ' `- ' . $this->name;

        if (null !== $this->tag) {
            $out .= '(' . $this->tag . ')';
        }

        $out .= PHP_EOL;
        $sub_indent = $indent . ($last ? '    ' : ' |  ');
        $last_i = count($this->children) - 1;
        foreach ($this->children as $i => $child) {
            $out .= $child->dumpAsString($sub_indent, $i === $last_i);
        }
        return $out;
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

    /**
     * @inheritdoc
     * @since 2.0.0
     */
    public function prune()
    {
        $this->children = [];
    }
}
