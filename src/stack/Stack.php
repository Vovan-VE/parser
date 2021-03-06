<?php
namespace VovanVE\parser\stack;

use VovanVE\parser\actions\AbortParsingException;
use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\table\Table;
use VovanVE\parser\table\TableRow;
use VovanVE\parser\tree\NonTerminal;

/**
 * Stack of parsed tokens
 *
 * Stack to represent parser state according to [LR(0)](https://en.wikipedia.org/wiki/LR_parser)
 * terms. Stack is used internally on every parsing session.
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class Stack extends BaseObject
{
    /** @var Table Parser's states table */
    private $table;

    /** @var StackItem[] Items in the stack. Last item in the array is topmost item. */
    private $items;
    /** @var int Current state index from states table */
    private $stateIndex;
    /** @var TableRow Current state row from states table */
    private $stateRow;
    /** @var ActionsMap|null Actions to apply to nodes on its construction */
    private $actions;

    /**
     * @param Table $table Parser states table
     * @param ActionsMap|null $actions [since 1.3.0] Actions map to apply to nodes
     */
    public function __construct(Table $table, ?ActionsMap $actions = null)
    {
        $this->table = $table;
        $this->stateIndex = 0;
        $this->stateRow = $table->getRow(0);
        $this->actions = $actions;
    }

    /**
     * Current state index from states table
     * @return int
     */
    public function getStateIndex(): int
    {
        return $this->stateIndex;
    }

    /**
     * Current state row from states table
     * @return TableRow
     */
    public function getStateRow(): TableRow
    {
        return $this->stateRow;
    }

    /**
     * Perform a Shift of a node into the stack
     * @param TreeNodeInterface $node Node to add into the stack
     * @param int $stateIndex Next state index to switch to
     * @throws AbortParsingException
     */
    public function shift(TreeNodeInterface $node, int $stateIndex): void
    {
        $item = new StackItem();
        $item->state = $stateIndex;
        $item->node = $node;

        if ($this->actions) {
            $this->actions->applyToNode($node);
        }

        $this->items[] = $item;
        $this->stateIndex = $stateIndex;
        $this->stateRow = $this->table->getRow($stateIndex);
    }

    /**
     * Perform the Reduce
     * @throws NoReduceException No rule to reduce by in the current state
     * @throws InternalException Internal package error
     * @throws AbortParsingException
     */
    public function reduce(): void
    {
        $rule = $this->stateRow->reduceRule;
        if (!$rule) {
            throw new NoReduceException();
        }
        $reduce_count = count($rule->getDefinition());
        $total_count = count($this->items);
        if ($total_count < $reduce_count) {
            throw new InternalException('Not enough items in stack');
        }
        $nodes = [];
        $offset = null;
        $reduce_items = array_slice($this->items, -$reduce_count);
        foreach ($rule->getDefinition() as $i => $symbol) {
            $item = $reduce_items[$i];
            if ($item->node->getNodeName() !== $symbol->getName()) {
                throw new InternalException('Unexpected stack content');
            }

            if (!$symbol->isHidden()) {
                $nodes[] = $item->node;
            }

            if (null === $offset) {
                $offset = $item->node->getOffset();
            }
        }

        $base_state_index = ($total_count > $reduce_count)
            ? $this->items[$total_count - 1 - $reduce_count]->state
            : 0;
        $base_state_row = $this->table->getRow($base_state_index);

        $new_symbol_name = $rule->getSubject()->getName();

        $new_node = new NonTerminal($new_symbol_name, $nodes, $rule->getTag(), $offset);

        $goto = $base_state_row->gotoSwitches;
        if (!isset($goto[$new_symbol_name])) {
            throw new InternalException('No required state in GOTO table');
        }
        $next_state = $goto[$new_symbol_name];

        array_splice($this->items, -$reduce_count);
        $this->shift($new_node, $next_state);
    }

    /**
     * End work and get final tree
     * @return TreeNodeInterface
     * @throws InternalException Internal package error
     */
    public function done(): TreeNodeInterface
    {
        if (1 !== count($this->items)) {
            throw new InternalException('Unexpected stack content');
        }

        return $this->items[0]->node;
    }
}
