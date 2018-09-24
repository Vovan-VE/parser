<?php
namespace VovanVE\parser\tests\unit\actions;

use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ActionsMapTest extends BaseTestCase
{
    public function testApplyToNode()
    {
        $map = new ActionsMap([
            'foo' => function (Token $foo) {
                return $foo->getContent();
            },
            'Bar' => function (TreeNodeInterface $bar, TreeNodeInterface $foo) {
                // apply instead of return
                $bar->make('[' . $foo->made() . ']');
                // so return null
            },
        ]);

        $foo = new Token('foo', 'lorem ipsum');
        $this->assertTrue($map->applyToNode($foo));

        $bar = new NonTerminal('Bar', [$foo]);
        $this->assertFalse($map->applyToNode($bar));
        $this->assertEquals('[lorem ipsum]', $bar->made());

        $baz = new Token('baz', 'dolor');
        $baz->make(42);
        $this->assertNull($map->applyToNode($baz));
        $this->assertSame(42, $baz->made());
    }

    public function testRunForNode()
    {
        $map = new ActionsMap([
            'foo' => function (Token $foo) {
                return $foo->getContent();
            },
            'Bar' => function (TreeNodeInterface $bar, TreeNodeInterface $foo) {
                return '[' . $foo->made() . ']';
            },
            'Baz' => ActionsMap::DO_BUBBLE_THE_ONLY,
        ]);

        $foo = new Token('foo', 'lorem ipsum');
        $foo->make($map->runForNode($foo));
        $bar = new NonTerminal('Bar', [$foo]);

        $this->assertEquals('[lorem ipsum]', $map->runForNode($bar));
        $this->assertNull($map->runForNode(new Token('x', '42')));

        $baz = new NonTerminal('Baz', [$foo]);
        $this->assertEquals('lorem ipsum', $map->runForNode($baz));
    }

    public function testThrowingInTerminal()
    {
        $map = new ActionsMap([
            'foo' => function (Token $foo) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');

        $this->setExpectedException(\RuntimeException::class, "Action failure in `foo`");
        $map->applyToNode($foo);
    }

    public function testThrowingInNonTerminal()
    {
        $map = new ActionsMap([
            'Baz' => function (NonTerminal $baz) {
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
        $map = new ActionsMap([
            'Baz(tag)' => function (NonTerminal $baz) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');
        $baz = new NonTerminal('Baz', [$foo], 'tag');

        $this->setExpectedException(\RuntimeException::class, "Action failure in `Baz(tag)`");
        $map->applyToNode($baz);
    }
}
