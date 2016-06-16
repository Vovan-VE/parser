<?php
namespace VovanVE\parser\tree;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

class NonTerminal extends BaseObject implements TreeNodeInterface
{
    /** @var string */
    public $name;
    /** @var TreeNodeInterface[] */
    public $children;

    /**
     * @inheritdoc
     */
    public function getNodeName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString($indent = '', $last = true)
    {
        $out = $indent . ' `- ' . $this->name . PHP_EOL;
        $sub_indent = $indent . ($last ? '    ' : ' |  ');
        $last_i = count($this->children) - 1;
        foreach ($this->children as $i => $child) {
            $out .= $child->dumpAsString($sub_indent, $i === $last_i);
        }
        return $out;
    }
}
