<?php
namespace VovanVE\parser\tests\unit\table;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\table\Item;
use VovanVE\parser\tests\helpers\BaseTestCase;

class ItemTest extends BaseTestCase
{
    public function testCompare()
    {
        $a = new Symbol('A');
        $b = new Symbol('B');
        $c = new Symbol('c', true);
        $d = new Symbol('d', true);
        $e = new Symbol('E');

        $orig = new Item($a, [$b, $c], [$d, $e]);
        $copy = new Item($a, [$b, $c], [$d, $e], false);
        $wtag = new Item($a, [$b, $c], [$d, $e], false, 'foo');

        $this->assertEquals(0, Item::compare($orig, $copy), 'orig == copy');
        $this->assertEquals(0, Item::compare($orig, $wtag), 'orig == copy with tag');

        $diffs = [
            new Item($a, [$b, $c], [$d, $e], true),
            new Item($a, [$b], [$c, $d, $e]),
            new Item($a, [$b], [$d, $e]),
            new Item($a, [$b, $c], [$d]),
            new Item($b, [$a, $c], [$d, $e]),
            new Item($a, [$a, $c], [$d, $e]),
            new Item($b, [$b, $c], [$d, $e]),
        ];
        foreach ($diffs as $i => $diff) {
            $this->assertNotEquals(0, Item::compare($orig, $diff), "orig != diff[$i]");
        }
    }

    public function testFlow()
    {
        $b = new Symbol('B');
        $c = new Symbol('c', true);
        $d = new Symbol('D');
        $rule = new Rule(new Symbol('A'), [$b, $c, $d]);

        $item = Item::createFromRule($rule);
        foreach ([$b, $c, $d, null] as $i => $expect_symbol) {
            $this->assertInstanceOf(Item::class, $item, 'is Item');
            $actual_symbol = $item->getExpected();

            if (null === $expect_symbol) {
                $this->assertNull($actual_symbol, "no next symbol at [$i]");
            } else {
                $this->assertInstanceOf(Symbol::class, $actual_symbol, "next symbol [$i] is Symbol");
                $this->assertEquals(0, Symbol::compare($expect_symbol, $actual_symbol), "next symbol [$i] match");
            }

            $as_rule = $item->getAsRule();
            $this->assertInstanceOf(Rule::class, $as_rule, 'rule from item');
            $this->assertEquals(0, Rule::compare($rule, $as_rule, true), 'source rule == out rule');

            $next_item = $item->shift();
            $this->assertFalse($next_item === $item, 'next item is not same object');
            $item = $next_item;
        }
        $this->assertNull($item, 'no next item');
    }

    public function testFlowTag()
    {
        $b = new Symbol('B');
        $c = new Symbol('c', true);
        $d = new Symbol('D');
        $orig = new Rule(new Symbol('A'), [$b, $c, $d], false, 'foo');

        $item = Item::createFromRule($orig);

        $item->shift()->shift();

        $out = $item->getAsRule();

        $this->assertEquals(0, Rule::compare($orig, $out, true), 'source rule == out rule');
    }
}
