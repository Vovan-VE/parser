<?php
namespace VovanVE\parser\actions;

use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;

/**
 * Actions map to deal with only made values
 *
 * Action handler for this class will receive only children' `made()` values
 * instead of tree nodes.
 *
 * *   `Token` handler will take one argument to accept token's string content:
 *     `function (string $content): mixed`
 * *   Other nodes will take arguments in count of non-hidden children nodes.
 *     Values are children' `made()` values:
 *     `function (mixed ...$value): mixed`
 *
 * This lets you to write actions in more simple way like so:
 *
 * ```php
 * $actions = new ActionsMadeMap([
 *     'int' => function ($content) { return (int)$content; },
 *
 *     'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
 *     'Value(neg)' => function ($v) { return -$v; },
 *
 *     'Product(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
 *     'Product(mul)' => function ($a, $b) { return $a * $b; },
 *     'Product(div)' => function ($a, $b) { return $a / $b; },
 *
 *     'Sum(P)' => Parser::ACTION_BUBBLE_THE_ONLY,
 *     'Sum(add)' => function ($a, $b) { return $a + $b; },
 *     'Sum(sub)' => function ($a, $b) { return $a - $b; },
 * ]);
 * ```
 *
 * See this example in package README.
 *
 * @package VovanVE\parser
 * @since 1.5.0
 */
class ActionsMadeMap extends ActionsMap
{
    /**
     * @var bool Whether to prune children nodes after parent node does `made()`
     *
     * In some case children nodes become useless after a parent node did `made()`.
     * This feature is recommended for complex grammar to save memory.
     */
    public $prune = false;

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
        if ($node instanceof Token) {
            try {
                return $action($node->getContent());
            } catch (AbortParsingException $e) {
                if (null === $e->getOffset()) {
                    throw new AbortParsingException($e->getMessage(), $node->getOffset(), $e);
                }
                throw $e;
            } catch (AbortNodeException $e) {
                throw new AbortParsingException($e->getMessage(), $node->getOffset(), $e);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Action failure in `{$this::buildActionName($node)}`", 0, $e);
            }
        }

        $args = [];
        foreach ($node->getChildren() as $child) {
            $args[] = $child->made();
        }

        try {
             $result = $action(...$args);
        } catch (AbortNodeException $e) {
            throw new AbortParsingException(
                $e->getMessage(),
                $node->getChild($e->getNodeIndex() - 1)->getOffset(),
                $e
            );
        } catch (AbortParsingException $e) {
            if (null === $e->getOffset()) {
                throw new AbortParsingException($e->getMessage(), $node->getOffset(), $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Action failure in `{$this::buildActionName($node)}`", 0, $e);
        }

        if ($this->prune) {
            $node->prune();
        }

        return $result;
    }
}
