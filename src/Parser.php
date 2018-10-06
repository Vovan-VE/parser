<?php
namespace VovanVE\parser;

use VovanVE\parser\actions\AbortParsingException;
use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\errors\AbortedException;
use VovanVE\parser\errors\UnexpectedInputAfterEndException;
use VovanVE\parser\errors\UnexpectedTokenException;
use VovanVE\parser\errors\UnknownCharacterException;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\stack\NoReduceException;
use VovanVE\parser\stack\Stack;
use VovanVE\parser\stack\StateException;
use VovanVE\parser\table\Table;

/**
 * Main parser class
 *
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class Parser extends BaseObject
{
    /** Shortcut action to bubble up the only child's made value */
    const ACTION_BUBBLE_THE_ONLY = ActionsMap::DO_BUBBLE_THE_ONLY;

    /** @var Lexer lexer to parse input text into tokens stream */
    protected $lexer;
    /** @var Table States table */
    protected $table;

    /**
     * @param Lexer $lexer lexer to parse input text into tokens stream
     * @param Grammar|string $grammar Grammar object or text. Text will be passed to `Grammar::create()`
     * @see Grammar::create()
     */
    public function __construct($lexer, $grammar)
    {
        if (!$lexer instanceof Lexer) {
            throw new \InvalidArgumentException(
                'Argument $lexer must be ' . Lexer::class
            );
        }

        if (is_string($grammar)) {
            $grammar = Grammar::create($grammar);
        } elseif (!$grammar instanceof Grammar) {
            throw new \InvalidArgumentException(
                'Argument $grammar must be string or ' . Grammar::class
            );
        }

        $this->lexer = $lexer
            ->fixed($grammar->getFixed())
            ->terminals($grammar->getRegExpMap())
            ->inline($grammar->getInlines());

        $this->table = new Table($grammar);
    }

    /**
     * Parse input text into nodes tree
     *
     * Actions map can be used to evaluate node values on tree construction phase.
     * Without action you will need dive into a tree manually.
     *
     * Key in actions map is a subject node name with optional tag in parenses
     * without spaces (`Foo` or `Foo(bar)`). Action will be applied to nodes with
     * given name and tag. So `Foo` would be applied either to terminals `Foo` or
     * Non-terminals `Foo` built by rules without a tag. And so `Foo(bar)` would be applied
     * to non-terminals `Foo` built by rules with tag `(bar)` (since terminals cannot have tags).
     *
     * Value in actions map is either shortcut action name (since 1.4.0) or a callable
     * with signature (since 1.3.0):
     *
     * ```php
     * function (TreeNodeInterface $subject, TreeNodeInterface ...$children): mixed`
     * ```
     *
     * Arguments is not required to be variadic `...$children`. It would be much better
     * to declare exact amount of arguments with respect to corresponding rule(s).
     *
     * Return value of a callback (unless it's `null`) will be used in `make()` method
     * on a node. Callback itself should to use children nodes' `made()` values to
     * evaluate the result. To apply `null` value to a node you need to call `make(null)`
     * manually in action callback, but it is not necessary since default `made()` value is `null`.
     *
     * Since 1.5.0 an instance of `ActionsMap` can be passed directly to `$actions`.
     * @param string $input Input text to parse
     * @param ActionsMap|callable[]|string[] $actions [since 1.3.0] Actions map.
     * Accepts `ActionsMap` since 1.5.0.
     * @return TreeNodeInterface
     * @throws UnknownCharacterException
     * @throws UnexpectedTokenException
     * @throws UnexpectedInputAfterEndException
     * @throws AbortedException
     * @see \VovanVE\parser\actions\ActionsMadeMap
     */
    public function parse($input, $actions = [])
    {
        if ($actions instanceof ActionsMap) {
            $actions_map = $actions;
        } elseif ($actions) {
            $actions_map = new ActionsMap($actions);
        } else {
            $actions_map = null;
        }

        $stack = new Stack($this->table, $actions_map);

        $pos = 0;
        $token = null;
        try {
            while (true) {
                while ($stack->getStateRow()->isReduceOnly()) {
                    $stack->reduce();
                }

                $expected_terms = array_keys($stack->getStateRow()->terminalActions);
                $match = $this->lexer->parseOne($input, $pos, $expected_terms);

                if ($match) {
                    /** @var Token $token */
                    $token = $match->token;
                    $pos = $match->nextOffset;
                    $symbol_name = $token->getType();
                } else {
                    $token = null;
                    $symbol_name = null;
                }

                while (true) {
                    if ($token) {
                        $terminal_actions = $stack->getStateRow()->terminalActions;
                        if (isset($terminal_actions[$symbol_name])) {
                            $stack->shift(
                                $token,
                                $terminal_actions[$symbol_name],
                                $token->isHidden()
                            );
                            goto NEXT_SYMBOL;
                        }
                    }
                    if ($stack->getStateRow()->eofAction) {
                        if ($token) {
                            throw new UnexpectedInputAfterEndException(
                                $this->dumpTokenForError($token),
                                $token->getOffset()
                            );
                        }
                        goto DONE;
                    }
                    $stack->reduce();
                }

                NEXT_SYMBOL:
            }
            DONE:
        } catch (AbortParsingException $e) {
            throw new AbortedException($e->getMessage(), $e->getOffset());
        } catch (NoReduceException $e) {
            // This unexpected reduce (no rule to reduce) may happen only
            // when current terminal is not expected. So, some terminals
            // are expected here.

            $expected_terminals = [];
            foreach ($stack->getStateRow()->terminalActions as $name => $_) {
                $expected_terminals[] = Symbol::dumpType($name);
            }

            throw new UnexpectedTokenException(
                $this->dumpTokenForError($token),
                $expected_terminals,
                $token ? $token->getOffset() : strlen($input)
            );
        } catch (StateException $e) {
            throw new InternalException('Unexpected state fail', 0, $e);
        }
        $tokens_gen = null;

        return $stack->done();
    }

    /**
     * @param Token|null $token
     * @return string
     */
    private function dumpTokenForError($token)
    {
        if (!$token) {
            return '<EOF>';
        }
        if ($token->isInline()) {
            return Symbol::dumpInline($token->getType());
        }
        return '<' . $token->getType() . ' "' . $token->getContent() . '">';
    }
}
