Migration guide
---------------

### 2.0 ← 1.7

Text grammar now is for dev purpose by design. When your text grammar
is ready, you need to generate an array or JSON grammar for production
purpose with CLI tool. Text grammar loader uses Parser+Grammar
internally.

*   At least PHP 7.1 is required now.
*   Grammar now MUST to define all mentioned terminals. Undefined terminals
    will cause a grammar to fail loading.
*   Deleted argument #1 `$lexer` from `VovanVE\parser\Parser` constructor.
    Grammar now is Lexer. You don't need Lexer itself anymore but dev or test
    purpose.
*   Removed concept of hidden tokens in Lexer's point if view. Now only
    Grammar is responsive for hidden symbols.
*   `VovanVE\parser\lexer\Lexer` does not accept anonymous terminals,
    so use inlines directly.
*   Deleted class `VovanVE\parser\LexerBuilder`.
*   Deleted exception `VovanVE\parser\actions\ActionAbortException`.
    Use `VovanVE\parser\actions\AbortNodeException`
    or `VovanVE\parser\actions\AbortParsingException`.
*   Deleted exception `VovanVE\parser\lexer\ParseException`
    since it was replaced with
    `VovanVE\parser\errors\UnknownCharacterException`.
*   Deleted method `VovanVE\parser\grammar\Grammar::create()`.
    Use `VovanVE\parser\grammar\loaders\TextLoader::createGrammar()`
    instead.
*   Deleted method `VovanVE\parser\actions\ActionsMap::runForNode()`
    since it is unused internally and does not cause tree recursion.
*   Deleted method `VovanVE\parser\lexer\Lexer::extend()`
    since there are alternative methods.
*   Deleted all arguments to `VovanVE\parser\lexer\Lexer` constructor
    since there are methods to do same things.
*   Deleted constant `VovanVE\parser\common\TreeNodeInterface::DUMP_INDENT`
    since it is unused.
*   Deleted constant `VovanVE\parser\grammar\Grammar::RE_RULE_LINE`
    since it is unused.
*   Deleted constant `VovanVE\parser\grammar\Grammar::RE_INPUT_RULE`
    since it is unused.
*   Deleted constant `VovanVE\parser\grammar\Grammar::RE_RULE_DEF_ITEM`
    since it is unused.
*   Deleted constant `VovanVE\parser\grammar\Grammar::RE_RULE_DEF_REGEXP`
    since it is unused.
*   Deleted property `VovanVE\parser\stack\StackItem::$isHidden`
    since it became unused.
*   Deleted argument #3 `$isHidden` to `VovanVE\parser\stack\Stack::shift()`.
*   Deleted argument #5 `$isHidden` to `VovanVE\parser\common\Token` constructor.
*   Deleted method `VovanVE\parser\common\Token::isHidden()`.
*   Property `VovanVE\parser\tree\NonTerminal::$name` become private,
    so use getter.
*   Property `VovanVE\parser\tree\NonTerminal::$children` become private,
    so use getter.
*   Constants `VovanVE\parser\common\BaseRule::DUMP_*` became protected.
*   Constants `VovanVE\parser\grammar\loaders\TextLoader::RE_*` became private.
*   Constants `VovanVE\parser\lexer\Lexer::RE_*` became private.
*   Constant `VovanVE\parser\lexer\Lexer::DUMP_NEAR_LENGTH` became private.
*   Constant `VovanVE\parser\table\Item::DUMP_MARKER` became private.
*   Constants `VovanVE\parser\table\ItemSet::DUMP_PREFIX_MAIN_*` became private.
*   Most of methods now have type hinting. If you inherit anything, you should
    to check it.
*   Properties `$rows` and `$states` of `VovanVE\parser\table\Table`
    became private, so use getters.
*   Property `$name` of `VovanVE\parser\tree\NonTerminal` became private,
    so use getters.
*   Property `$items` of `VovanVE\parser\table\ItemSet` became private,
    so use getters.
*   Properties `$passed` and `$further` of `VovanVE\parser\table\Item`
    became private, so use getters.
