<?php
/* @formatter:off */
use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$grammar = Grammar::create(<<<'_END'
    Goal        : Sum $
    Sum(add)    : Sum "+" Product
    Sum(sub)    : Sum "-" Product
    Sum(P)      : Product
    Product(mul): Product "*" Value
    Product(div): Product "/" Value
    Product(V)  : Value
    Value(neg)  : "-" Value
    Value       : "+" Value
    Value       : "(" Sum ")"
    Value       : int
    int         : /\d+/
    %ws         : /\s+/
_END
);

$lexer = (new Lexer)
    //->modifiers('i')
    ->whitespaces(['\\s+']);

$actions = new ActionsMadeMap([
    'int' => function ($content) { return (int)$content; },

    'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Value(neg)' => function ($v) { return -$v; },

    'Product(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Product(mul)' => function ($a, $b) { return $a * $b; },
    'Product(div)' => function ($a, $b) { return $a / $b; },

    'Sum(P)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Sum(add)' => function ($a, $b) { return $a + $b; },
    'Sum(sub)' => function ($a, $b) { return $a - $b; },
]);

$parser = new Parser($lexer, $grammar);

$tree = $parser->parse('2 * (-10 + 33) - 4', $actions);

echo 'Result is ', $tree->made(), PHP_EOL;
echo 'Tree:', PHP_EOL;
echo $tree->dumpAsString();
