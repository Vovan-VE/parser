<?php
namespace VovanVE\parser\tests\unit\table;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\table\ItemSet;
use VovanVE\parser\table\Table;
use VovanVE\parser\table\TableRow;
use VovanVE\parser\tests\helpers\BaseTestCase;

/**
 * @link https://en.wikipedia.org/wiki/LR_parser#Table_construction
 */
class TableTest extends BaseTestCase
{
    const EXPECTED_STATES_COUNT = 9;

    /**
     * @return Grammar
     */
    public function testCreateGrammar(): Grammar
    {
        $grammar = TextLoader::createGrammar(<<<'_GRAMMAR'
S: E $
E: E mul B
E: E add B
E: B
B: zero
B: one

add : "+"
mul : "*"
zero: "0"
one : "1"
_GRAMMAR
        );
        $this->assertInstanceOf(Grammar::class, $grammar);
        return $grammar;
    }

    /**
     * @param Grammar $grammar
     * @return TableRow[]
     * @depends testCreateGrammar
     */
    public function testCreateTable(Grammar $grammar): array
    {
        $table = new Table($grammar);

        $rows = $table->getRows();
        $this->assertInternalType('array', $rows);
        $this->assertCount(self::EXPECTED_STATES_COUNT, $rows);
        $this->assertContainsOnlyInstancesOf(TableRow::class, $rows);

        $states = $table->getStates();
        $this->assertInternalType('array', $states);
        $this->assertCount(self::EXPECTED_STATES_COUNT, $states);
        $this->assertContainsOnlyInstancesOf(ItemSet::class, $states);

        return $rows;
    }

    /**
     * @param TableRow[] $rows
     * @return int[]
     * @depends testCreateTable
     */
    public function testRow0(array $rows): array
    {
        $row = $rows[0];

        $this->assertNull($row->eofAction);
        $this->assertNull($row->reduceRule);

        $terminals = $row->terminalActions;
        $this->assertInternalType('array', $terminals);
        $this->assertCount(2, $terminals);
        $this->assertArrayHasKey('zero', $terminals);
        $this->assertArrayHasKey('one', $terminals);

        $non_terminals = $row->gotoSwitches;
        $this->assertInternalType('array', $non_terminals);
        $this->assertCount(2, $terminals);
        $this->assertArrayHasKey('E', $non_terminals);
        $this->assertArrayHasKey('B', $non_terminals);

        $next_states_index = array_merge($terminals, $non_terminals);
        foreach ($next_states_index as $symbol => $state_index) {
            $this->assertInternalType('int', $state_index, "[$symbol]");
            $this->assertGreaterThan(0, $state_index, "[$symbol]");
            $this->assertLessThan(self::EXPECTED_STATES_COUNT, $state_index, "[$symbol]");
        }
        $nums = array_unique($next_states_index, SORT_NUMERIC);
        $this->assertCount(4, $nums);

        return $next_states_index;
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map
     * @depends testCreateTable
     * @depends testRow0
     */
    public function testRowBIsZero(array $rows, array $map)
    {
        $row = $rows[$map['zero']];
        $expectRule = new Rule(new Symbol('B'), [new Symbol('zero', true)]);

        $this->_testReduceOnly($row, $expectRule);
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map
     * @depends testCreateTable
     * @depends testRow0
     */
    public function testRowBIsOne(array $rows, array $map)
    {
        $row = $rows[$map['one']];
        $expectRule = new Rule(new Symbol('B'), [new Symbol('one', true)]);

        $this->_testReduceOnly($row, $expectRule);
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map
     * @depends testCreateTable
     * @depends testRow0
     */
    public function testRowEIsB(array $rows, array $map)
    {
        $row = $rows[$map['B']];
        $expectRule = new Rule(new Symbol('E'), [new Symbol('B')]);

        $this->_testReduceOnly($row, $expectRule);
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map
     * @return int[]
     * @depends testCreateTable
     * @depends testRow0
     */
    public function testRowSIsEEof(array $rows, array $map): array
    {
        $row = $rows[$map['E']];

        $this->assertTrue($row->eofAction);

        $terminals = $row->terminalActions;
        $this->assertInternalType('array', $terminals);
        $this->assertCount(2, $terminals);
        $this->assertArrayHasKey('mul', $terminals);
        $this->assertArrayHasKey('add', $terminals);
        foreach ($terminals as $symbol => $index) {
            $this->assertInternalType('int', $index, "[$symbol]");
            $this->assertGreaterThan(0, $index, "[$symbol]");
            $this->assertLessThan(self::EXPECTED_STATES_COUNT, $index, "[$symbol]");
            $this->assertNotContains($index, $map, "[$symbol]");
        }

        $this->assertInternalType('array', $row->gotoSwitches);
        $this->assertCount(0, $row->gotoSwitches);

        $rule = $row->reduceRule;
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(0, Rule::compare(
            $rule,
            new Rule(new Symbol('S'), [new Symbol('E')], true)
        ));

        return $terminals;
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map0
     * @param int[] $mapE
     * @return int
     * @depends testCreateTable
     * @depends testRow0
     * @depends testRowSIsEEof
     */
    public function testRowEIsEMulWantB(array $rows, array $map0, array $mapE): int
    {
        $row = $rows[$mapE['mul']];

        $this->assertNull($row->eofAction);

        $terminals = $row->terminalActions;
        $this->assertInternalType('array', $terminals);
        $this->assertCount(2, $terminals);
        foreach (['zero', 'one'] as $symbol) {
            $this->assertArrayHasKey($symbol, $terminals);
            $this->assertEquals($map0[$symbol], $terminals[$symbol], "[$symbol]");
        }

        $non_terminals = $row->gotoSwitches;
        $this->assertInternalType('array', $non_terminals);
        $this->assertCount(1, $non_terminals);
        $this->assertArrayHasKey('B', $non_terminals);
        $next_state_b = $non_terminals['B'];
        $this->assertGreaterThan(0, $next_state_b);
        $this->assertLessThan(self::EXPECTED_STATES_COUNT, $next_state_b);
        $this->assertNotContains($next_state_b, $map0);
        $this->assertNotContains($next_state_b, $mapE);

        $this->assertNull($row->reduceRule);

        return $next_state_b;
    }

    /**
     * @param TableRow[] $rows
     * @param int $stateB
     * @depends testCreateTable
     * @depends testRowEIsEMulWantB
     */
    public function testRowEIsEMulB(array $rows, int $stateB)
    {
        $row = $rows[$stateB];

        $this->assertNull($row->eofAction);

        foreach (
            [
                'terminalActions' => $row->terminalActions,
                'gotoSwitches' => $row->gotoSwitches,
            ]
            as $field => $value
        ) {
            $this->assertInternalType('array', $value, $field);
            $this->assertCount(0, $value, $field);
        }

        $rule = $row->reduceRule;
        $this->assertInstanceOf(Rule::class, $rule);
        $E = new Symbol('E');
        $B = new Symbol('B');
        $mul = new Symbol('mul', true);
        $this->assertEquals(0, Rule::compare($rule, new Rule($E, [$E, $mul, $B])));
    }

    /**
     * @param TableRow[] $rows
     * @param int[] $map0
     * @param int[] $mapE
     * @return int
     * @depends testCreateTable
     * @depends testRow0
     * @depends testRowSIsEEof
     */
    public function testRowEIsEAddWantB(array $rows, array $map0, array $mapE): int
    {
        $row = $rows[$mapE['add']];

        $this->assertNull($row->eofAction);

        $terminals = $row->terminalActions;
        $this->assertInternalType('array', $terminals);
        $this->assertCount(2, $terminals);
        foreach (['zero', 'one'] as $symbol) {
            $this->assertArrayHasKey($symbol, $terminals);
            $this->assertEquals($map0[$symbol], $terminals[$symbol], "[$symbol]");
        }

        $non_terminals = $row->gotoSwitches;
        $this->assertInternalType('array', $non_terminals);
        $this->assertCount(1, $non_terminals);
        $this->assertArrayHasKey('B', $non_terminals);
        $next_state_b = $non_terminals['B'];
        $this->assertGreaterThan(0, $next_state_b);
        $this->assertLessThan(self::EXPECTED_STATES_COUNT, $next_state_b);
        $this->assertNotContains($next_state_b, $map0);
        $this->assertNotContains($next_state_b, $mapE);

        $this->assertNull($row->reduceRule);

        return $next_state_b;
    }

    /**
     * @param TableRow[] $rows
     * @param int $stateB
     * @depends testCreateTable
     * @depends testRowEIsEAddWantB
     */
    public function testRowEIsEAddB(array $rows, int $stateB)
    {
        $row = $rows[$stateB];

        $this->assertNull($row->eofAction);

        foreach (
            [
                'terminalActions' => $row->terminalActions,
                'gotoSwitches' => $row->gotoSwitches,
            ]
            as $field => $value
        ) {
            $this->assertInternalType('array', $value, $field);
            $this->assertCount(0, $value, $field);
        }

        $rule = $row->reduceRule;
        $this->assertInstanceOf(Rule::class, $rule);
        $E = new Symbol('E');
        $B = new Symbol('B');
        $add = new Symbol('add', true);
        $this->assertEquals(0, Rule::compare($rule, new Rule($E, [$E, $add, $B])));
    }

    /**
     * @param TableRow $row
     * @param Rule $expectRule
     */
    private function _testReduceOnly(TableRow $row, Rule $expectRule)
    {
        $this->assertNull($row->eofAction);

        $this->assertInternalType('array', $row->terminalActions);
        $this->assertCount(0, $row->terminalActions);

        $this->assertInternalType('array', $row->gotoSwitches);
        $this->assertCount(0, $row->gotoSwitches);

        $rule = $row->reduceRule;
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(0, Rule::compare($rule, $expectRule));
    }
}
