Migration guide
---------------

### to 2.0 from 1.x

*   At least PHP 7.1 is required now.
*   `\VovanVE\parser\lexer\Lexer` does not accept anonymous terminals,
    so use inlines directly.
*   Deleted class `\VovanVE\parser\LexerBuilder`.
*   Deleted exception `\VovanVE\parser\actions\ActionAbortException`.
    Use `\VovanVE\parser\actions\AbortNodeException`
    or `\VovanVE\parser\actions\AbortParsingException`.
*   Deleted exception `\VovanVE\parser\lexer\ParseException`
    since it was replaced with
    `\VovanVE\parser\errors\UnknownCharacterException`.
*   Deleted method `\VovanVE\parser\grammar\Grammar::create()`.
    Use `\VovanVE\parser\grammar\loaders\TextLoader::createGrammar()`
    instead.
*   Deleted method `\VovanVE\parser\actions\ActionsMap::runForNode()`
    since it is unused internally and does not cause tree recursion.
*   Deleted method `\VovanVE\parser\lexer\Lexer::extend()`
    since there are alternative methods.
*   Deleted all arguments to `\VovanVE\parser\lexer\Lexer` constructor
    since there are methods to do same things.
*   Deleted constant `\VovanVE\parser\common\TreeNodeInterface::DUMP_INDENT`
    since it is unused.
*   Deleted constant `\VovanVE\parser\grammar\Grammar::RE_RULE_LINE`
    since it is unused.
*   Deleted constant `\VovanVE\parser\grammar\Grammar::RE_INPUT_RULE`
    since it is unused.
*   Deleted constant `\VovanVE\parser\grammar\Grammar::RE_RULE_DEF_ITEM`
    since it is unused.
*   Deleted constant `\VovanVE\parser\grammar\Grammar::RE_RULE_DEF_REGEXP`
    since it is unused.
*   Property `\VovanVE\parser\tree\NonTerminal::$name` become private,
    so use getter.
*   Property `\VovanVE\parser\tree\NonTerminal::$children` become private,
    so use getter.
*   Constants `\VovanVE\parser\common\BaseRule::DUMP_*` became protected.
*   Constants `\VovanVE\parser\grammar\loaders\TextLoader::RE_*` became private.
*   Constants `\VovanVE\parser\lexer\Lexer::RE_*` became private.
*   Constant `\VovanVE\parser\lexer\Lexer::DUMP_NEAR_LENGTH` became private.
*   Constant `\VovanVE\parser\table\Item::DUMP_MARKER` became private.
*   Constants `\VovanVE\parser\table\ItemSet::DUMP_PREFIX_MAIN_*` became private.
*   Most of methods now have type hinting. If you inherit anything, you should
    to check it.
*   Properties `$rows` and `$states` of `\VovanVE\parser\table\Table`
    became private, so use getters.
