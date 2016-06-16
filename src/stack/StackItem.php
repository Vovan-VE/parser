<?php
namespace VovanVE\parser\stack;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

class StackItem extends BaseObject
{
    /** @var integer */
    public $state;
    /** @var TreeNodeInterface */
    public $node;
}
