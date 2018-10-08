<?php
namespace VovanVE\parser\tests\unit\grammar;

use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class GrammarTest extends BaseTestCase
{
    public function testCreateFailManyMainRules()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar("E: A \$; A: a; E: B \$; B: b");
    }

    public function testCreateFailNoMainRule()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar("A: A a; A: a");
    }

    public function testCreateFailNoTerminals()
    {
        $this->setExpectedException(GrammarException::class);
        TextLoader::createGrammar("E: A \$; A: B; B: A");
    }
}
