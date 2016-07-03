LR(0) parser
============

This package contains [LR(0) parser][lr-parser.wiki] with parsing table
generator to work with custom LR(0) grammar.

Synopsis
--------

```php
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\LexerBuilder;
use VovanVE\parser\Parser;

$grammar = Grammar::create(<<<'_END'
    Goal: Sum $
    Sum: Sum add Product
    Sum: Product
    Product: Product mul Value
    Product: Value
    Value: int
    Value: id
_END
);

$lexer = (new LexerBuilder)
    ->terminals([
        'id' => '[a-z]+',
        'int' => '\\d+',
        'add' => '[-+]',
        'mul' => '[*\\/]',
    ])
    ->whitespaces(['\\s+'])
    ->modifiers('i')
    ->create();

$tree = $parser->parse('A * 2 + 1');

echo $tree->dumpAsString();
```

Output:

     `- Sum
         `- Sum
         |   `- Product
         |       `- Product
         |       |   `- Value
         |       |       `- id <A>
         |       `- mul <*>
         |       `- Value
         |           `- int <2>
         `- add <+>
         `- Product
             `- Value
                 `- int <1>

Theory
------

[LR parser][lr-parser.wiki].


[lr-parser.wiki]: https://en.wikipedia.org/wiki/LR_parser
