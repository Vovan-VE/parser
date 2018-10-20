LR(0) parser Change Log
=======================

2.0.0 (dev)
-----

*   **BC break**:
    *   Minimum PHP is 7.1 now.
    *   Deleted stuff which was deprecated before:
        *   class `\VovanVE\parser\LexerBuilder`;
        *   exception `\VovanVE\parser\actions\ActionAbortException`;
        *   exception `\VovanVE\parser\lexer\ParseException`;
        *   method `\VovanVE\parser\grammar\Grammar::create()`;
        *   method `\VovanVE\parser\actions\ActionsMap::runForNode()`;
        *   method `\VovanVE\parser\lexer\Lexer::extend()`;
        *   all arguments to `\VovanVE\parser\lexer\Lexer` constructor;
        *   constant `\VovanVE\parser\common\TreeNodeInterface::DUMP_INDENT`;
        *   constant `\VovanVE\parser\grammar\Grammar::RE_RULE_LINE`;
        *   constant `\VovanVE\parser\grammar\Grammar::RE_INPUT_RULE`;
        *   constant `\VovanVE\parser\grammar\Grammar::RE_RULE_DEF_ITEM`;
        *   constant `\VovanVE\parser\grammar\Grammar::RE_RULE_DEF_REGEXP`;
    *   Dropped support for deprecated features:
        *   `Lexer` terminals will not accept anonymous inline tokens, so use
            inlines directly.
        *   Property `\VovanVE\parser\tree\NonTerminal::$name` become private,
            so use getter.
        *   Property `\VovanVE\parser\tree\NonTerminal::$children` become private,
            so use getter.
    *   Internal constants hidden from public API:
        *   `\VovanVE\parser\common\BaseRule::DUMP_*`;
        *   `\VovanVE\parser\grammar\loaders\TextLoader::RE_*`;
        *   `\VovanVE\parser\lexer\Lexer::DUMP_NEAR_LENGTH`;
        *   `\VovanVE\parser\lexer\Lexer::RE_*`;
        *   `\VovanVE\parser\table\Item::DUMP_MARKER`;
        *   `\VovanVE\parser\table\ItemSet::DUMP_*`

1.7.0
-----

This is the last minor version of 1.* branch. This version will prepare you to
future upgrade to next 2.0 version.

*   Deprecated: setup parsing process with `Lexer`. Now `Grammar` can do everything
    that `Lexer` does, but with new array/JSON grammar. The `Lexer` class either will
    become internal, will be removed or will be "eaten" by `Grammar` class in next
    major version 2.0.
*   Deprecated: exception `\VovanVE\parser\actions\ActionAbortException` - use
    new exceptions `\VovanVE\parser\actions\AbortParsingException` or
    `\VovanVE\parser\actions\AbortNodeException` instead.
*   Deprecated: method `\VovanVE\parser\grammar\Grammar::create()` - use
    `\VovanVE\parser\grammar\loaders\TextLoader::createGrammar()` instead.
*   Add: `Grammar` now can do everything that `Lexer` does, but temporarily
    in ugly way. Just use new array/JSON grammar to setup `Grammar` object.
*   Add: exception `\VovanVE\parser\actions\AbortNodeException` to abort
    parsing from actions pointing to offset of the given node in source text.
*   Add: exception `\VovanVE\parser\actions\AbortParsingException` to abort
    parsing from actions pointing to given offset in source text.
*   Add: method `\VovanVE\parser\common\TreeNodeInterface::getOffset()` to get node
    offset in input text. _This would be BC break change, but why do you ever need to
    implement this interface yourself?_
*   Add: 4th argument `$offset` to `\VovanVE\parser\tree\NonTerminal` constructor.
*   Add: ability to export/load `Grammar` object to/from array or JSON.
*   Notice: Text grammar should not be used for production anymore, but it
    can be used for dev phase. New array/JSON grammar should be used for production.
    Automatic conversion to JSON can be done with CLI tool:
    ```sh
    $ vendor/bin/grammar-text-to-json < grammar.txt > grammar.json
    ```

1.6.0
-----

*   Deprecated: `\VovanVE\parser\lexer\ParseException` now replaced with
    `\VovanVE\parser\errors\UnknownCharacterException`.
*   Enh: `\VovanVE\parser\SyntaxException` divided into more detailed exceptions
    to be explained to end-user easily.

1.5.3
-----

*   Fix: Preferred RegExp terminals under `Lexer::parseOne()` did not sort in specified order
    and could not give correct expected match in specific cases.

1.5.2
-----

*   Enh: Wrap and rethrow unexpected exceptions and errors from action handlers to show action target
    for debug purpose.

1.5.1
-----

*   Enh: Enumerate expected tokens in `VovanVE\parser\SyntaxException` in more human-friendly
    manner like `X, Y or Z` instead of `X, Y, Z`.

1.5.0
-----

*   BC break: Non-terminal in `VovanVE\parser\grammar\Grammar` with the only definition
    which consists of the only inline token like `name: "text"` can be converted from rule
    into fixed terminal. This may cause problems if you rely that the rules subjects are
    always non-terminal or rely either on rules count or specific rules.
*   Add: Terminals can be defined with RegExp literal in grammar text.
*   Add: Named fixed tokens in `VovanVE\parser\lexer\Lexer`.
*   Add: Separate definition for inline tokens in `VovanVE\parser\lexer\Lexer`.
*   Add: Class `VovanVE\parser\actions\ActionsMadeMap` to let actions to accept only
    children' `made()` values instead of nodes. It also allows you optionally to prune
    tree just after node `made()` is done.
*   Add: Method `VovanVE\parser\lexer\Lexer::parseOne()` to deal with complex grammar.
*   Add method `\VovanVE\parser\table\TableRow::isReduceOnly()` to check if table row
    is for reduce only.
*   Add exception `\VovanVE\parser\actions\ActionAbortException` which should be thrown from
    action handler to be converted to `VovanVE\parser\SyntaxException`.
*   Enh: RegExp validation throws exception from `VovanVE\parser\lexer\Lexer`.
*   Enh: Enum expected tokens in `VovanVE\parser\SyntaxException` in case of unexpected token
    in parsing text.
*   Fix: Cannot use inline/fixed spaces and `#` with `/x` modifier.
*   Fix: Could nor work with valid deterministic grammar when unexpected token could match
    without respect to current expectations.
*   Deprecated: `VovanVE\parser\lexer\Lexer` constructor arguments should be avoided
    in favor to corresponding extending method.
*   Deprecated: Method `VovanVE\parser\lexer\Lexer::extend()` - use specific corresponding
    extending method.
*   Deprecated: Method `\VovanVE\parser\actions\ActionsMap::runForNode()` since it is unused
    internally and does not cause tree recursion.
*   Deprecated: Defining inline tokens within regexp terminals in `VovanVE\parser\lexer\Lexer`.

1.4.2
-----

*   Fix: Hidden non-terminal completely broken is grammar.

1.4.1
-----

*   Fix: Inline semicolor in grammar `A: ";"` cause grammar syntax error.

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

*   Fix: broken example code in README.


1.0.0
-----

*   First release.
