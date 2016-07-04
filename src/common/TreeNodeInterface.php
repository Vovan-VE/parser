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
     * @param string $indent
     * @param bool $last
     * @return string
     */
    public function dumpAsString($indent = '', $last = true);
}
