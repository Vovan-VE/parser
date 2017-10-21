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
}
