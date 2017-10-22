<?php
namespace VovanVE\parser\tests\unit\grammar;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\tests\helpers\BaseTestCase;

class RuleTest extends BaseTestCase
{
    public function testCompare()
    {
        $foo = new Symbol('foo', false);
        $bar = new Symbol('bar', false);
        $baz = new Symbol('baz', true);
        $lol = new Symbol('lol', true);

        $orig = new Rule($foo, [$bar, $baz, $lol]);
        $copy = new Rule($foo, [$bar, $baz, $lol], false);
        $wtag = new Rule($foo, [$bar, $baz, $lol], false, 'foo');

        $this->assertEquals(0, Rule::compare($orig, $copy, false), 'orig == copy /skip tag');
        $this->assertEquals(0, Rule::compare($orig, $copy, true), 'orig == copy /check tag');
        $this->assertEquals(0, Rule::compare($orig, $wtag, false), 'orig == copy-tag /skip tag');
        $this->assertNotEquals(0, Rule::compare($orig, $wtag, true), 'orig != copy-tag /check tag');

        $diffs = [
            new Rule($foo, [$bar, $baz, $lol], true),
            new Rule($foo, [$bar, $baz], false),
            new Rule($bar, [$bar, $baz, $lol], false),
            new Rule($baz, [$bar, $lol], true),
        ];
        foreach ($diffs as $i => $diff) {
            $this->assertNotEquals(0, Rule::compare($orig, $diff), "orig != diffs[$i]");
        }
    }
}
