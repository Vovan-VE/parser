<?php
namespace VovanVE\parser\tests\unit\stack;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\common\Token;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\stack\Stack;
use VovanVE\parser\table\Item;
use VovanVE\parser\table\Table;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

/**
 * @link https://en.wikipedia.org/wiki/LR_parser#LR_parse_steps_for_example_A.2A2_.2B_1
 */
class StackTest extends BaseTestCase
{
    /**
     * @return Table
     */
    public function testInitTable(): Table
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
E: S $
S: S add P
S: P
P: P mul V
P: V
V: int
V: id
_END
        );
        $this->expectNotToPerformAssertions();
        return new Table($grammar);
    }

    /**
     * @param Table $table
     * @return int[]
     * @depends testInitTable
     */
    public function testGetStatesMap(Table $table)
    {
        $index_map = [];

        $real_states = $table->getStates();
        unset($real_states[0]);

        $E = new Symbol('E');
        $S = new Symbol('S');
        $P = new Symbol('P');
        $V = new Symbol('V');
        $id = new Symbol('id', true);
        $int = new Symbol('int', true);
        $add = new Symbol('add', true);
        $mul = new Symbol('mul', true);

        $want_items = [
            1 => new Item($E, [$S], [], true),
            2 => new Item($S, [$S, $add], [$P]),
            3 => new Item($S, [$S, $add, $P], []),
            4 => new Item($S, [$P], []),
            5 => new Item($P, [$P, $mul], [$V]),
            6 => new Item($P, [$P, $mul, $V], []),
            7 => new Item($P, [$V], []),
            8 => new Item($V, [$int], []),
            9 => new Item($V, [$id], []),
        ];

        foreach ($want_items as $want_index => $want_item) {
            foreach ($real_states as $real_index => $item_set) {
                if ($item_set->hasItem($want_item)) {
                    $index_map[$want_index] = $real_index;
                    unset($real_states[$real_index]);
                    goto NEXT_WANTED_ITEM;
                }
            }
            $this->fail("Did not found state with item <$want_item>");

            NEXT_WANTED_ITEM:
        }
        $this->assertCount(0, $real_states);

        return $index_map;
    }

    /**
     * @param Table $table
     * @return Stack
     * @depends testInitTable
     */
    public function testCreateStack(Table $table)
    {
        $this->expectNotToPerformAssertions();
        return new Stack($table);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     */
    public function testInputStep0(Stack $stack, array $indexMap)
    {
        $this->assertEquals(0, $stack->getStateIndex());
        $stack->shift(new Token('id', 'A'), $indexMap[9]);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep0
     */
    public function testInputStep1(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[9], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep1
     */
    public function testInputStep2(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[7], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep2
     */
    public function testInputStep3(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[4], $stack->getStateIndex());
        $stack->shift(new Token('mul', '*'), $indexMap[5]);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep3
     */
    public function testInputStep4(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[5], $stack->getStateIndex());
        $stack->shift(new Token('int', '2'), $indexMap[8]);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep4
     */
    public function testInputStep5(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[8], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep5
     */
    public function testInputStep6(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[6], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep6
     */
    public function testInputStep7(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[4], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep7
     */
    public function testInputStep8(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[1], $stack->getStateIndex());
        $stack->shift(new Token('add', '+'), $indexMap[2]);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep8
     */
    public function testInputStep9(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[2], $stack->getStateIndex());
        $stack->shift(new Token('int', '1'), $indexMap[8]);
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep9
     */
    public function testInputStep10(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[8], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep10
     */
    public function testInputStep11(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[7], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep11
     */
    public function testInputStep12(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[3], $stack->getStateIndex());
        $stack->reduce();
    }

    /**
     * @param Stack $stack
     * @param int[] $indexMap
     * @depends testCreateStack
     * @depends testGetStatesMap
     * @depends testInputStep12
     */
    public function testInputStep13(Stack $stack, array $indexMap)
    {
        $this->assertEquals($indexMap[1], $stack->getStateIndex());
        $tree = $stack->done();
        $this->assertInstanceOf(NonTerminal::class, $tree);
        $this->assertEquals(<<<'DUMP'
 `- S
     `- S
     |   `- P
     |       `- P
     |       |   `- V
     |       |       `- id <A>
     |       `- mul <*>
     |       `- V
     |           `- int <2>
     `- add <+>
     `- P
         `- V
             `- int <1>

DUMP
            ,
            $tree->dumpAsString()
        );
    }
}
