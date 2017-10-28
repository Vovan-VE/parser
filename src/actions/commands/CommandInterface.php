<?php
namespace VovanVE\parser\actions\commands;

use VovanVE\parser\common\TreeNodeInterface;

/**
 * Interface for command with is shortcut action
 *
 * Commands are internal implementation to shortcut some common simple actions.
 * @package VovanVE\parser
 */
interface CommandInterface
{
    /**
     * Run action for a node
     * @param TreeNodeInterface $node Subject node to made
     * @return mixed Result to be `make()` for subject node
     */
    public static function runForNode(TreeNodeInterface $node);
}
