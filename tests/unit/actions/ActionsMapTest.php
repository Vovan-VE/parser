<?php
namespace VovanVE\parser\tests\unit\actions;

use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ActionsMapTest extends BaseTestCase
{
    public function testApplyToNode(): void
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

    public function testThrowingInTerminal(): void
    {
        $map = new ActionsMap([
            'foo' => function (Token $foo) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Action failure in `foo`");
        $map->applyToNode($foo);
    }

    public function testThrowingInNonTerminal(): void
    {
        $map = new ActionsMap([
            'Baz' => function (NonTerminal $baz) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');
        $baz = new NonTerminal('Baz', [$foo]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Action failure in `Baz`");
        $map->applyToNode($baz);
    }

    public function testThrowingInNonTerminalTag(): void
    {
        $map = new ActionsMap([
            'Baz(tag)' => function (NonTerminal $baz) {
                throw new \DomainException('Something was wrong in userland code');
            },
        ]);
        $foo = new Token('foo', 'lorem ipsum');
        $baz = new NonTerminal('Baz', [$foo], 'tag');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Action failure in `Baz(tag)`");
        $map->applyToNode($baz);
    }
}
