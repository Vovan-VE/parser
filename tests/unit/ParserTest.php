<?php
namespace VovanVE\parser\tests\unit;

use VovanVE\parser\actions\AbortNodeException;
use VovanVE\parser\actions\ActionsMadeMap;
use VovanVE\parser\actions\ActionsMap;
use VovanVE\parser\common\Token;
use VovanVE\parser\common\TreeNodeInterface as INode;
use VovanVE\parser\errors\AbortedException;
use VovanVE\parser\errors\UnexpectedInputAfterEndException;
use VovanVE\parser\errors\UnexpectedTokenException;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;
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
V(S)  : "(" S ")"
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
        $this->setExpectedException(
            UnexpectedInputAfterEndException::class,
            'Expected <EOF> but got <id "B">'
        );
        $parser->parse('A * 2 B');
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseFailB($parser)
    {
        $this->setExpectedException(
            UnexpectedTokenException::class,
            'Unexpected <add "-">; expected: "(", <id> or <int>'
        );
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
            'V(int)' => function ($v, INode $int) {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, $mul, INode $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, $div, INode $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p) {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, $add, INode $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, $sub, INode $b) {
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
            'V(int)' => function ($v, INode $int) {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p) {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b) {
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
            'V(int)' => function ($v, INode $int) {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p) {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b) {
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
            'V(int)' => function ($v, INode $int) {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, $op, INode $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, $op, INode $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p) {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b) {
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
            'V(int)' => function ($v, INode $int) {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v) {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b) {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b) {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p) {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b) {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b) {
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

        $this->assertInstanceOf(INode::class, $result);
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
            'S' => function ($s, INode $a, INode $b) {
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

    public function testActionsAbort()
    {
        // some tokens are hidden completely on its definition
        $lexer = (new Lexer)
            ->whitespaces(['\\s+']);

        // some tokens are hidden locally in specific rules
        $grammar = Grammar::create(<<<'_END'
E      : S $
S(add) : S "+" P
S(sub) : S "-" P
S(P)   : P
P(mul) : P "*" V
P(div) : P "/" V
P(V)   : V
V      : "(" S ")"
V      : int
int    : /\d++/
_END
        );

        $parser = new Parser($lexer, $grammar);

        $actions = new ActionsMadeMap([
            'int' => function ($int) { return (int)$int; },
            'V(int)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'V' => Parser::ACTION_BUBBLE_THE_ONLY,
            'P(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'P(mul)' => function ($a, $b) { return $a * $b; },
            'P(div)' => function ($a, $b) {
                if (0 === $b || 0.0 === $b) {
                    throw new AbortNodeException('Division by zero', 2);
                }
                return $a / $b;
            },
            'S(P)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'S(add)' => function ($a, $b) { return $a + $b; },
            'S(sub)' => function ($a, $b) { return $a - $b; },
        ]);

        try {
            $parser->parse('42 / (2 * 5 - 10)', $actions);
            $this->fail('Did not abort action');
        } catch (AbortedException $e) {
            $this->assertEquals('Division by zero', $e->getMessage());
            $this->assertEquals(5, $e->getOffset());
        }
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

    public function testPreferredMatching()
    {
        $grammar = Grammar::create(<<<'_END'
            G           : Nodes $
            Nodes(list) : Nodes Node
            Nodes(first): Node

            Node(var)   : "${" VarName "}"
            Node(elem)  : "<" ElementName ">"
            Node(text)  : Text

            Text        : /[^$<>]++/
            VarName     : /[a-z][a-z0-9]*+/
            ElementName : /[a-z]++/
_END
);
        $lexer = new Lexer;

        $actions = new ActionsMadeMap([
            'Nodes(list)' => function ($nodes, $node) { $nodes[] = $node; return $nodes; },
            'Nodes(first)' => function ($node) { return [$node]; },

            'Node(var)' => function ($name) { return "html(\$$name)"; },
            'Node(elem)' => function ($name) { return 'element(' . var_export($name, true) . ')'; },
            'Node(text)' => function ($text) { return 'text(' . var_export($text, true) . ')'; },

            'VarName' => function ($name) { return $name; },
            'ElementName' => function ($name) { return $name; },
            'Text' => function ($content) { return $content; },
        ]);

        $parser = new Parser($lexer, $grammar);

        $result = $parser->parse('Lorem ${ipsum} dolor <sit> amet', $actions)->made();

        $this->assertEquals(["text('Lorem ')", 'html($ipsum)', "text(' dolor ')", "element('sit')", "text(' amet')"], $result);
    }
}
