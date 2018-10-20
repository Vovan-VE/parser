<?php
namespace VovanVE\parser\common;

/**
 * Interface for the resulting tree node
 * @package VovanVE\parser
 */
interface TreeNodeInterface
{
    /**
     * Name of the node according to grammar
     * @return string
     */
    public function getNodeName();

    /**
     * Tag from corresponding grammar rule if one was defined
     * @return string|null
     * @since 1.3.0
     */
    public function getNodeTag();

    /**
     * Position of the node in the input text
     * @return integer
     * @since 1.7.0
     */
    public function getOffset();

    /**
     * Counts children nodes
     * @return integer
     * @since 1.1.0
     */
    public function getChildrenCount();

    /**
     * Retrieves a child by index starting from 0
     * @param int $index Zero based index
     * @return TreeNodeInterface Child node
     * @throws \OutOfRangeException No child node with such index
     * @since 1.3.0
     */
    public function getChild($index);

    /**
     * Get all children nodes
     * @return TreeNodeInterface[]
     * @since 1.1.0
     */
    public function getChildren();

    /**
     * Check if children nodes'names match with given names
     * @param string[] $nodeNames Names to test children nodes for
     * @return bool Returns `true` when all children nodes' names
     * match given names in the order.
     * @since 1.2.0
     */
    public function areChildrenMatch($nodeNames);

    /**
     * Get string representation of a tree recursively for a debug purpose
     * @param string $indent String prefix for each result line
     * @param bool $last Whether this node is the last child in parent node if any
     * @return string Text representation of the tree for debug purpose.
     * Text is mostly multiline.
     */
    public function dumpAsString($indent = '', $last = true);

    /**
     * Store a value in the node to get it back later
     *
     * This method is used with actions on parsing phase.
     * This method will be called when the node is constructing
     * from a parsing stack by rule reduction.
     *
     * Value can be retrieved back by `made()` method.
     * @param mixed $value
     * @since 1.3.0
     * @see made()
     */
    public function make($value);

    /**
     * Get a value made previously
     *
     * This method is used with actions on parsing phase.
     * This method can be called in action callback on children nodes
     * to evaluate value for a current node.
     * @return mixed Returns a value made previously by `make()` method.
     * @since 1.3.0
     * @see make()
     */
    public function made();

    /**
     * Cleanup children and content
     *
     * This method can be used after `make()` to free memory usage.
     * @return void
     * @since 2.0.0
     */
    public function prune();
}
