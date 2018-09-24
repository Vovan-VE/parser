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
        $this->assertEquals(2, $sum->getChildrenCount());
    }

    public function testApplyWithPrune()
    {
        $map = new ActionsMadeMap([
            'int' => function ($content) {
                return (int)$content;
            },
            'Sum' => function ($a, $b) {
                return $a + $b;
            },
        ]);
        $map->prune = true;

        $foo = new Token('int', '42');
        $bar = new Token('int', '37');

        $foobar = new NonTerminal('Sum', [$foo, $bar]);

        $qux = new Token('int', '12');

        $foobarqux = new NonTerminal('Sum', [$foobar, $qux]);

        $this->assertTrue($map->applyToNode($foo));
        $this->assertTrue($map->applyToNode($bar));
        $this->assertTrue($map->applyToNode($foobar));
        $this->assertTrue($map->applyToNode($qux));
        $this->assertTrue($map->applyToNode($foobarqux));
        $this->assertEquals(91, $foobarqux->made());

        $this->assertEquals(0, $foobar->getChildrenCount());
        $this->assertEquals(0, $foobarqux->getChildrenCount());
    }

    public function testThrowingInTerminal()
    {
        $map = new ActionsMadeMap([
            'foo' => function ($foo) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');

        $this->setExpectedException(\RuntimeException::class, "Action failure in `foo`");
        $map->applyToNode($foo);
    }

    public function testThrowingInNonTerminal()
    {
        $map = new ActionsMadeMap([
            'Baz' => function () {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');
        $baz = new NonTerminal('Baz', [$foo]);

        $this->setExpectedException(\RuntimeException::class, "Action failure in `Baz`");
        $map->applyToNode($baz);
    }

    public function testThrowingInNonTerminalTag()
    {
        $map = new ActionsMadeMap([
            'Baz(tag)' => function () {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');
        $baz = new NonTerminal('Baz', [$foo], 'tag');

        $this->setExpectedException(\RuntimeException::class, "Action failure in `Baz(tag)`");
        $map->applyToNode($baz);
    }
}
