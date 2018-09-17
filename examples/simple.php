<?php
/* @formatter:off */

use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$lexer = (new Lexer)
    ->fixed([
        'div' => '/',
    ])
    ->terminals([
        'id' => '[a-z_][a-z_\\d]*+',
        'add' => '[-+]',
    ])
    ->whitespaces(['\\s+'])
    ->modifiers('i');

$grammar = Grammar::create(<<<'_END'
E     : S $
S(add): S add P
S     : P
P(mul): P mul V
P(div): P div V
P     : V
V(int): int
V(var): id
int   : /\d++/
mul   : "*"
_END
);

$parser = new Parser($lexer, $grammar);
$tree = $parser->parse('A * 2  +1 ');
echo $tree->dumpAsString();
