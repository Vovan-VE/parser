LR(0) parser Change Log
=======================

1.4.0
-----

*   Deprecated: class `\VovanVE\parser\LexerBuilder`. Use `use VovanVE\parser\lexer\Lexer`
    constructor directly. Configuration methods of `LexerBuilder` introduced in `Lexer` to
    simplify migration, but remember that `Lexer` is immutable.
*   Add inline quoted tokens in grammar source like `Sum(add): Sum "+" Product`.
*   Add ability to omit some useless tokens from the resulting tree like `A: foo .bar baz`.
*   Add action shortcut to bubble up the only child's `made()` value.
*   Enh: Returning `null` from action handler will no longer change node's `made()` value.
*   Branch 1.3 was reverted to its last fix release and current dev branch became 1.4
    according to semver rules.

1.3.0
-----

*   BC break:
    *   New methods in `VovanVE\parser\common\TreeNodeInterface` interface:
        *   `getNodeTag()`
        *   `getChild()`
        *   `made()`
        *   `make()`
    *   Class `VovanVE\parser\tree\NonTerminal` now has constructor and it requires two arguments
        `($name, $children)`. Also there are other optional arguments.
*   Deprecated:
    *   Class constant `VovanVE\parser\common\TreeNodeInterface::DUMP_INDENT` is useless.
    *   Class `VovanVE\parser\tree\NonTerminal` will hide public properties `$name` and `$children`
        to private. So use getters.
*   New:
    *   Add actions to evaluate result on tree nodes while parsing.
    *   Add ability to mark rules with tags like `Sum(add): Sum add Product` to map actions by rule
        subject.

1.2.0
-----

*   Add: method `areChildrenMatch()` to interface `VovanVE\parser\common\TreeNodeInterface`
    with its implementations in classes `VovanVE\parser\common\Token`
    and `VovanVE\parser\tree\NonTerminal`.

1.1.0
-----

*   Add: methods `getChildren()` and `getChildrenCount()` to interface
    `VovanVE\parser\common\TreeNodeInterface` with its implementations in classes
    `VovanVE\parser\common\Token` and `VovanVE\parser\tree\NonTerminal`.


1.0.3
-----

*   Fix: composer warning about outdated composer.lock.
*   Drop HHMV support.
*   Enable PHP 7.1 for travis.


1.0.2
-----

*   Experimental changes for packagist.


1.0.1
-----

*   Fix: broken exmple code in README.


1.0.0
-----

*   First release.
