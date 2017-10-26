<?php
namespace VovanVE\parser\actions\commands;

use VovanVE\parser\common\TreeNodeInterface;

interface CommandInterface
{
    /**
     * @param TreeNodeInterface $node
     * @return mixed Result of `made()` from the only child
     */
    public static function runForNode(TreeNodeInterface $node);
}
