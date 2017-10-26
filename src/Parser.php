<?php
namespace VovanVE\parser;

use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\stack\NoReduceException;
use VovanVE\parser\stack\Stack;
use VovanVE\parser\stack\StateException;
use VovanVE\parser\table\Table;

class Parser extends BaseObject
{
    const ACTION_BUBBLE_THE_ONLY = ActionsMap::DO_BUBBLE_THE_ONLY;

    /** @var Lexer */
    protected $lexer;
    /** @var Table */
    protected $table;

    /**
     * @param Lexer $lexer
     * @param Grammar|string $grammar
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

        $my_lexer = $lexer;

        $inlines = $grammar->getInlines();
        if ($inlines) {
            $my_lexer = $my_lexer->extend($inlines);
        }
        $this->lexer = $my_lexer;

        $this->table = new Table($grammar);
    }

    /**
     * @param string $input
     * @param callable[] $actions [since 1.3.0]
     * @return TreeNodeInterface
     */
    public function parse($input, $actions = [])
    {
        $tokens_gen = $this->lexer->parse($input);
        $stack = new Stack($this->table, $actions ? new ActionsMap($actions) : null);

        $eof_offset = mb_strlen($input, '8bit');
        $tokens_gen->rewind();
        $token = null;
        try {
            while (true) {
                if ($tokens_gen->valid()) {
                    /** @var Token $token */
                    $token = $tokens_gen->current();
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
                            throw new SyntaxException(
                                'Expected <EOF> but got <'
                                . $this->dumpTokenForError($token)
                                . '>',
                                $token->getOffset()
                            );
                        }
                        goto DONE;
                    }
                    $stack->reduce();
                }

                NEXT_SYMBOL:
                $tokens_gen->next();
            }
            DONE:
        } catch (NoReduceException $e) {
            // This unexpected reduce (no rule to reduce) may happen only
            // when current terminal is not expected. So, some terminals
            // are expected here.

            // TODO: what expected
            throw new SyntaxException(
                'Unexpected <' . $this->dumpTokenForError($token) . '>',
                $token ? $token->getOffset() : $eof_offset
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
        if ($token) {
            return $token->getType() . ' "' . $token->getContent() . '"';
        }
        return '<EOF>';
    }
}
