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
        $this->assertCount(1, $fixed, 'Fixed count');
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar("E: A \$; some ! invalid rule");
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
_END
                ,
                <<<'_END'
                    E: A $
                    A: "x;"
                    A: 'y;'
                    A: <z;>
                    A: a

_END
                ,
                ' E: A $;  A: "x;";  A: \'y;\';  A: <z;>',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>; ',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a;',
                'E: A $;  A: "x;";  A: \'y;\';  A: <z;>;  A: a; ',
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
_END
        );
        $this->assertCount(3, $grammar->getTerminals());
    }

    public function testHiddenNonTerminal()
    {
        $grammar = TextLoader::createGrammar(<<<'_END'
            G: a .B c $
            B: b
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSubject()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: "E"
_END
        );
    }

    public function testCreateInlineWithConflictCross1()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: 'a'
            E: a
_END
        );
    }

    public function testCreateInlineWithConflictCross2()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: a
            E: 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSingleHidden()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: .a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictCross1Hidden()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: E $
            E: 'a'
            E: .a
_END
        );
    }

    public function testCreateInlineWithConflictCross2Hidden()
    {
        $this->setExpectedException(GrammarException::class);
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEmptyAndMore()
    {
        $this->setExpectedException(GrammarException::class);
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedAndMore()
    {
        $this->setExpectedException(GrammarException::class);
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+\
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedNothingAndMore()
    {
        $this->setExpectedException(GrammarException::class);
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+\/
_END
        );
    }

    public function testFailCreateRegExpSyntaxNotClosedEscapedDelimiterAndMore()
    {
        $this->setExpectedException(GrammarException::class);
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
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: foo /a+/ bar
_END
        );
    }

    public function testFailCreateRegExpConflictRegExp()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+/
            a: /b+/
_END
        );
    }

    public function testFailCreateRegExpConflictNonTerminal()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: A $
            A: "foo"
            A: /a+/
_END
        );
    }

    public function testFailCreateRegExpConflictFixedAfter()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: /a+/
            a: "b"
_END
        );
    }

    public function testFailCreateRegExpConflictFixedBefore()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar(<<<'_END'
            G: a $
            a: "b"
            a: /a+/
_END
        );
    }
}
