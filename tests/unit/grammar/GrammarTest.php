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
}
