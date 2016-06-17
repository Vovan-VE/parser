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
        $grammar = Grammar::create("E: A \$; A:a; A:A a\n A : B ; ;\n; \n\n B : b");
        $this->assertTrue($grammar instanceof Grammar, 'is Grammar object');
        $this->assertEquals(5, count($grammar->rules), 'rules count');
        $this->assertEquals(
            0,
            Rule::compare(
                $grammar->getMainRule(),
                new Rule(new Symbol('E'), [new Symbol('A')], true)
            ),
            'main rule match'
        );

        $terminals = $grammar->getTerminals();
        $this->assertTrue(is_array($terminals), 'Terminals are array');
        $this->assertEquals(2, count($terminals), 'Terminals count');
        $this->assertArrayHasKey('a', $terminals, 'Has terminal "a"');
        $this->assertArrayHasKey('b', $terminals, 'Has terminal "b"');

        $non_terminals = $grammar->getNonTerminals();
        $this->assertTrue(is_array($non_terminals), 'Non-terminals are array');
        $this->assertEquals(3, count($non_terminals), 'Non-terminals count');
        $this->assertArrayHasKey('A', $non_terminals, 'Has non-terminal "A"');
        $this->assertArrayHasKey('B', $non_terminals, 'Has non-terminal "B"');
        $this->assertArrayHasKey('E', $non_terminals, 'Has non-terminal "E"');

        $symbol_b = $grammar->getSymbol('b');
        $this->assertTrue($symbol_b instanceof Symbol, 'getSymbol(b) is Symbol');
        $unknown_symbol = $grammar->getSymbol('unknown');
        $this->assertNull($unknown_symbol, 'getSymbol(unknown) is NULL');

        $a_rules = $grammar->getRulesFor(new Symbol('A', false));
        $this->assertTrue(is_array($a_rules), 'getRulesFor(A) is array');
        $this->assertEquals(3, count($a_rules), 'Rules count for "A"');

        $terminal_rules = $grammar->getRulesFor(new Symbol('a', true));
        $this->assertTrue(is_array($terminal_rules), 'getRulesFor(a) is array');
        $this->assertEquals(0, count($terminal_rules), 'Rules count for "a"');
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
