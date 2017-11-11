TODO
----

*   Declarations for all terminals in a grammar.
    *   Named static terminals `name: "text"` which are currently called "inline".
        Report an error when named static overlaps with anonymous inline.
    *   RegExp terminals in grammar: `int: /\d++/`.
    *   Finally move Lexer to internals.
*   Charset control for input text.
*   Introduce actions with only children' `made()` values as arguments.
    Either as alternative actions map or via action name like `Foo*`.

    ```php
    'Sum(add)' => function ($a, $b) { return $a + $b; },
    ```
