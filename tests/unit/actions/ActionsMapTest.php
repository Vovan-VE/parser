<?php
namespace VovanVE\parser\tests\unit\actions;

use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ActionsMapTest extends BaseTestCase
{
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
}
