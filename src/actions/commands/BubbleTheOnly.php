<?php
namespace VovanVE\parser\actions\commands;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * Command implementation to bubble up the only child's made value
 *
 * This class is used internally with actions.
 * @package VovanVE\parser
 */
class BubbleTheOnly extends BaseObject implements CommandInterface
{
    /**
     * @param TreeNodeInterface $node Subject node to made for
     * @return mixed Value of the only child's made value
     */
    public static function runForNode(TreeNodeInterface $node)
    {
        if (1 !== $node->getChildrenCount()) {
            throw new \LogicException('Action can be applied to node with the only child');
        }
        return $node->getChild(0)->made();
    }
}
