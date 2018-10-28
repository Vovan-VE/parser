<?php

use VovanVE\parser\Parser;

require dirname(__DIR__) . '/vendor/autoload.php';

$grammar = <<<'_END'
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
_END;

$parser = new Parser($grammar);
$tree = $parser->parse('A * 2  +1 ');
echo $tree->dumpAsString();
