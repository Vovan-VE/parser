<?php
/* @formatter:off */

use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$grammar = TextLoader::createGrammar(<<<'TEXT'
G       : Nodes $
Nodes(L): Nodes Node
Nodes(i): Node

Node    : "{{" Sum "}}"
Node    : text

Sum(add): Sum "+" Value
Sum(sub): Sum "-" Value
Sum(V)  : Value
Value   : int

int     : /\d++/
text    : /[^{}]++/

TEXT
);

$lexer = new Lexer;

$parser = new Parser($lexer, $grammar);

$result = $parser->parse("997foo{{42+37-23}}000", new ActionsMadeMap([
    'Nodes(L)' => function ($a, $b) { return $a . $b; },
    'Nodes(i)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Node' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Sum(add)' => function ($a, $b) { return $a + $b; },
    'Sum(sub)' => function ($a, $b) { return $a - $b; },
    'Sum(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
    'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
    'int' => function ($s) { return $s; },
    'text' => function ($s) { return $s; },
]));
var_dump($result->made());
echo $result->dumpAsString();
