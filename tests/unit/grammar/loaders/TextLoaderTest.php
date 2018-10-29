<?php
namespace VovanVE\parser\tests\unit\grammar\loaders;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\tests\helpers\BaseTestCase;

class TextLoaderTest extends BaseTestCase
{
    public function testCreateSuccess()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            E: A $;  A(end):a;  A ( loop ) :A a
            A(ref) : B ; ;
            ;
            A : "AAA"

            B : b
            B : c
            B : d
            a : 'aa'
            b : 'bb'
            c : "cc"
            d : /\d+/
_END
        );
        $this->assertInstanceOf(Grammar::class, $grammar, 'is Grammar object');
        $this->assertCount(8, $grammar->getRules(), 'rules count');
        $this->assertEquals(
            0,
            Rule::compare(
                $grammar->getMainRule(),
                new Rule(new Symbol('E'), [new Symbol('A')], true)
            ),
            'main rule match'
        );

        $terminals = $grammar->getTerminals();
        $this->assertInternalType('array', $terminals);
        $this->assertCount(5, $terminals, 'Terminals count');
        $this->assertArrayHasKey('a', $terminals, 'Has terminal "a"');
        $this->assertArrayHasKey('b', $terminals, 'Has terminal "b"');
        $this->assertArrayHasKey('c', $terminals, 'Has terminal "c"');
        $this->assertArrayHasKey('d', $terminals, 'Has terminal "d"');
        $this->assertArrayHasKey('AAA', $terminals, 'Has terminal "AAA"');

        $non_terminals = $grammar->getNonTerminals();
        $this->assertInternalType('array', $non_terminals);
        $this->assertCount(3, $non_terminals, 'Non-terminals count');
        $this->assertArrayHasKey('A', $non_terminals, 'Has non-terminal "A"');
        $this->assertArrayHasKey('B', $non_terminals, 'Has non-terminal "B"');
        $this->assertArrayHasKey('E', $non_terminals, 'Has non-terminal "E"');

        $inlines = $grammar->getInlines();
        $this->assertInternalType('array', $inlines);
        $this->assertCount(1, $inlines, 'Inline count');
        $this->assertTrue(in_array('AAA', $inlines, true), 'Has inline "AAA"');

        $fixed = $grammar->getFixed();
        $this->assertInternalType('array', $fixed);
        $this->assertCount(3, $fixed, 'Fixed count');
        $this->assertArrayHasKey('a', $fixed, 'Has fixed "a"');
        $this->assertArrayHasKey('b', $fixed, 'Has fixed "b"');
        $this->assertArrayHasKey('c', $fixed, 'Has fixed "c"');

        $regexp_map = $grammar->getRegExpMap();
        $this->assertInternalType('array', $regexp_map);
        $this->assertCount(1, $regexp_map, 'RegExp count');
        $this->assertArrayHasKey('d', $regexp_map, 'Has RegExp "d"');

        $symbol_b = $grammar->getSymbol('b');
        $this->assertInstanceOf(Symbol::class, $symbol_b, 'getSymbol(b) is Symbol');
        $unknown_symbol = $grammar->getSymbol('unknown');
        $this->assertNull($unknown_symbol, 'getSymbol(unknown) is NULL');

        $a_rules = $grammar->getRulesFor(new Symbol('A', false));
        $this->assertInternalType('array', $a_rules);
        $this->assertCount(4, $a_rules, 'Rules count for "A"');

        $terminal_rules = $grammar->getRulesFor(new Symbol('a', true));
        $this->assertInternalType('array', $terminal_rules);
        $this->assertCount(0, $terminal_rules, 'Rules count for "a"');
    }

    public function testCreateFailFormat()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Cannot parse none of expected tokens near "! invalid rule" at offset 13');
        TextLoader::createGrammar("E: A \$; some ! invalid rule");
    }

    /**
     * @param string $text
     * @dataProvider dataProvider_FailEmpty
     */
    public function testCreateFailEmpty(string $text, string $message)
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage($message);
        TextLoader::createGrammar($text);
    }

    public function dataProvider_FailEmpty(): array
    {
        return [
            [
                '',
                'Cannot parse grammar: Unexpected <EOF>; expected: "&", <separator>, "-", "." or <name> at offset 0',
            ],
            [
                '#comment',
                'Cannot parse grammar: Unexpected <EOF>; expected: "&", <separator>, "-", "." or <name> at offset 8',
            ],
            [
                ";#comment",
                'No rules defined',
            ],
            [
                ";;#comment",
                'No rules defined',
            ],
            [
                "\n\n#comment",
                'No rules defined',
            ],
            [
                "\n\n#comment",
                'No rules defined',
            ],
            [
                "#comment\n",
                'No rules defined',
            ],
            [
                "#comment\n\n",
                'No rules defined',
            ],
            [
                "#comment\n\n;",
                'No rules defined',
            ],
            [
                "#comment\n;",
                'No rules defined',
            ],
            [
                "#comment\n;\n",
                'No rules defined',
            ],
        ];
    }

    /**
     * @param string $text
     * @dataProvider dataProvider_comments
     */
    public function testComments(string $text)
    {
        $grammar = TextLoader::createGrammar($text);
        $this->assertInstanceOf(Grammar::class, $grammar);
    }

    public function dataProvider_comments(): array
    {
        return [
            [
                <<<'_END'
# comment1
# comment2
G: E $

# comment3
E: a     # comment3-1

# comment4

E: b     # comment5-1
# comment5

a: "x"
# comment6
b: "y"
# comment7
_END
            ],
            [
                <<<'_END'

# comment1
# comment2
G: E $

# comment3
E: a     # comment3-1

# comment4

E: b     # comment5-1
# comment5

a: "x"
# comment6
b: "y"
# comment7

_END
            ],
        ];
    }

    public function testInlineSemicolon()
    {
        foreach (
            [
                <<<'_END'
                    E: A $
                    A: "x;"
                    A: 'y;'
                    A: <z;>
_END
                ,
                <<<'_END'
                    E: A $
                    A: "x;"
                    A: 'y;'
                    A: <z;>

_END
                ,
                <<<'_END'
                    E: A $
                    A: "x;"
                    A: 'y;'
                    A: <z;>
                    A: a
                    a: ';'
_END
                ,
                <<<'_END'
                    E: A $
                    A: "x;"
                    A: 'y;'
                    A: <z;>
                    A: a
                    a: ';'

_END
                ,
                ' E: A $;  A: "x;";  A: \'y;\';  A: <z;>',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>; ',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a; a: ";"',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a; a: ";";',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a; a: ";"; ',
            ]
            as $text
        ) {
            $grammar = TextLoader::createGrammar($text);
            $this->assertInstanceOf(Grammar::class, $grammar);
        }
    }

    public function testCreateWithHidden()
    {
        $grammar = TextLoader::createGrammar(
            '
            G: E $
            E: A
            E: B
            A: A .comma a
            A: a
            B: B comma b
            B: b
            a: /a+/
            b: /b+/
            comma: ","
            '
        );
        $this->assertCount(3, $grammar->getTerminals(), 'does not care about hidden flag');
        $rules = $grammar->getRules();
        $comma_hidden = $rules[3]->getDefinition()[1];
        $this->assertEquals('comma', $comma_hidden->getName(), 'is comma');
        $this->assertTrue($comma_hidden->isHidden(), 'hidden comma');
        $comma_shown = $rules[5]->getDefinition()[1];
        $this->assertEquals('comma', $comma_shown->getName(), 'is comma');
        $this->assertFalse($comma_shown->isHidden(), 'shown comma');
    }

    public function testCreateHiddenOverlap()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: E $
            E: a .a
            E: a .b
            E: .a c
            E: .a .c
            a: /a+/
            b: /b+/
            c: /c+/
_END
        );
        $this->assertCount(3, $grammar->getTerminals());
    }

    public function testHiddenNonTerminal()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: a .B c $
            B: b
            a: /a+/
            b: /b+/
            c: /c+/
_END
        );
        $this->assertCount(3, $grammar->getTerminals());

        $non_terminals = $grammar->getNonTerminals();
        $this->assertCount(2, $non_terminals);
        $this->assertArrayHasKey('G', $non_terminals);
        $this->assertArrayHasKey('B', $non_terminals);
        $this->assertFalse($non_terminals['G']->isHidden());
        $this->assertTrue($non_terminals['B']->isHidden());

    }

    public function testCreateWithInlines()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: E $
            E: A
            E: B
            E: C
            A: A "," a
            A: a
            B: B ',' b
            B: b
            C: C <"> c
            C: c
            a: /a+/
            b: /b+/
            c: /c+/
_END
        );
        $this->assertCount(5, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(2, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');

        $rules = $grammar->getRules();

        $quote = $rules[4]->getDefinition()[1];
        $this->assertEquals(',', $quote->getName(), 'is comma from quotes');
        $this->assertTrue($quote->isHidden(), 'inlines are hidden tokens');

        $comma_q = $rules[6]->getDefinition()[1];
        $this->assertSame($comma_q, $quote, 'quoting style does not matter');

        $quote = $rules[8]->getDefinition()[1];
        $this->assertEquals('"', $quote->getName(), 'is quote from angle brackets');
    }

    public function testCreateWithFixed()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: a b $
            a: "+"
            b: "-"
_END
        );
        $this->assertCount(2, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(0, $grammar->getInlines(), 'total inlines');
        $this->assertCount(2, $grammar->getFixed(), 'total fixed');

        $this->assertCount(1, $grammar->getRules(), 'rules count');

    }

    public function testCreateNoFixedDueToDefinitionItems()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: A $
            A: "+" "-"
_END
        );
        $this->assertCount(2, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(2, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');

        $this->assertCount(2, $grammar->getRules(), 'rules count');

    }

    public function testCreateNoFixedDueToMultiRef()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: A "+" $
            A: "+"
_END
        );
        $this->assertCount(1, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(1, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');

        $this->assertCount(2, $grammar->getRules(), 'rules count');

    }

    public function testCreateNoFixedDueToTag()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G   : A $
            A(x): "+"
_END
        );
        $this->assertCount(1, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(1, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');

        $this->assertCount(2, $grammar->getRules(), 'rules count');
    }

    public function testCreateInlineWithConflictSingle()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Inline 'a' conflicts with token <a> defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSubject()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Inline 'E' conflicts with token <E> defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: "E"
_END
        );
    }

    public function testCreateInlineWithConflictCross1()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Token <a> conflicts with inline 'a' defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: 'a'
            E: a
_END
        );
    }

    public function testCreateInlineWithConflictCross2()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Inline 'a' conflicts with token <a> defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: a
            E: 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSingleHidden()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Inline 'a' conflicts with token <a> defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: .a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictCross1Hidden()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Token <a> conflicts with inline 'a' defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: 'a'
            E: .a
_END
        );
    }

    public function testCreateInlineWithConflictCross2Hidden()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage("Inline 'a' conflicts with token <a> defined previously");
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: .a
            E: 'a'
_END
        );
    }

    public function testCreateWithRegExp()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: a b c $
            a: /a+/
            b: /b+/
            c: /\//
_END
        );
        $this->assertCount(3, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(0, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');
        $this->assertCount(3, $grammar->getRegExpMap(), 'total RegExps');

        $this->assertCount(1, $grammar->getRules(), 'rules count');
    }

    public function testCreateWithRegExpHidden()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: a .b c $
            a: /a+/
            b: /b+/
            c: /c+/
_END
        );
        $this->assertCount(3, $grammar->getTerminals(), 'total terminals');
        $this->assertCount(0, $grammar->getInlines(), 'total inlines');
        $this->assertCount(0, $grammar->getFixed(), 'total fixed');
        $this->assertCount(3, $grammar->getRegExpMap(), 'total RegExps');

        $this->assertCount(1, $grammar->getRules(), 'rules count');
    }

    public function testFailCreateRegExpSyntaxNotClosedEmpty()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Cannot parse none of expected tokens near "/" at offset 34');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEmptyAndMore()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage(<<<'_MSG'
Cannot parse grammar: Cannot parse none of expected tokens near "/
            A: b" at offset 51
_MSG
);
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: a
            a: /
            A: b
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosed()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Cannot parse none of expected tokens near "/a+" at offset 34');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedAndMore()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage(<<<'_MSG'
Cannot parse grammar: Cannot parse none of expected tokens near "/a+
            A: b" at offset 51
_MSG
);
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: a
            a: /a+
            A: b
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedNothing()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Cannot parse none of expected tokens near "/a+\\" at offset 34');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+\
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedNothingAndMore()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage(<<<'_MSG'
Cannot parse grammar: Cannot parse none of expected tokens near "/a+\
            A: b" at offset 51
_MSG
);
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: a
            a: /a+\
            A: b
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedDelimiter()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Cannot parse none of expected tokens near "/a+\\/" at offset 34');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+\/
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedDelimiterAndMore()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage(<<<'_MSG'
Cannot parse grammar: Cannot parse none of expected tokens near "/a+\/
            A: b" at offset 51
_MSG
);
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: a
            a: /a+\/
            A: b
_END
        );
    }

    public function testFailCreateRegExpNotInPlace()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Cannot parse grammar: Expected <EOF> but got <regexp "/a+/"> at offset 38');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: foo /a+/ bar
_END
        );
    }

    public function testFailCreateRegExpConflictRegExp()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Duplicate RegExp rule for symbol `a`');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+/
            a: /b+/
_END
        );
    }

    public function testFailCreateRegExpConflictNonTerminal()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Symbol `A` defined as non-terminal and as regexp terminal in the same time');
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: "foo"
            A: /a+/
_END
        );
    }

    public function testFailCreateRegExpConflictFixedAfter()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Symbol `a` defined as non-terminal and as regexp terminal in the same time');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+/
            a: "b"
_END
        );
    }

    public function testFailCreateRegExpConflictFixedBefore()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Symbol `a` defined as non-terminal and as regexp terminal in the same time');
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: "b"
            a: /a+/
_END
        );
    }

    public function testCreateWithDefines()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G  : a $
            a  : /(?&b)/
            &b : /[a-z]+/
_END
        );
        $this->assertEquals(['b' => '[a-z]+'], $grammar->getDefines());
        $this->assertEquals(['a' => '(?&b)'], $grammar->getRegExpMap());
        $this->assertCount(1, $grammar->getRules());
    }

    public function testFailCreateDefinesConflictSymbolToken()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('DEFINE `a` overlaps with a symbol');
        TextLoader::createGrammar(<<<'_END'
            G : a $
            a : "b"
            &a: /a+/
_END
        );
    }

    public function testFailCreateDefinesConflictSymbolNonTerminal()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('DEFINE `a` overlaps with a symbol');
        TextLoader::createGrammar(<<<'_END'
            G : a $
            a : "b"
            a : "c"
            &a: /a+/
_END
        );
    }

    public function testFailCreateSymbolTokenConflictDefines()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Token <a> conflicts with DEFINE');
        TextLoader::createGrammar(<<<'_END'
            &a: /a+/
            G : a $
            a : "b"
_END
        );
    }

    public function testFailCreateSymbolNonTerminalConflictDefines()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Token <a> conflicts with DEFINE');
        TextLoader::createGrammar(<<<'_END'
            &a: /a+/
            G : a $
            a : "b"
            a : "c"
_END
        );
    }

    public function testFailCreateOptionUnknown()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Unknown option `-foo`');
        TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "b"
            -foo: "x"
_END
        );
    }

    public function testCreateWithWhitespaces()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "A"
            -ws : /\s+/
            -ws : /#.*/
_END
        );
        $this->assertEquals(['\\s+', '#.*'], $grammar->getWhitespaces());
        $this->assertEquals(['a' => 'A'], $grammar->getFixed());
        $this->assertCount(1, $grammar->getRules());
    }

    public function testFailCreateWhitespacesNotRegexp()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Option `-ws` requires a regexp');
        TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "b"
            -ws : " "
_END
        );
    }

    public function testCreateWithModifiers()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "A"
            -mod: 'uis'
_END
        );
        $this->assertEquals('uis', $grammar->getModifiers());
    }

    public function testFailCreateModifiersNotString()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Option `-mod` requires a string');
        TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "b"
            -mod: /xu/
_END
        );
    }

    public function testFailCreateModifiersMultiple()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Option `-mod` can be used only once');
        TextLoader::createGrammar(<<<'_END'
            G   : a $
            a   : "b"
            -mod: 's'
            -mod: 'ui'
_END
        );
    }

    public function testCreateHidden()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G   : A $
            .A  : b
            .A  : c
            .b  : /b+/
            .c  : 'ccc'
_END
        );

        $rules = $grammar->getRules();
        $this->assertCount(3, $rules);

        $rule_1 = $rules[0];
        $this->assertEquals('G', $rule_1->getSubject()->getName());
        $this->assertFalse($rule_1->getSubject()->isHidden());
        $this->assertFalse($rule_1->getSubject()->isTerminal());
        $this->assertCount(1, $rule_1->getDefinition());
        $this->assertEquals('A', $rule_1->getDefinition()[0]->getName());
        $this->assertTrue($rule_1->getDefinition()[0]->isHidden());
        $this->assertFalse($rule_1->getDefinition()[0]->isTerminal());

        $rule_2 = $rules[1];
        $this->assertEquals('A', $rule_2->getSubject()->getName());
        $this->assertTrue($rule_2->getSubject()->isHidden());
        $this->assertFalse($rule_2->getSubject()->isTerminal());
        $this->assertCount(1, $rule_2->getDefinition());
        $this->assertEquals('b', $rule_2->getDefinition()[0]->getName());
        $this->assertTrue($rule_2->getDefinition()[0]->isHidden());
        $this->assertTrue($rule_2->getDefinition()[0]->isTerminal());

        $rule_3 = $rules[2];
        $this->assertEquals('A', $rule_3->getSubject()->getName());
        $this->assertTrue($rule_3->getSubject()->isHidden());
        $this->assertFalse($rule_3->getSubject()->isTerminal());
        $this->assertCount(1, $rule_3->getDefinition());
        $this->assertEquals('c', $rule_3->getDefinition()[0]->getName());
        $this->assertTrue($rule_3->getDefinition()[0]->isHidden());
        $this->assertTrue($rule_3->getDefinition()[0]->isTerminal());
    }

    public function testFailCreateHiddenMix()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Symbol `A` defined both as hidden and as visible');
        TextLoader::createGrammar(<<<'_END'
            G   : A $
            A   : 'a'
            .A  : 'b'
_END
        );
    }
}
