<?php
namespace VovanVE\parser\tests\unit\tree;

use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class NonTerminalTest extends BaseTestCase
{
    /**
     * @return NonTerminal
     */
    public function testVInt(): NonTerminal
    {
        $token = new Token('int', '42');

        $node = new NonTerminal('V', [$token]);

        $this->assertEquals(1, $node->getChildrenCount());
        $this->assertCount(1, $node->getChildren());

        $this->assertEquals(<<<'DUMP'
 `- V
     `- int <42>

DUMP
            ,
            $node->dumpAsString()
        );

        $expected_indented = <<<'DUMP'
. `- V
.     `- int <42>

DUMP;
        $this->assertEquals($expected_indented, $node->dumpAsString('.'));
        $this->assertEquals($expected_indented, $node->dumpAsString('.', true));

        $this->assertEquals(<<<'DUMP'
. `- V
. |   `- int <42>

DUMP
            ,
            $node->dumpAsString('.', false)
        );

        $this->assertTrue($node->areChildrenMatch(['int']));
        $this->assertFalse($node->areChildrenMatch([]));
        $this->assertFalse($node->areChildrenMatch(['foo']));
        $this->assertFalse($node->areChildrenMatch(['foo', 'int']));
        $this->assertFalse($node->areChildrenMatch(['int', 'foo']));

        $this->assertNull($node->made());
        $node->make(42);
        $this->assertEquals(42, $node->made());

        $o = new \stdClass;
        $node->make($o);
        $this->assertSame($o, $node->made());

        return $node;
    }

    public function testVTagInt(): NonTerminal
    {
        $token = new Token('int', '37');

        $node = new NonTerminal('V', [$token], 'num');

        $this->assertEquals(1, $node->getChildrenCount());
        $this->assertCount(1, $node->getChildren());

        $this->assertEquals(<<<'DUMP'
 `- V(num)
     `- int <37>

DUMP
            ,
            $node->dumpAsString()
        );

        return $node;
    }

    /**
     * @param NonTerminal $v
     * @return NonTerminal
     * @depends testVInt
     */
    public function testPVInt(NonTerminal $v): NonTerminal
    {
        $node = new NonTerminal('P', [$v]);

        $this->assertEquals(
            <<<'DUMP'
 `- P
     `- V
         `- int <42>

DUMP

            ,
            $node->dumpAsString()
        );

        $this->assertTrue($node->areChildrenMatch(['V']));
        $this->assertFalse($node->areChildrenMatch([]));
        $this->assertFalse($node->areChildrenMatch(['P']));
        $this->assertFalse($node->areChildrenMatch(['int']));
        $this->assertFalse($node->areChildrenMatch(['foo', 'V']));
        $this->assertFalse($node->areChildrenMatch(['V', 'foo']));

        return $node;
    }

    /**
     * @param NonTerminal $p
     * @param NonTerminal $v
     * @return NonTerminal
     * @depends testPVInt
     * @depends testVTagInt
     */
    public function testEVMulInt(NonTerminal $p, NonTerminal $v): NonTerminal
    {
        $mul = new Token('mul', '*');

        $node = new NonTerminal('E', [$p, $mul, $v]);

        $this->assertEquals(3, $node->getChildrenCount());
        $this->assertCount(3, $node->getChildren());

        $this->assertEquals(
            <<<'DUMP'
.... `- E
.... |   `- P
.... |   |   `- V
.... |   |       `- int <42>
.... |   `- mul <*>
.... |   `- V(num)
.... |       `- int <37>

DUMP

            ,
            $node->dumpAsString('....', false)
        );

        $this->assertTrue($node->areChildrenMatch(['P', 'mul', 'V']));
        $this->assertFalse($node->areChildrenMatch([]));
        $this->assertFalse($node->areChildrenMatch(['P']));
        $this->assertFalse($node->areChildrenMatch(['V']));
        $this->assertFalse($node->areChildrenMatch(['P', 'mul']));
        $this->assertFalse($node->areChildrenMatch(['P', 'mul', 'foo']));
        $this->assertFalse($node->areChildrenMatch(['P', 'mul', 'V', 'foo']));
        $this->assertFalse($node->areChildrenMatch(['V', 'P', 'mul']));

        return $node;
    }

    /**
     * @param NonTerminal $node
     * @depends testEVMulInt
     */
    public function testGetChild(NonTerminal $node)
    {
        $this->assertInstanceOf(TreeNodeInterface::class, $node->getChild(0));
        $this->assertInstanceOf(TreeNodeInterface::class, $node->getChild(1));
        $this->assertInstanceOf(TreeNodeInterface::class, $node->getChild(2));

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('No children');
        $node->getChild(3);
    }
}
