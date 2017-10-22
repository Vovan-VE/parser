LR(0) parser
============

[![Latest Stable Version](https://poser.pugx.org/vovan-ve/lr0-parser/v/stable)](https://packagist.org/packages/vovan-ve/lr0-parser)
[![Build Status](https://travis-ci.org/Vovan-VE/parser.svg)](https://travis-ci.org/Vovan-VE/parser)

This package contains [LR(0) parser][lr-parser.wiki] with parsing table
generator to work with custom LR(0) grammar.

Synopsis
--------

See also following example in [examples/](examples/).

```php
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\LexerBuilder;
use VovanVE\parser\Parser;

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
        'int'  => '\\d+',
        '.add' => '\\+',
        '.sub' => '-',
        '.mul' => '\\*',
        '.div' => '\\/',
    ])
    ->whitespaces(['\\s+'])
    //->modifiers('i')
    ->create();

$actions = [
    'int' => function ($t) {
        return (int) $t->getContent();
    },
    'Value' => function ($v, $int) {
        return $int->made();
    },
    'Product(V)' => function ($p, $v) {
        return $v->made();
    },
    'Product(mul)' => function ($p, $a, $b) {
        return $a->made() * $b->made();
    },
    'Product(div)' => function ($p, $a, $b) {
        return $a->made() / $b->made();
    },
    'Sum(P)' => function ($s, $p) {
        return $p->made();
    },
    'Sum(add)' => function ($s, $a, $b) {
        return $a->made() + $b->made();
    },
    'Sum(sub)' => function ($s, $a, $b) {
        return $a->made() - $b->made();
    },
];

$parser = new Parser($lexer, $grammar);

$tree = $parser->parse('23 * 2 - 4', $actions);

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
         |       |       `- int <23>
         |       `- mul <*>
         |       `- Value
         |           `- int <2>
         `- sub <->
         `- Product(V)
             `- Value
                 `- int <4>

Description
-----------

This package contains:

*   Lexer to parse input string for tokens. Lexer is configurable by regexps.
*   Parsing table generator to work with any LR(0) grammar. Input grammar does
    initialize from plain text.
*   LR(0) parser itself. It parse input string for AST using the table.

This package was made just to apply the theory in practice. It may be easily be
used for small grammars to parse small source codes.

Installation
------------

Install through [composer][]:

    composer require vovan-ve/lr0-parser

or add to `require` section in your composer.json:

    "vovan-ve/lr0-parser": "~1.3.0"

Theory
------

[LR parser][lr-parser.wiki].

License
-------

This package is under [MIT License][mit]


[composer]: http://getcomposer.org/
[lr-parser.wiki]: https://en.wikipedia.org/wiki/LR_parser
[mit]: https://opensource.org/licenses/MIT
