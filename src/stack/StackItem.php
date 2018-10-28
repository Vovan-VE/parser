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
    /** @var int State index */
    public $state;
    /** @var TreeNodeInterface Node */
    public $node;
}
