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
 * actions set is declaring with array. This class is used as default actions map
 * if you pass array of handlers in `\VovanVE\parser\Parser::parse()`.
 *
 * Actions map accepts array of callable as actions handlers.
 * Key in actions map is a subject node name with optional tag in parenses
 * without spaces (`Foo` or `Foo(bar)`). Action will be applied to nodes with
 * given name and tag. So `Foo` would be applied either to terminals `Foo` or
 * Non-terminals `Foo` built by rules without a tag. And so `Foo(bar)` would be applied
 * to non-terminals `Foo` built by rules with tag `(bar)` (since terminals cannot have tags).
 *
 * Value in actions map is either shortcut action name (since 1.4.0) or a callable
 * with signature (since 1.3.0):
 *
 * ```php
 * function (TreeNodeInterface $subject, TreeNodeInterface ...$children): mixed`
 * ```
 *
 * Arguments is not required to be variadic `...$children`. It would be much better
 * to declare exact amount of arguments with respect to corresponding rule(s).
 *
 * Return value of a callback (unless it's `null`) will be used in `make()` method
 * on a node. Callback itself should to use children nodes' `made()` values to
 * evaluate the result. To apply `null` value to a node you need to call `make(null)`
 * manually in action callback, but it is not necessary since default `made()` value is `null`.
 *
 * Handler may throw `ActionAbortException` or `AbortParsingException`. Parser will convert it into `SyntaxException.`
 * @package VovanVE\parser
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
     * Commands declaration. Key is command name, value is class name which
     * must implement `CommandInterface` class
     * @uses CommandInterface
     */
    private const COMMANDS = [
        self::DO_BUBBLE_THE_ONLY => BubbleTheOnly::class,
    ];

    /**
     * @param TreeNodeInterface $node
     * @return string
     * @since 1.5.2
     */
    protected static function buildActionName($node)
    {
        $name = $node->getNodeName();
        $tag = $node->getNodeTag();
        if (null !== $tag) {
            $name .= '(' . $tag . ')';
        }
        return $name;
    }

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
                    || is_string($action) && isset(self::COMMANDS[$action])
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
     * action result `null` and so it was not applied. Returns `true` when action result
     * was not `null` and it was applied.
     * @throws AbortParsingException
     * @since 1.4.0
     */
    public function applyToNode($node)
    {
        $action = $this->getAction($node);
        if (null === $action) {
            return null;
        }
        $value = $this->runAction($action, $node);
        if (null === $value) {
            return false;
        }
        $node->make($value);
        return true;
    }

    /**
     * Get action handler for node
     * @param TreeNodeInterface $node Subject node
     * @return callable|string|null Action handler or `null`.
     * @since 1.4.0
     */
    private function getAction($node)
    {
        $name = self::buildActionName($node);

        return $this->actions[$name] ?? null;
    }

    /**
     * Run action and return its result
     * @param callable|string $action Action from the map
     * @param TreeNodeInterface $node Subject node
     * @return mixed Result of the action call
     * @throws AbortParsingException
     * @since 1.5.0
     */
    private function runAction($action, $node)
    {
        if (is_string($action) && isset(self::COMMANDS[$action])) {
            /** @var CommandInterface $class Just a class name, not an instance */
            $class = self::COMMANDS[$action];
            return $class::runForNode($node);
        }

        return $this->runActionHandler($action, $node);
    }

    /**
     * Run action handler and return its result
     * @param callable $action Action callback from the map
     * @param TreeNodeInterface $node Subject node
     * @return mixed Result of the action call
     * @since 1.5.0
     * @throws AbortParsingException
     */
    protected function runActionHandler($action, $node)
    {
        $children = $node->getChildren();
        try {
            return $action($node, ...$children);
        } catch (AbortNodeException $e) {
            $child = $children[$e->getNodeIndex() - 1] ?? null;
            throw new AbortParsingException($e->getMessage(), $child ? $child->getOffset() : null, $e);
        } catch (AbortParsingException $e) {
            if (null === $e->getOffset()) {
                throw new AbortParsingException($e->getMessage(), $node->getOffset(), $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Action failure in `{$this::buildActionName($node)}`", 0, $e);
        }
    }
}
