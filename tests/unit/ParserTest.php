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
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class ParserTest extends BaseTestCase
{
    /**
     * @return Parser
     */
    public function testCreate(): Parser
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
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
add   : /[-+]/
div   : "/"
id    : /[a-z_][a-z_\d]*+/
-ws   : /\s+/
-mod  : 'i'
_END
        );

        $this->expectNotToPerformAssertions();

        return new Parser(new Lexer, $grammar);
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseValidA(Parser $parser): void
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
    public function testParseValidB(Parser $parser): void
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
    public function testParseFailA(Parser $parser): void
    {
        $this->expectException(UnexpectedInputAfterEndException::class);
        $this->expectExceptionMessage('Expected <EOF> but got <id "B">');
        $parser->parse('A * 2 B');
    }

    /**
     * @param Parser $parser
     * @depends testCreate
     */
    public function testParseFailB(Parser $parser): void
    {
        $this->expectException(UnexpectedTokenException::class);
        $this->expectExceptionMessage('Unexpected <add "-">; expected: "(", <id> or <int>');
        $parser->parse('A * -5');
    }

    public function testParseWithActions(): void
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
E      : S $
S(add) : S add P
S(sub) : S sub P
S(P)   : P
P(mul) : P mul V
P(div) : P div V
P(V)   : V
V(int) : int
add    : /\+/
sub    : /-/
div    : '/'
mul    : '*'
int    : /\d++/
-ws    : /\s+/
-mod   : 'i'
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'int' => function (Token $int): int {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, INode $int): int {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v): int {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, $mul, INode $b): int {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, $div, INode $b): int {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p): int {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, $add, INode $b): int {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, $sub, INode $b): int {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithHiddensAndActions(): void
    {
        // some tokens are hidden completely on its definition
        // some tokens are hidden locally in specific rules
        $grammar = TextLoader::createGrammar(<<<'_END'
E      : S $
S(add) : S .add P
S(sub) : S .sub P
S(P)   : P
P(mul) : P mul V
P(div) : P .div V
P(V)   : V
V(int) : int
add    : "+"
sub    : /-/
.mul   : "*"
.div   : /\//
int    : /\d++/
-ws    : /\s+/
-mod   : 'i'
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'int' => function (Token $int): int {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, INode $int): int {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v): int {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b): int {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b): int {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p): int {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b): int {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b): int {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithInlinesAndActions(): void
    {
        // some tokens are hidden locally in specific rules
        $grammar = TextLoader::createGrammar(<<<'_END'
E      : S $
S(add) : S '+' P
S(sub) : S "-" P
S(P)   : P
P(mul) : P <*> V
P(div) : P '/' V
P(V)   : V
V(int) : int
int    : /\d++/
-ws    : /\s+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'int' => function (Token $int): int {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, INode $int): int {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v): int {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b): int {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b): int {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p): int {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b): int {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b): int {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithInlinesAndFixedAndActions(): void
    {
        // some tokens are hidden locally in specific rules
        $grammar = TextLoader::createGrammar(<<<'_END'
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
int    : /\d++/
-ws    : /\s+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'int' => function (Token $int): int {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, INode $int): int {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v): int {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, $op, INode $b): int {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, $op, INode $b): int {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p): int {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b): int {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b): int {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testParseWithAllInlineAndActions(): void
    {
        // some tokens are hidden locally in specific rules
        $grammar = TextLoader::createGrammar(<<<'_END'
E      : S $
S(add) : S '+' P
S(sub) : S "-" P
S(P)   : P
P(mul) : P <*> V
P(div) : P '/' V
P(V)   : V
V(int) : int
int    : /\d++/
-ws    : /\s+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'int' => function (Token $int): int {
                return (int)$int->getContent();
            },
            'V(int)' => function ($v, INode $int): int {
                return $int->made();
            },
            'P(V)' => function ($p, INode $v): int {
                return $v->made();
            },
            'P(mul)' => function ($p, INode $a, INode $b): int {
                return $a->made() * $b->made();
            },
            'P(div)' => function ($p, INode $a, INode $b): int {
                return $a->made() / $b->made();
            },
            'S(P)' => function ($s, INode $p): int {
                return $p->made();
            },
            'S(add)' => function ($s, INode $a, INode $b): int {
                return $a->made() + $b->made();
            },
            'S(sub)' => function ($s, INode $a, INode $b): int {
                return $a->made() - $b->made();
            },
        ];

        $result = $parser->parse('42 * 23 / 3  + 90 / 15 - 17 * 19 ', $actions)->made();
        $this->assertEquals(5, $result, 'calculated result');
    }

    public function testContextDependent(): void
    {
        $grammar = TextLoader::createGrammar(<<<'TEXT'
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

        $parser = new Parser(new Lexer, $grammar);

        $result = $parser->parse("997foo{{42+37-23}}000bar", new ActionsMadeMap([
            'Nodes(L)' => function (string $a, string $b): string {
                return $a . $b;
            },
            'Nodes(i)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Node' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Sum(add)' => function (int $a, int $b): int {
                return $a + $b;
            },
            'Sum(sub)' => function (int $a, int $b): int {
                return $a - $b;
            },
            'Sum(V)' => Parser::ACTION_BUBBLE_THE_ONLY,
            'Value' => Parser::ACTION_BUBBLE_THE_ONLY,
            'int' => function (string $s): int {
                return (int)$s;
            },
            'text' => function (string $s): string {
                return $s;
            },
        ]));

        $this->assertInstanceOf(INode::class, $result);
        $this->assertEquals('997foo56000bar', $result->made());
    }

    public function testInlinesOrderDoesNotMatter(): void
    {
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
                TextLoader::createGrammar('
                    G    : A $
                    A(aa): "aa"
                    A(a) : "a" "a"
                '),
                TextLoader::createGrammar('
                    G    : A $
                    A(a) : "a" "a"
                    A(aa): "aa"
                '),
            ]
            as $grammar
        ) {
            $parser = new Parser(new Lexer, $grammar);
            $out = $parser->parse('aa', $actions)->made();
            $this->assertEquals(2, $out);
        }
    }

    public function testActionsMapDefault(): void
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G  : S $
            S  : int "+" int
            int: /\d+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = new ActionsMap([
            'int' => function (Token $i): int {
                return (int)$i->getContent();
            },
            'S' => function ($s, INode $a, INode $b): int {
                return $a->made() + $b->made();
            },
        ]);

        $result = $parser->parse('42+37', $actions)->made();

        $this->assertEquals(79, $result);
    }

    public function testActionsMapMade(): void
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G  : S $
            S  : int "+" int
            int: /\d+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = new ActionsMadeMap([
            'int' => function (string $i): int {
                return (int)$i;
            },
            'S' => function (int $a, int $b): int {
                return $a + $b;
            },
        ]);

        $result = $parser->parse('42+37', $actions)->made();

        $this->assertEquals(79, $result);
    }

    public function testActionsAbort(): void
    {
        // some tokens are hidden locally in specific rules
        $grammar = TextLoader::createGrammar(<<<'_END'
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
-ws    : /\s+/
_END
        );

        $parser = new Parser(new Lexer, $grammar);

        $actions = new ActionsMadeMap([
            'int' => function (string $int): int { return (int)$int; },
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

    public function testPreferredMatching(): void
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
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

        $parser = new Parser(new Lexer, $grammar);

        $result = $parser->parse('Lorem ${ipsum} dolor <sit> amet', $actions)->made();

        $this->assertEquals(["text('Lorem ')", 'html($ipsum)', "text(' dolor ')", "element('sit')", "text(' amet')"], $result);
    }
}
