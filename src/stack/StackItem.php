<?php
namespace VovanVE\parser\stack;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * Parser stack item
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class StackItem extends BaseObject
{
    /** @var integer State index */
    public $state;
    /** @var TreeNodeInterface Node */
    public $node;
    /**
     * @var boolean Whether the node must be hidden from the resulting tree
     * @since 1.3.2
     */
    public $isHidden = false;
}
