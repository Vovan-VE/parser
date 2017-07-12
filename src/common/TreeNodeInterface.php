<?php
namespace VovanVE\parser\common;

interface TreeNodeInterface
{
    const DUMP_INDENT = '    ';

    /**
     * @return string
     */
    public function getNodeName();

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
     * @param string $indent
     * @param bool $last
     * @return string
     */
    public function dumpAsString($indent = '', $last = true);
}
