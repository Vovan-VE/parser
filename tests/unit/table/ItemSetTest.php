<?php
namespace VovanVE\parser\tests\unit\table;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\table\Item;
use VovanVE\parser\table\ItemSet;
use VovanVE\parser\tests\helpers\BaseTestCase;

/**
 * @link https://en.wikipedia.org/wiki/LR_parser#Table_construction
 */
class ItemSetTest extends BaseTestCase
{
    /**
     * @return Grammar
     */
    public function testCreateGrammar()
    {
        $grammar = Grammar::create(<<<'_GRAMMAR'
S: E $
E: E mul B
E: E add B
E: B
B: zero
B: one
_GRAMMAR
        );
        $this->assertInstanceOf(Grammar::class, $grammar);
        return $grammar;
    }

    /**
     * @param Grammar $grammar
     * @return ItemSet
     * @depends testCreateGrammar
     */
    public function testCreateFromItemsInitial($grammar)
    {
        $item = Item::createFromRule($grammar->getMainRule());
        $item_set = ItemSet::createFromItems([$item], $grammar);
        $this->assertInstanceOf(ItemSet::class, $item_set);

        $item_copy = Item::createFromRule($grammar->getMainRule());
        $item_set_copy = ItemSet::createFromItems([$item_copy], $grammar);
        $this->assertTrue($item_set->isSame($item_set_copy), 'isSame()');

        $initial_items = $item_set->getInitialItems();
        $this->assertInternalType('array', $initial_items, 'initial items');
        $this->assertCount(1, $initial_items, 'initial items');
        $this->assertContainsOnlyInstancesOf(Item::class, $initial_items, 'initial items');

        $items = $item_set->items;
        $this->assertInternalType('array', $items, 'items');
        $this->assertCount(6, $items, 'items');
        $this->assertContainsOnlyInstancesOf(Item::class, $items, 'items');

        $this->assertFalse($item_set->hasFinalItem(), 'no final item');
        $this->assertNull($item_set->getReduceRule(), 'no reduce rule');

        $S = new Symbol('S');
        $B = new Symbol('B');
        $E = new Symbol('E');
        $test_items = [
            [true, [new Item($S, [], [$E], true)]],
            [true, [new Item($S, [], [$E], true), true]],
            [true, [new Item($S, [], [$E], true), false]],
            [false, [new Item($E, [], [$B])]],
            [false, [new Item($E, [], [$B]), true]],
            [true, [new Item($E, [], [$B]), false]],
            [false, [new Item($S, [], [$E], false), false]],
            [false, [new Item($S, [], [$E], false), true]],
            [false, [new Item($S, [$E], [], true), false]],
            [false, [new Item($S, [$E], [], true), true]],
        ];
        /** @uses ItemSet::hasItem() */
        $method = [$item_set, 'hasItem'];
        foreach ($test_items as $i => list ($expect_result, $test_args)) {
            $actual = call_user_func_array($method, $test_args);
            if ($expect_result) {
                $this->assertTrue($actual, "hasItem() for test[$i]");
            } else {
                $this->assertFalse($actual, "hasItem() for test[$i]");
            }
        }

        return $item_set;
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet $initialItemSet
     * @return ItemSet[]
     * @depends testCreateGrammar
     * @depends testCreateFromItemsInitial
     */
    public function testGetNextSetsFromInitial($grammar, $initialItemSet)
    {
        $next_map = $initialItemSet->getNextSets($grammar);
        $this->assertInternalType('array', $next_map, 'next map is array');
        $this->assertCount(4, $next_map, 'next sets');
        $this->assertContainsOnlyInstancesOf(ItemSet::class, $next_map);
        foreach (['E', 'B', 'zero', 'one'] as $next_symbol) {
            $this->assertArrayHasKey($next_symbol, $next_map, "has next set for symbol <$next_symbol>");
        }
        return $next_map;
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testGetNextSetsFromInitial
     */
    public function testNextZeroFromInitial($grammar, $nextMap)
    {
        $terminalSymbolName = 'zero';
        $this->_testTerminalTheOnly($grammar, $nextMap[$terminalSymbolName], $terminalSymbolName);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testGetNextSetsFromInitial
     */
    public function testNextOneFromInitial($grammar, $nextMap)
    {
        $terminalSymbolName = 'one';
        $this->_testTerminalTheOnly($grammar, $nextMap[$terminalSymbolName], $terminalSymbolName);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet $itemSet
     * @param string $terminalSymbolName
     */
    private function _testTerminalTheOnly($grammar, $itemSet, $terminalSymbolName)
    {
        $subject = new Symbol('B');
        $definition = [new Symbol($terminalSymbolName, true)];

        $this->_testSimpleReducing($grammar, $itemSet, $subject, $definition);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testGetNextSetsFromInitial
     */
    public function testNextBFromInitial($grammar, $nextMap)
    {
        $subject = new Symbol('E');
        $definition = [new Symbol('B')];

        $this->_testSimpleReducing($grammar, $nextMap['B'], $subject, $definition);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @return ItemSet[]
     * @depends testCreateGrammar
     * @depends testGetNextSetsFromInitial
     */
    public function testNextEFromInitial($grammar, $nextMap)
    {
        $item_set = $nextMap['E'];

        $S = new Symbol('S');
        $E = new Symbol('E');
        $B = new Symbol('B');
        $mul = new Symbol('mul', true);
        $add = new Symbol('add', true);

        $initial = $item_set->getInitialItems();
        $this->assertInternalType('array', $initial);
        $this->assertCount(3, $initial);
        $this->assertContainsOnlyInstancesOf(Item::class, $initial);

        $this->assertTrue($item_set->hasFinalItem());

        $rule = $item_set->getReduceRule();
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(0, Rule::compare($rule, new Rule($S, [$E], true)));

        $items_copy = [
            new Item($S, [$E], [], true),
            new Item($E, [$E], [$mul, $B]),
            new Item($E, [$E], [$add, $B]),
        ];
        $copy = new ItemSet($items_copy, $items_copy, $grammar);
        $this->assertTrue($item_set->isSame($copy));

        foreach ($items_copy as $item) {
            $this->assertTrue($item_set->hasItem($item));
        }

        $next_map = $item_set->getNextSets($grammar);
        $this->assertInternalType('array', $next_map);
        $this->assertCount(2, $next_map);
        $this->assertContainsOnlyInstancesOf(ItemSet::class, $next_map);
        foreach (['mul', 'add'] as $next_symbol) {
            $this->assertArrayHasKey($next_symbol, $next_map);
        }
        return $next_map;
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @return ItemSet[]
     * @depends testCreateGrammar
     * @depends testNextEFromInitial
     */
    public function testNextMulFromE($grammar, $nextMap)
    {
        $item_set = $nextMap['mul'];

        $E = new Symbol('E');
        $B = new Symbol('B');
        $mul = new Symbol('mul', true);
        $zero = new Symbol('zero', true);
        $one = new Symbol('one', true);

        $expect_item_initial = new Item($E, [$E, $mul], [$B]);

        $initial = $item_set->getInitialItems();
        $this->assertInternalType('array', $initial);
        $this->assertCount(1, $initial);
        $this->assertContainsOnlyInstancesOf(Item::class, $initial);
        $this->assertEquals(0, Item::compare($initial[0], $expect_item_initial));

        $expected_items = [
            $expect_item_initial,
            new Item($B, [], [$zero]),
            new Item($B, [], [$one]),
        ];
        foreach ($expected_items as $expected_item) {
            $this->assertTrue($item_set->hasItem($expected_item, false));
        }

        $copy = new ItemSet($expected_items, [$expect_item_initial], $grammar);
        $this->assertTrue($item_set->isSame($copy));

        $this->assertFalse($item_set->hasFinalItem());
        $this->assertNull($item_set->getReduceRule());

        $next_map = $item_set->getNextSets($grammar);
        $this->assertInternalType('array', $next_map);
        $this->assertCount(3, $next_map);
        $this->assertContainsOnlyInstancesOf(ItemSet::class, $next_map);
        foreach (['B', 'zero', 'one'] as $next_symbol) {
            $this->assertArrayHasKey($next_symbol, $next_map);
        }
        return $next_map;
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextMulFromE
     */
    public function testNextZeroFromEMul($grammar, $nextMap)
    {
        $item_set = $nextMap['zero'];
        $this->_testSimpleReducing($grammar, $item_set, new Symbol('B'), [new Symbol('zero', true)]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextMulFromE
     */
    public function testNextOneFromEMul($grammar, $nextMap)
    {
        $item_set = $nextMap['one'];
        $this->_testSimpleReducing($grammar, $item_set, new Symbol('B'), [new Symbol('one', true)]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextMulFromE
     */
    public function testNextBFromEMul($grammar, $nextMap)
    {
        $item_set = $nextMap['B'];
        $E = new Symbol('E');
        $B = new Symbol('B');
        $mul = new Symbol('mul', true);
        $this->_testSimpleReducing($grammar, $item_set, $E, [$E, $mul, $B]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @return ItemSet[]
     * @depends testCreateGrammar
     * @depends testNextEFromInitial
     */
    public function testNextAddFromE($grammar, $nextMap)
    {
        $item_set = $nextMap['add'];

        $E = new Symbol('E');
        $B = new Symbol('B');
        $add = new Symbol('add', true);
        $zero = new Symbol('zero', true);
        $one = new Symbol('one', true);

        $expect_item_initial = new Item($E, [$E, $add], [$B]);

        $initial = $item_set->getInitialItems();
        $this->assertInternalType('array', $initial);
        $this->assertCount(1, $initial);
        $this->assertContainsOnlyInstancesOf(Item::class, $initial);
        $this->assertEquals(0, Item::compare($initial[0], $expect_item_initial));

        $expected_items = [
            $expect_item_initial,
            new Item($B, [], [$zero]),
            new Item($B, [], [$one]),
        ];
        foreach ($expected_items as $expected_item) {
            $this->assertTrue($item_set->hasItem($expected_item, false));
        }

        $copy = new ItemSet($expected_items, [$expect_item_initial], $grammar);
        $this->assertTrue($item_set->isSame($copy));

        $this->assertFalse($item_set->hasFinalItem());
        $this->assertNull($item_set->getReduceRule());

        $next_map = $item_set->getNextSets($grammar);
        $this->assertInternalType('array', $next_map);
        $this->assertCount(3, $next_map);
        $this->assertContainsOnlyInstancesOf(ItemSet::class, $next_map);
        foreach (['B', 'zero', 'one'] as $next_symbol) {
            $this->assertArrayHasKey($next_symbol, $next_map);
        }
        return $next_map;
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextAddFromE
     */
    public function testNextZeroFromEAdd($grammar, $nextMap)
    {
        $item_set = $nextMap['zero'];
        $this->_testSimpleReducing($grammar, $item_set, new Symbol('B'), [new Symbol('zero', true)]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextAddFromE
     */
    public function testNextOneFromEAdd($grammar, $nextMap)
    {
        $item_set = $nextMap['one'];
        $this->_testSimpleReducing($grammar, $item_set, new Symbol('B'), [new Symbol('one', true)]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet[] $nextMap
     * @depends testCreateGrammar
     * @depends testNextAddFromE
     */
    public function testNextBFromEAdd($grammar, $nextMap)
    {
        $item_set = $nextMap['B'];
        $E = new Symbol('E');
        $B = new Symbol('B');
        $add = new Symbol('add', true);
        $this->_testSimpleReducing($grammar, $item_set, $E, [$E, $add, $B]);
    }

    /**
     * @param Grammar $grammar
     * @param ItemSet $itemSet
     * @param Symbol $subject
     * @param Symbol[] $definition
     * @param bool $eof
     */
    private function _testSimpleReducing($grammar, $itemSet, $subject, $definition, $eof = false)
    {
        $expect_item = new Item($subject, $definition, [], $eof);

        $initial = $itemSet->getInitialItems();
        $this->assertCount(1, $initial);
        $this->assertContainsOnlyInstancesOf(Item::class, $initial);
        $this->assertArrayHasKey(0, $initial);
        $this->assertEquals(0, Item::compare($initial[0], $expect_item));

        $next = $itemSet->getNextSets($grammar);
        $this->assertInternalType('array', $next);
        $this->assertCount(0, $next);

        $this->assertTrue($itemSet->hasItem($expect_item));

        if ($eof) {
            $this->assertTrue($itemSet->hasFinalItem());
        } else {
            $this->assertFalse($itemSet->hasFinalItem());
        }

        $rule = $itemSet->getReduceRule();
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(0, Rule::compare($rule, new Rule($subject, $definition)));

        $this->assertTrue($itemSet->isSame(new ItemSet([$expect_item], [$expect_item], $grammar)));
    }
}
