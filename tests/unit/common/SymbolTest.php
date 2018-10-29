<?php
namespace VovanVE\parser\tests\unit\common;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\tests\helpers\BaseTestCase;

class SymbolTest extends BaseTestCase
{
    public function testCompare(): void
    {
        $foo = new Symbol('foo');
        $foo_copy = new Symbol('foo', false);
        $foo_hidden = new Symbol('foo', false, true);
        $bar = new Symbol('bar');

        $baz = new Symbol('baz', true);
        $foo_terminal = new Symbol('foo', true);

        $this->assertFalse($foo->isHidden(), 'must not be hidden by default');
        $this->assertTrue($foo_hidden->isHidden(), 'foo_hidden must be hidden');

        $this->assertEquals(0, Symbol::compare($foo, $foo_copy), 'foo == foo_copy');
        $this->assertEquals(0, Symbol::compare($foo, $foo_hidden), 'foo == foo_hidden');
        $this->assertNotEquals(0, Symbol::compare($foo, $bar), 'foo != bar');
        $this->assertNotEquals(0, Symbol::compare($foo, $baz), 'foo != baz');
        $this->assertNotEquals(0, Symbol::compare($foo, $foo_terminal), 'foo != foo_terminal');
    }

    public function testCompareList(): void
    {
        $orig = [new Symbol('foo', true), new Symbol('foo', false), new Symbol('bar', true)];
        $copy = [new Symbol('foo', true), new Symbol('foo', false), new Symbol('bar', true)];

        $this->assertEquals(0, Symbol::compareList($orig, $copy), 'orig == copy');

        $diffs = [
            [new Symbol('foo', true), new Symbol('foo', false)],
            [new Symbol('foo', true), new Symbol('foo', false), new Symbol('bar',
                true), new Symbol('more')],
            [new Symbol('more'), new Symbol('foo', true), new Symbol('foo',
                false), new Symbol('bar', true)],
            [new Symbol('foo', false), new Symbol('foo', false), new Symbol('bar', true)],
            [new Symbol('foo', true), new Symbol('foo', true), new Symbol('bar', true)],
            [new Symbol('foo', false), new Symbol('foo', false), new Symbol('bar', false)],
            [new Symbol('lol', true), new Symbol('foo', false), new Symbol('bar', true)],
            [new Symbol('foo', true), new Symbol('lol', true), new Symbol('bar', true)],
        ];

        foreach ($diffs as $i => $diff) {
            $this->assertNotEquals(0, Symbol::compareList($orig, $diff), "orig != diff[$i]");
        }
    }
}
