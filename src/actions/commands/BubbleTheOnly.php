<?php
namespace VovanVE\parser\actions\commands;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

class BubbleTheOnly extends BaseObject implements CommandInterface
{
    public static function runForNode(TreeNodeInterface $node)
    {
        if (1 !== $node->getChildrenCount()) {
            throw new \LogicException('Action can be applied to node with the only child');
        }
        return $node->getChild(0)->made();
    }
}
