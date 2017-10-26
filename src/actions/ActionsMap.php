<?php
namespace VovanVE\parser\actions;

use VovanVE\parser\actions\commands\BubbleTheOnly;
use VovanVE\parser\actions\commands\CommandInterface;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * @since 1.3.0
 */
class ActionsMap extends BaseObject
{
    const DO_BUBBLE_THE_ONLY = '#bubble';

    /** @var callable[]|string[] */
    private $actions = [];

    /**
     * @var array
     * @refact minimal PHP >= 7 const array isset
     */
    private static $COMMANDS = [
        self::DO_BUBBLE_THE_ONLY => BubbleTheOnly::class,
    ];

    /**
     * @param callable[] $actions
     */
    public function __construct(array $actions)
    {
        foreach ($actions as $action) {
            if (
                !(
                    is_callable($action)
                    || is_string($action) && isset(self::$COMMANDS[$action])
                )
            ) {
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

        $action = $this->actions[$name];

        if (is_string($action) && isset(self::$COMMANDS[$action])) {
            /** @var CommandInterface $class Just a class name, not an instance */
            $class = self::$COMMANDS[$action];
            return $class::runForNode($node);
        }

        // REFACT: minimal PHP >= 5.6:
        // return $this->actions[$name]($node, ...$node->getChildren());
        $args = $node->getChildren();
        array_unshift($args, $node);
        return call_user_func_array($action, $args);
    }
}
