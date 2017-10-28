<?php
namespace VovanVE\parser\actions;

use VovanVE\parser\actions\commands\BubbleTheOnly;
use VovanVE\parser\actions\commands\CommandInterface;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * Internal utility to call actions for nodes
 *
 * Since class definition is not so useful to declare set of actions like Perl6 does,
 * actions set is declaring with array. This class is used internally to handle actions set.
 * @since 1.3.0
 */
class ActionsMap extends BaseObject
{
    /** Command name to bubble up the only's child `made()` value */
    const DO_BUBBLE_THE_ONLY = '#bubble';

    /**
     * @var callable[]|string[]
     * Holds source set of actions. Keys are node reference like `Name` or `Name(tag)`.
     * Values are either callable or command name.
     */
    private $actions = [];

    /**
     * @var array Commands declaration. Key is command name, value is class name which
     * must implement `CommandInterface` class
     * @uses CommandInterface
     * @refact minimal PHP >= 7 const array isset
     */
    private static $COMMANDS = [
        self::DO_BUBBLE_THE_ONLY => BubbleTheOnly::class,
    ];

    /**
     * @param callable[]|string[] $actions Map of actions. Keys are node reference like
     * `Name` or `Name(tag)`. Values are either callable or command name.
     * Node reference must be the same as declared in corresponding rule in grammar.
     * That is action for node `Foo` will not apply to nodes created by `Foo(tag)`
     * rule. Last might be improved in future versions.
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
     * Run action for a node if any
     *
     * Runs action for a node if action is defined.
     * @param TreeNodeInterface $node Subject node
     * @return mixed Value returned from action or `null`.
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

        // REFACT: minimal PHP >= 7.0:
        // return $action($node, ...$node->getChildren());
        // REFACT: minimal PHP >= 5.6:
        // return call_user_func($action, $node, ...$node->getChildren());
        $args = $node->getChildren();
        array_unshift($args, $node);
        return call_user_func_array($action, $args);
    }
}
