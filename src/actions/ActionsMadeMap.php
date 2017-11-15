<?php
namespace VovanVE\parser\actions;

use VovanVE\parser\common\Token;

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
     * @inheritdoc
     */
    protected function runActionHandler($action, $node)
    {
        if ($node instanceof Token) {
            return call_user_func($action, $node->getContent());
        }

        $args = [];
        foreach ($node->getChildren() as $child) {
            $args[] = $child->made();
        }

        // REFACT: minimal PHP >= 7.0:
        // return $action(...$args);
        return call_user_func_array($action, $args);
    }
}
