<?php

use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface as INode;
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
_END
);

$lexer = (new Lexer)
    ->terminals([
        'int' => '\\d+',
    ])
    //->modifiers('i')
    ->whitespaces(['\\s+']);

$actions = [
    'int' => function (Token $t) {
        return (int)$t->getContent();
    },

    'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Value(neg)' => function ($v, INode $n) {
        return -$n->made();
    },

    'Product(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Product(mul)' => function ($p, INode $a, INode $b) {
        return $a->made() * $b->made();
    },
    'Product(div)' => function ($p, INode $a, INode $b) {
        return $a->made() / $b->made();
    },

    'Sum(P)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Sum(add)' => function ($s, INode $a, INode $b) {
        return $a->made() + $b->made();
    },
    'Sum(sub)' => function ($s, INode $a, INode $b) {
        return $a->made() - $b->made();
    },
];

$parser = new Parser($lexer, $grammar);

$tree = $parser->parse('2 * (-10 + 33) - 4', $actions);

echo 'Result is ', $tree->made(), PHP_EOL;
echo 'Tree:', PHP_EOL;
echo $tree->dumpAsString();
