LR(0) parser
============

[![Latest Stable Version](https://img.shields.io/packagist/v/vovan-ve/lr0-parser.svg)](https://packagist.org/packages/vovan-ve/lr0-parser)
[![Latest Dev Version](https://img.shields.io/packagist/vpre/vovan-ve/lr0-parser.svg)](https://packagist.org/packages/vovan-ve/lr0-parser)
[![Build Status](https://travis-ci.org/Vovan-VE/parser.svg)](https://travis-ci.org/Vovan-VE/parser)
[![License](https://poser.pugx.org/vovan-ve/lr0-parser/license)](https://packagist.org/packages/vovan-ve/lr0-parser)

This package contains [LR(0) parser][lr-parser.wiki] to parse texts according
to custom LR(0) grammar.

Synopsis
--------

See also following example in [examples/](examples/).

```php
use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;

$grammar = TextLoader::createGrammar(<<<'_END'
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

    -ws         : /\s+/
    -mod        : 'u'
_END
);

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

$parser = new Parser(new Lexer, $grammar);

$tree = $parser->parse('2 * (-10 + 33) - 4', $actions);

echo 'Result is ', $tree->made(), PHP_EOL;
echo 'Tree:', PHP_EOL;
echo $tree->dumpAsString();
```

Output:

    Result is 42
    Tree:
     `- Sum(sub)
         `- Sum(P)
         |   `- Product(mul)
         |       `- Product(V)
         |       |   `- Value
         |       |       `- int <2>
         |       `- Value
         |           `- Sum(add)
         |               `- Sum(P)
         |               |   `- Product(V)
         |               |       `- Value(neg)
         |               |           `- Value
         |               |               `- int <10>
         |               `- Product(V)
         |                   `- Value
         |                       `- int <33>
         `- Product(V)
             `- Value
                 `- int <4>

Description
-----------

This package contains:

*   Lexer to parse input string for tokens. Lexer is configurable by regexps.
*   Parsing table generator to work with any LR(0) grammar. Input grammar can
    be initialized from plain text.
*   LR(0) parser itself. It parse input string for AST using the table.

This package was made just to apply the theory in practice. It may easily be
used for small grammars to parse small source codes.

Installation
------------

Install through [composer][]:

    composer require vovan-ve/lr0-parser

or add to `require` section in your composer.json:

    "vovan-ve/lr0-parser": "~2.0.0-dev"

Theory
------

[LR parser][lr-parser.wiki].

License
-------

This package is under [MIT License][mit]


[composer]: http://getcomposer.org/
[lr-parser.wiki]: https://en.wikipedia.org/wiki/LR_parser
[mit]: https://opensource.org/licenses/MIT
