<?php
namespace VovanVE\parser\actions;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * @since 1.3.0
 */
class ActionsMap extends BaseObject
{
    /** @var callable[] */
    private $actions = [];

    /**
     * @param callable[] $actions
     */
    public function __construct(array $actions)
    {
        foreach ($actions as $action) {
            if (!is_callable($action)) {
                throw new \InvalidArgumentException('All actions must be a valid callable');
            }
        }

        $this->actions = $actions;
    }

    /**
     * @param TreeNodeInterface $node
     * @return mixed
     */
    public function runForNode($node)
    {
        $name = $node->getNodeName();
        $tag = $node->getNodeTag();
        if (null !== $tag) {
            $name .= '(' . $tag . ')';
        }

        if (!isset($this->actions[$name])) {
            return null;
        }
        // REFACT: minimal PHP >= 5.6:
        // return $this->actions[$name]($node, ...$node->getChildren());
        $args = $node->getChildren();
        array_unshift($args, $node);
        return call_user_func_array($this->actions[$name], $args);
    }
}
