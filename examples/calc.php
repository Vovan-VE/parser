<?php

use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\LexerBuilder;
use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$grammar = Grammar::create(<<<'_END'
    Goal        : Sum $
    Sum(add)    : Sum add Product
    Sum(sub)    : Sum sub Product
    Sum(P)      : Product
    Product(mul): Product mul Value
    Product(div): Product div Value
    Product(V)  : Value
    Value       : int
_END
);

$lexer = (new LexerBuilder)
    ->terminals([
        'int' => '\\d+',
        '.add' => '\\+',
        '.sub' => '-',
        '.mul' => '\\*',
        '.div' => '\\/',
    ])
    ->whitespaces(['\\s+'])
    ->modifiers('i')
    ->create();

$actions = [
    'int' => function (Token $t) {
        return (int) $t->getContent();
    },
    'Value' => function ($v, TreeNodeInterface $int) {
        return $int->made();
    },
    'Product(V)' => function ($p, TreeNodeInterface $v) {
        return $v->made();
    },
    'Product(mul)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
        return $a->made() * $b->made();
    },
    'Product(div)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
        return $a->made() / $b->made();
    },
    'Sum(P)' => function ($s, TreeNodeInterface $p) {
        return $p->made();
    },
    'Sum(add)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
        return $a->made() + $b->made();
    },
    'Sum(sub)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
        return $a->made() - $b->made();
    },
];

$parser = new Parser($lexer, $grammar);

$tree = $parser->parse('23 * 2 - 4', $actions);

echo 'Result is ', $tree->made(), PHP_EOL;
echo 'Tree:', PHP_EOL;
echo $tree->dumpAsString();
