<?php
namespace VovanVE\parser\tests\unit;

use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
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
        $lexer = (new Lexer)
            ->fixed([
                'div' => '/',
            ])
            ->terminals([
                'id' => '[a-z_][a-z_\\d]*+',
                'add' => '[-+]',
            ])
            ->whitespaces(['\\s+'])
            ->modifiers('i');

        $grammar = Grammar::create(<<<'_END'
E     : S $
S(add): S add P
S     : P
P(mul): P mul V
P(div): P div V
P     : V
V(int): int
V(var): id
int   : /\d++/
mul   : "*"
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
     |   |   `- P(div)
     |   |       `- P(mul)
     |   |       |   `- P
     |   |       |   |   `- V(var)
     |   |       |   |       `- id <A>
     |   |       |   `- mul <*>
     |   |       |   `- V(var)
     |   |       |       `- id <B>
     |   |       `- div </>
     |   |       `- V(int)
     |   |           `- int <23>
     |   `- add <+>
     |   `- P(div)
     |       `- P
     |       |   `- V(var)
     |       |       `- id <B>
     |       `- div </>
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
        $this->setExpectedException(SyntaxException::class, 'Unexpected <add "-">; expected: <id>, <int>');
        $parser->parse('A * -5');
    }

    public function testParseWithActions()
    {
        $lexer = (new Lexer)
            ->fixed([
                'mul' => '*',
                'div' => '/',
            ])
            ->terminals([
                'int' => '\\d++',
                'add' => '\\+',
                'sub' => '-',
            ])
            ->whitespaces(['\\s+'])
            ->modifiers('i');

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

    public function testParseWithHiddensAndActions()
    {
        // some tokens are hidden completely on its definition
        $lexer = (new Lexer)
            ->fixed([
                'add' => '+',
                '.mul' => '*',
            ])
            ->terminals([
                'int' => '\\d++',
                'sub' => '-',
                '.div' => '\\/',
            ])
            ->whitespaces(['\\s+'])
            ->modifiers('i');

        // some tokens are hidden locally in specific rules
        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S .add P
S(sub) : S .sub P
S(P)   : P
P(mul) : P mul V
P(div) : P .div V
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
            'P(mul)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, TreeNodeInterface $p) {
                return $p->made();
            },
            'S(add)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithInlinesAndActions()
    {
        // some tokens are hidden completely on its definition
        $lexer = (new Lexer)
            ->terminals([
                'int' => '\\d++',
            ])
            ->whitespaces(['\\s+']);

        // some tokens are hidden locally in specific rules
        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S '+' P
S(sub) : S "-" P
S(P)   : P
P(mul) : P <*> V
P(div) : P '/' V
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
            'P(mul)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, TreeNodeInterface $p) {
                return $p->made();
            },
            'S(add)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithInlinesAndFixedAndActions()
    {
        // some tokens are hidden completely on its definition
        $lexer = (new Lexer)
            ->terminals([
                'int' => '\\d++',
            ])
            ->whitespaces(['\\s+']);

        // some tokens are hidden locally in specific rules
        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S .add P
S(sub) : S .sub P
S(P)   : P
P(mul) : P mul V
P(div) : P div V
P(V)   : V
V(int) : int
add    : '+'
sub    : "-"
mul    : <*>
div    : '/'
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
            'P(mul)' => function ($p, TreeNodeInterface $a, $op, TreeNodeInterface $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, TreeNodeInterface $a, $op, TreeNodeInterface $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, TreeNodeInterface $p) {
                return $p->made();
            },
            'S(add)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithAllInlineAndActions()
    {
        // some tokens are hidden completely on its definition
        $lexer = (new Lexer)
            ->whitespaces(['\\s+']);

        // some tokens are hidden locally in specific rules
        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S '+' P
S(sub) : S "-" P
S(P)   : P
P(mul) : P <*> V
P(div) : P '/' V
P(V)   : V
V(int) : int
int    : /\d++/
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
            'P(mul)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, TreeNodeInterface $p) {
                return $p->made();
            },
            'S(add)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testContextDependent()
    {
        $grammar = Grammar::create(<<<'TEXT'
G       : Nodes $
Nodes(L): Nodes Node
Nodes(i): Node

Node    : "{{" Sum "}}"
Node    : text

Sum(add): Sum "+" Value
Sum(sub): Sum "-" Value
Sum(V)  : Value
Value   : int

int     : /\d++/
text    : /[^{}]++/

TEXT
        );

        $lexer = new Lexer;

        $parser = new Parser($lexer, $grammar);

        $result = $parser->parse("997foo{{42+37-23}}000bar", new ActionsMadeMap([
            'Nodes(L)' => function ($a, $b) {
                return $a . $b;
            },
            'Nodes(i)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Node' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Sum(add)' => function ($a, $b) {
                return $a + $b;
            },
            'Sum(sub)' => function ($a, $b) {
                return $a - $b;
            },
            'Sum(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
            'int' => function ($s) {
                return $s;
            },
            'text' => function ($s) {
                return $s;
            },
        ]));

        $this->assertInstanceOf(TreeNodeInterface::class, $result);
        $this->assertEquals('997foo56000bar', $result->made());
    }

    public function testInlinesOrderDoesNotMatter()
    {
        $lexer = new Lexer();
        $actions = [
            'A(a)' => function () {
                return 1;
            },
            'A(aa)' => function () {
                return 2;
            },
        ];

        foreach (
            [
                Grammar::create('
                    G    : A $
                    A(aa): "aa"
                    A(a) : "a" "a"
                '),
                Grammar::create('
                    G    : A $
                    A(a) : "a" "a"
                    A(aa): "aa"
                '),
            ]
            as $grammar
        ) {
            $parser = new Parser($lexer, $grammar);
            $out = $parser->parse('aa', $actions)->made();
            $this->assertEquals(2, $out);
        }
    }

    public function testActionsMapDefault()
    {
        $grammar = Grammar::create(<<<'_END'
            G  : S $
            S  : int "+" int
            int: /\d+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = new ActionsMap([
            'int' => function (Token $i) {
                return (int)$i->getContent();
            },
            'S' => function ($s, TreeNodeInterface $a, TreeNodeInterface $b) {
                return $a->made() + $b->made();
            },
        ]);

        $result = $parser->parse('42+37', $actions)->made();

        $this->assertEquals(79, $result);
    }

    public function testActionsMapMade()
    {
        $grammar = Grammar::create(<<<'_END'
            G  : S $
            S  : int "+" int
            int: /\d+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = new ActionsMadeMap([
            'int' => function ($i) {
                return (int)$i;
            },
            'S' => function ($a, $b) {
                return $a + $b;
            },
        ]);

        $result = $parser->parse('42+37', $actions)->made();

        $this->assertEquals(79, $result);
    }

    public function testConflictTerminals()
    {
        $lexer = (new Lexer)
            ->terminals([
                'int' => '\\d+',
            ]);
        $grammar = Grammar::create(<<<'_END'
            G: int $
            int: /\d+/
_END
        );

        $this->setExpectedException(\InvalidArgumentException::class);
        new Parser($lexer, $grammar);
    }
}
