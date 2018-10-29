<?php
namespace VovanVE\parser\tests\unit\grammar;

use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class GrammarTest extends BaseTestCase
{
    public function testCreateFailManyMainRules()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Only one rule must to allow EOF');
        TextLoader::createGrammar('E: A $; A: a; E: B $; B: b');
    }

    public function testCreateFailNoMainRule()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('Exactly one rule must to allow EOF - it will be main rule');
        TextLoader::createGrammar('A: A a; A: a');
    }

    public function testCreateFailNoTerminals()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('No terminals');
        TextLoader::createGrammar('E: A $; A: B; B: A');
    }

    public function testCreateFailUndefined()
    {
        $this->expectException(GrammarException::class);
        $this->expectExceptionMessage('There are terminals without definitions: bar, foo');
        TextLoader::createGrammar('E: A $; A: foo; A: bar');
    }
}
