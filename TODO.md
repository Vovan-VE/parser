TODO
----

*   Declarations for all terminals in a grammar.
    *   Finally move Lexer to internals.
*   Charset control for input text.
*   Introduce actions with only children' `made()` values as arguments.
    Either as alternative actions map or via action name like `Foo*`.

    ```php
    'Sum(add)' => function ($a, $b) { return $a + $b; },
    ```

    Can be done for all actions by options in `[0]` element. Or can be done
    in same manner for all next elements after `[int => options]` element.
