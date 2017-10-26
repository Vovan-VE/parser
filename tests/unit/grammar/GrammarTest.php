<?php
namespace VovanVE\parser\tests\unit\grammar;

use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\Rule;
use VovanVE\parser\tests\helpers\BaseTestCase;

class GrammarTest extends BaseTestCase
{
    public function testCreateSuccess()
    {
        $grammar = Grammar::create("
            E: A \$;  A(end):a;  A ( loop ) :A a
            A(ref) : B ; ;
            ;

            B : b"
        );
        $this->assertInstanceOf(Grammar::class, $grammar, 'is Grammar object');
        $this->assertCount(5, $grammar->getRules(), 'rules count');
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
        $this->assertCount(2, $terminals, 'Terminals count');
        $this->assertArrayHasKey('a', $terminals, 'Has terminal "a"');
        $this->assertArrayHasKey('b', $terminals, 'Has terminal "b"');

        $non_terminals = $grammar->getNonTerminals();
        $this->assertInternalType('array', $non_terminals);
        $this->assertCount(3, $non_terminals, 'Non-terminals count');
        $this->assertArrayHasKey('A', $non_terminals, 'Has non-terminal "A"');
        $this->assertArrayHasKey('B', $non_terminals, 'Has non-terminal "B"');
        $this->assertArrayHasKey('E', $non_terminals, 'Has non-terminal "E"');

        $symbol_b = $grammar->getSymbol('b');
        $this->assertInstanceOf(Symbol::class, $symbol_b, 'getSymbol(b) is Symbol');
        $unknown_symbol = $grammar->getSymbol('unknown');
        $this->assertNull($unknown_symbol, 'getSymbol(unknown) is NULL');

        $a_rules = $grammar->getRulesFor(new Symbol('A', false));
        $this->assertInternalType('array', $a_rules);
        $this->assertCount(3, $a_rules, 'Rules count for "A"');

        $terminal_rules = $grammar->getRulesFor(new Symbol('a', true));
        $this->assertInternalType('array', $terminal_rules);
        $this->assertCount(0, $terminal_rules, 'Rules count for "a"');
    }

    public function testCreateFailFormat()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create("E: A \$; some ! invalid rule");
    }

    public function testCreateFailManyMainRules()
    {
        $this->setExpectedException(GrammarException::class);
        Grammar::create("E: A \$; A: a; E: B \$; B: b");
    }

    public function testCreateFailNoMainRule()
    {
        $this->setExpectedException(GrammarException::class);
        Grammar::create("A: A a; A: a");
    }

    public function testCreateFailNoTerminals()
    {
        $this->setExpectedException(GrammarException::class);
        Grammar::create("E: A \$; A: B; B: A");
    }

    public function testCreateWithHidden()
    {
        $grammar = Grammar::create(
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
        $grammar = Grammar::create(<<<'_END'
            G: E $
            E: a .a
            E: a .b
            E: .a c
            E: .a .c
_END
        );
        $this->assertCount(3, $grammar->getTerminals());
    }

    public function testCreateWithInlines()
    {
        $grammar = Grammar::create(<<<'_END'
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

        $rules = $grammar->getRules();

        $quote = $rules[4]->getDefinition()[1];
        $this->assertEquals(',', $quote->getName(), 'is comma from quotes');
        $this->assertTrue($quote->isHidden(), 'inlines are hidden tokens');

        $comma_q = $rules[6]->getDefinition()[1];
        $this->assertSame($comma_q, $quote, 'quoting style does not matter');

        $quote = $rules[8]->getDefinition()[1];
        $this->assertEquals('"', $quote->getName(), 'is quote from angle brackets');
    }

    public function testCreateInlineWithConflictSingle()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSubject()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: "E"
_END
        );
    }

    public function testCreateInlineWithConflictCross1()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: 'a'
            E: a
_END
        );
    }

    public function testCreateInlineWithConflictCross2()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: a
            E: 'a'
_END
        );
    }

    public function testCreateInlineWithConflictSingleHidden()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: .a 'a'
_END
        );
    }

    public function testCreateInlineWithConflictCross1Hidden()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: 'a'
            E: .a
_END
        );
    }

    public function testCreateInlineWithConflictCross2Hidden()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        Grammar::create(<<<'_END'
            G: E $
            E: .a
            E: 'a'
_END
        );
    }
}
