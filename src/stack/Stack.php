<?php
namespace VovanVE\parser\stack;

use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\table\Table;
use VovanVE\parser\table\TableRow;
use VovanVE\parser\tree\NonTerminal;

class Stack extends BaseObject
{
    /** @var Table */
    private $table;

    /** @var StackItem[] */
    private $items;
    /** @var integer */
    private $stateIndex;
    /** @var TableRow */
    private $stateRow;
    /** @var ActionsMap|null */
    private $actions;

    /**
     * @param Table $table
     * @param ActionsMap|null $actions [since 1.3.0]
     */
    public function __construct($table, $actions = null)
    {
        $this->table = $table;
        $this->stateIndex = 0;
        $this->stateRow = $table->rows[0];
        $this->actions = $actions;
    }

    /**
     * @return integer
     */
    public function getStateIndex()
    {
        return $this->stateIndex;
    }

    /**
     * @return TableRow
     */
    public function getStateRow()
    {
        return $this->stateRow;
    }

    /**
     * @param TreeNodeInterface $node
     * @param integer $stateIndex
     * @param bool $isHidden [since 1.3.2]
     */
    public function shift($node, $stateIndex, $isHidden = false)
    {
        $item = new StackItem();
        $item->state = $stateIndex;
        $item->node = $node;
        $item->isHidden = $isHidden;

        if ($this->actions) {
            $node->make($this->actions->runForNode($node));
        }

        $this->items[] = $item;
        $this->stateIndex = $stateIndex;
        $this->stateRow = $this->table->rows[$stateIndex];
    }

    public function reduce()
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
        $reduce_items = array_slice($this->items, -$reduce_count);
        foreach ($rule->getDefinition() as $i => $symbol) {
            $item = $reduce_items[$i];
            if ($item->node->getNodeName() !== $symbol->getName()) {
                throw new InternalException('Unexpected stack content');
            }
            if (!($symbol->isHidden() || $item->isHidden)) {
                $nodes[] = $item->node;
            }
        }

        $base_state_index = ($total_count > $reduce_count)
            ? $this->items[$total_count - 1 - $reduce_count]->state
            : 0;
        $base_state_row = $this->table->rows[$base_state_index];

        $new_symbol_name = $rule->getSubject()->getName();

        $new_node = new NonTerminal($new_symbol_name, $nodes, $rule->getTag());

        $goto = $base_state_row->gotoSwitches;
        if (!isset($goto[$new_symbol_name])) {
            throw new InternalException('No required state in GOTO table');
        }
        $next_state = $goto[$new_symbol_name];

        array_splice($this->items, -$reduce_count);
        $this->shift($new_node, $next_state);
    }

    /**
     * @return TreeNodeInterface
     */
    public function done()
    {
        if (1 !== count($this->items)) {
            throw new InternalException('Unexpected stack content');
        }

        return $this->items[0]->node;
    }
}
