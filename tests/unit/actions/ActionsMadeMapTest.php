<?php
namespace VovanVE\parser\tests\unit\actions;

use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ActionsMadeMapTest extends BaseTestCase
{
    public function testApplyToNode()
    {
        $map = new ActionsMadeMap([
            'foo' => function ($content) {
                $this->assertEquals(1, func_num_args());
                $this->assertInternalType('string', $content);
                return $content;
            },
            'Bar' => function ($foo) {
                $this->assertEquals(1, func_num_args());
                $this->assertInternalType('string', $foo);
                return "[$foo]";
            },
        ]);

        $foo = new Token('foo', 'lorem ipsum');
        $this->assertTrue($map->applyToNode($foo));

        $bar = new NonTerminal('Bar', [$foo]);
        $this->assertTrue($map->applyToNode($bar));
        $this->assertEquals('[lorem ipsum]', $bar->made());

        $baz = new NonTerminal('Baz', [$foo]);
        $this->assertNull($map->applyToNode($baz));
        $this->assertNull($baz->made());

    }

    public function testApplyMultiple()
    {
        $map = new ActionsMadeMap([
            'int' => function ($content) {
                $this->assertEquals(1, func_num_args());
                $this->assertInternalType('string', $content);
                return (int)$content;
            },
            'Sum' => function ($a, $b) {
                $this->assertEquals(2, func_num_args());
                $this->assertInternalType('int', $a);
                $this->assertInternalType('int', $b);
                return $a + $b;
            },
        ]);

        $foo = new Token('int', '42');
        $this->assertTrue($map->applyToNode($foo));

        $bar = new Token('int', '37');
        $this->assertTrue($map->applyToNode($bar));

        $sum = new NonTerminal('Sum', [$foo, $bar]);
        $this->assertTrue($map->applyToNode($sum));
        $this->assertEquals(79, $sum->made());
    }
}
