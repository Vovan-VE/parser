<?php

use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$grammar = TextLoader::createGrammar(<<<'_END'
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
div   : "/"
add   : /[-+]/
id    : /[a-z_][a-z_\d]*+/
-ws   : /\s+/
-mod  : "i"
_END
);

$parser = new Parser(new Lexer, $grammar);
$tree = $parser->parse('A * 2  +1 ');
echo $tree->dumpAsString();
