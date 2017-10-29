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
     * Values are either callable (since 1.3.0) or command name (since 1.4.0).
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
     * `Name` or `Name(tag)`. Values are either callable (since 1.3.0)
     * or command name (since 1.4.0).
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
     * Run action for a node and apply its result to node
     *
     * Runs action for a node if action is defined. Returns whether action was found
     * and its result was made. Result of the action will be applied with `make()`
     * only when it is not `null`. So you can call `make()` manually inside action handler
     * under some conditions.
     *
     * To apply `null` to node with actions the only way is to call `make(null)` inside
     * action handler. It should not be a problem since default `made()` value on nodes
     * is `null`.
     * @param TreeNodeInterface $node Subject node
     * @return bool|null Returns `null` when no action defined. Returns `false` when
     * action result `null` and so it was not applied. Returns `true` wher action result
     * was not `null` and it was applied.
     * @since 1.4.0
     */
    public function applyToNode($node)
    {
        $action = $this->getAction($node);
        if (null === $action) {
            return null;
        }
        $value = $this->runActionHandler($action, $node);
        if (null === $value) {
            return false;
        }
        $node->make($value);
        return true;
    }

    /**
     * Run action for a node and returns its result
     *
     * Runs action for a node if action is defined. Returns result of the action.
     * @param TreeNodeInterface $node Subject node
     * @return mixed Value returned from action or `null` if no action.
     */
    public function runForNode($node)
    {
        // REFACT: unused internally - deprecate or improve public interface

        $action = $this->getAction($node);
        if (null === $action) {
            return null;
        }

        return $this->runActionHandler($action, $node);
    }

    /**
     * Get action handler for node
     * @param TreeNodeInterface $node Subject node
     * @return callable|string|null Action handler or `null`.
     * @since 1.4.0
     */
    private function getAction($node)
    {
        $name = $node->getNodeName();
        $tag = $node->getNodeTag();
        if (null !== $tag) {
            $name .= '(' . $tag . ')';
        }

        // REFACT: minimal PHP >= 7.0: $var ?? null
        if (!isset($this->actions[$name])) {
            return null;
        }

        return $this->actions[$name];
    }

    /**
     * Run action and return its result
     * @param callable|string $action Action from the map
     * @param TreeNodeInterface $node Subject node
     * @return mixed Result of the action call
     * @since 1.4.0
     */
    private function runActionHandler($action, $node)
    {
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
