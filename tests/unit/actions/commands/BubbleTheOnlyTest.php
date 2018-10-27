<?php
namespace VovanVE\parser\tests\unit\actions\commands;

use VovanVE\parser\actions\commands\BubbleTheOnly;
use VovanVE\parser\common\Token;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\Parser;
use VovanVE\parser\tests\helpers\BaseTestCase;
use VovanVE\parser\tree\NonTerminal;

class BubbleTheOnlyTest extends BaseTestCase
{
    public function testRun(): void
    {
        $token = new Token('t', '');
        $node = new NonTerminal('N', [$token]);

        foreach (
            [
                null,
                false,
                true,
                42,
                37.23,
                'string',
                ['key' => 'value', 17],
                new \stdClass,
            ]
            as $value
        ) {
            $token->make($value);
            $this->assertSame($value, BubbleTheOnly::runForNode($node));
        }
    }

    public function testUsage(): void
    {
        $grammar = TextLoader::createGrammar('
            G: E $
            E: "(" E ")"
            E: "[" E "]"
            E: "{" E "}"
            E: name

            name: /[a-z]++/
        ');
        $parser = new Parser(new Lexer, $grammar);

        $actions = [
            'name' => function (Token $name) {
                return $name->getContent();
            },
            'E' => Parser::ACTION_BUBBLE_THE_ONLY,
        ];

        $name = 'foobar';
        $result = $parser->parse("([({[$name]})])", $actions)->made();
        $this->assertEquals($name, $result);
    }
}
