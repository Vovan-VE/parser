<?php
namespace VovanVE\parser\common;

interface TreeNodeInterface
{
    /** @deprecated */
    const DUMP_INDENT = '    ';

    /**
     * @return string
     */
    public function getNodeName();

    /**
     * @return string|null
     * @since 1.3.0
     */
    public function getNodeTag();

    /**
     * @return integer
     * @since 1.1.0
     */
    public function getChildrenCount();

    /**
     * @param int $index Zero based
     * @return TreeNodeInterface
     * @throws \OutOfRangeException No child node with such index
     * @since 1.3.0
     */
    public function getChild($index);

    /**
     * @return TreeNodeInterface[]
     * @since 1.1.0
     */
    public function getChildren();

    /**
     * @param string[] $nodeNames
     * @return bool
     * @since 1.2.0
     */
    public function areChildrenMatch($nodeNames);

    /**
     * @param string $indent
     * @param bool $last
     * @return string
     */
    public function dumpAsString($indent = '', $last = true);

    /**
     * @param mixed $value
     * @since 1.3.0
     */
    public function make($value);

    /**
     * @return mixed
     * @since 1.3.0
     */
    public function made();
}
