<?php
namespace VovanVE\parser\tests\unit;

use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\LexerBuilder;
use VovanVE\parser\Parser;
use VovanVE\parser\SyntaxException;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ParserTest extends BaseTestCase
{
    /**
     * @return Parser
     */
    public function testCreate()
    {
        $lexer = (new LexerBuilder)
            ->terminals([
                'id' => '[a-z_][a-z_\\d]*+',
                'int' => '\\d++',
                'add' => '[-+]',
                'mul' => '[*\\/]',
            ])
            ->modifiers('i')
            ->create();

        $grammar = Grammar::create(<<<'_END'
E     : S $
S(add): S add P
S     : P
P(mul): P mul V
P     : V
V(int): int
V(var): id
_END
        );

        return new Parser($lexer, $grammar);
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseValidA($parser)
    {
        $tree = $parser->parse('A * 2  +1 ');
        $this->assertInstanceOf(NonTerminal::class, $tree);
        $this->assertEquals(<<<'DUMP'
 `- S(add)
     `- S
     |   `- P(mul)
     |       `- P
     |       |   `- V(var)
     |       |       `- id <A>
     |       `- mul <*>
     |       `- V(int)
     |           `- int <2>
     `- add <+>
     `- P
         `- V(int)
             `- int <1>

DUMP
            ,
            $tree->dumpAsString()
        );
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseValidB($parser)
    {
        $tree = $parser->parse('A * B / 23  + B / 37 - 42 * C ');
        $this->assertInstanceOf(NonTerminal::class, $tree);
        $this->assertEquals(<<<'DUMP'
 `- S(add)
     `- S(add)
     |   `- S
     |   |   `- P(mul)
     |   |       `- P(mul)
     |   |       |   `- P
     |   |       |   |   `- V(var)
     |   |       |   |       `- id <A>
     |   |       |   `- mul <*>
     |   |       |   `- V(var)
     |   |       |       `- id <B>
     |   |       `- mul </>
     |   |       `- V(int)
     |   |           `- int <23>
     |   `- add <+>
     |   `- P(mul)
     |       `- P
     |       |   `- V(var)
     |       |       `- id <B>
     |       `- mul </>
     |       `- V(int)
     |           `- int <37>
     `- add <->
     `- P(mul)
         `- P
         |   `- V(int)
         |       `- int <42>
         `- mul <*>
         `- V(var)
             `- id <C>

DUMP
            ,
            $tree->dumpAsString()
        );
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseFailA($parser)
    {
        $this->setExpectedException(SyntaxException::class, 'Expected <EOF> but got <id "B">');
        $parser->parse('A * 2 B');
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseFailB($parser)
    {
        $this->setExpectedException(SyntaxException::class, 'Unexpected <add "-">');
        $parser->parse('A * -5');
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseWithActions($parser)
    {
        $lexer = (new LexerBuilder)
            ->terminals([
                'int' => '\\d++',
                'add' => '\\+',
                'sub' => '-',
                'mul' => '\\*',
                'div' => '\\/',
            ])
            ->modifiers('i')
            ->create();

        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S add P
S(sub) : S sub P
S(P)   : P
P(mul) : P mul V
P(div) : P div V
P(V)   : V
V(int) : int
_END
        );

        $parser = new Parser($lexer, $grammar);

        $actions = [
            'int' => function (Token $int) {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, TreeNodeInterface $int) {
                return $int->made();
            },
            'P(V)' => function ($p, TreeNodeInterface $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, TreeNodeInterface $a, $mul, TreeNodeInterface $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, TreeNodeInterface $a, $div, TreeNodeInterface $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, TreeNodeInterface $p) {
                return $p->made();
            },
            'S(add)' => function ($s, TreeNodeInterface $a, $add, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, TreeNodeInterface $a, $sub, TreeNodeInterface $b) {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }
}
