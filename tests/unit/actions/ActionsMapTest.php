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
            'Bar' => function (TreeNodeInterface $bar) {
                list ($foo) = $bar->getChildren();
                return '[' . $foo->made() . ']';
            },
        ]);

        $token = new Token('foo', 'lorem ipsum');
        $token->make($map->runForNode($token));
        $node = new NonTerminal('Bar', [$token]);

        $this->assertEquals('[lorem ipsum]', $map->runForNode($node));
        $this->assertNull($map->runForNode(new Token('x', '42')));
    }
}
