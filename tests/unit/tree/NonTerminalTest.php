<?php
namespace VovanVE\parser\tests\unit\tree;

use VovanVE\parser\common\Token;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class NonTerminalTest extends BaseTestCase
{
    /**
     * @return NonTerminal
     */
    public function testVInt()
    {
        $token = new Token('int', '42');

        $node = new NonTerminal();
        $node->name = 'V';
        $node->children = [$token];

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

        return $node;
    }

    /**
     * @param NonTerminal $v
     * @return NonTerminal
     * @depends testVInt
     */
    public function testPVInt($v)
    {
        $node = new NonTerminal();
        $node->name = 'P';
        $node->children = [$v];

        $this->assertEquals(
            <<<'DUMP'
 `- P
     `- V
         `- int <42>

DUMP

            ,
            $node->dumpAsString()
        );

        return $node;
    }

    /**
     * @param NonTerminal $p
     * @param NonTerminal $v
     * @depends testPVInt
     * @depends testVInt
     */
    public function testEVMulInt($p, $v)
    {
        $mul = new Token('mul', '*');

        $node = new NonTerminal();
        $node->name = 'E';
        $node->children = [$p, $mul, $v];

        $this->assertEquals(
            <<<'DUMP'
.... `- E
.... |   `- P
.... |   |   `- V
.... |   |       `- int <42>
.... |   `- mul <*>
.... |   `- V
.... |       `- int <42>

DUMP

            ,
            $node->dumpAsString('....', false)
        );
    }
}
