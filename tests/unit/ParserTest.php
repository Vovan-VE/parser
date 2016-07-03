<?php
namespace VovanVE\parser\tests\unit;

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
E: S $
S: S add P
S: P
P: P mul V
P: V
V: int
V: id
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
 `- S
     `- S
     |   `- P
     |       `- P
     |       |   `- V
     |       |       `- id <A>
     |       `- mul <*>
     |       `- V
     |           `- int <2>
     `- add <+>
     `- P
         `- V
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
 `- S
     `- S
     |   `- S
     |   |   `- P
     |   |       `- P
     |   |       |   `- P
     |   |       |   |   `- V
     |   |       |   |       `- id <A>
     |   |       |   `- mul <*>
     |   |       |   `- V
     |   |       |       `- id <B>
     |   |       `- mul </>
     |   |       `- V
     |   |           `- int <23>
     |   `- add <+>
     |   `- P
     |       `- P
     |       |   `- V
     |       |       `- id <B>
     |       `- mul </>
     |       `- V
     |           `- int <37>
     `- add <->
     `- P
         `- P
         |   `- V
         |       `- int <42>
         `- mul <*>
         `- V
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
}
