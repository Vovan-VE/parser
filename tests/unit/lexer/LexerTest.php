<?php
namespace VovanVE\parser\tests\unit\lexer;

use VovanVE\parser\common\DevException;
use VovanVE\parser\common\Token;
use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\lexer\ParseException;
use VovanVE\parser\tests\helpers\BaseTestCase;

class LexerTest extends BaseTestCase
{
    public function testParseBasic()
    {
        $lexer = new Lexer(
            [
                'int' => '\\d++',
                'var' => '[a-z_][a-z_0-9]*+',
                'inc' => '\\+\\+',
                'dec' => '--',
                'add' => '[-+]',
            ],
            ['\\s++'],
            [],
            'i'
        );

        $test_inputs = [
            'foo  + --bar - 42 + i++-37 x y23' => [
                ['var', 'foo'],
                ['add', '+'],
                ['dec', '--'],
                ['var', 'bar'],
                ['add', '-'],
                ['int', '42'],
                ['add', '+'],
                ['var', 'i'],
                ['inc', '++'],
                ['add', '-'],
                ['int', '37'],
                ['var', 'x'],
                ['var', 'y23'],
            ],
            '' => [],
            '  ' => [],
            'foo42+37bar' => [
                ['var', 'foo42'],
                ['add', '+'],
                ['int', '37'],
                ['var', 'bar'],
            ],
        ];
        foreach ($test_inputs as $test_input => $expect_tokens) {
            $tokens = $lexer->parse($test_input);
            $this->assertInstanceOf(\Generator::class, $tokens, "Lexer->parse() is Generator for <$test_input>");
            $parsed_tokens_count = 0;
            foreach ($tokens as $i => $token) {
                $this->assertInstanceOf(Token::class, $token, "token[$i] is Token for input <$test_input>");
                $this->assertArrayHasKey($i, $expect_tokens, "want token[$i] for input <$test_input>");
                list ($expect_type, $expect_content) = $expect_tokens[$i];
                $this->assertEquals($expect_type, $token->getType(), "token[$i]->type for input <$test_input>");
                $this->assertEquals($expect_content, $token->getContent(),
                    "token[$i]->content for input <$test_input>");
                ++$parsed_tokens_count;
            }
            $this->assertEquals(count($expect_tokens), $parsed_tokens_count, "tokens count for input <$test_input>");
        }
    }

    public function testParseDeclarationOrder()
    {
        $test_input = "a+++b";

        $sub_tests = [
            [
                new Lexer(
                    [
                        'var' => '[a-z]',
                        'inc' => '\\+\\+',
                        'add' => '\\+',
                    ],
                    ['\\s++'],
                    [],
                    'i'
                ),
                [
                    ['var', 'a'],
                    ['inc', '++'],
                    ['add', '+'],
                    ['var', 'b'],
                ],
            ],
            [
                new Lexer(
                    [
                        'var' => '[a-z]',
                        'add' => '\\+',
                        'inc' => '\\+\\+',
                    ],
                    ['\\s++'],
                    [],
                    'i'
                ),
                [
                    ['var', 'a'],
                    ['add', '+'],
                    ['add', '+'],
                    ['add', '+'],
                    ['var', 'b'],
                ],
            ],
        ];

        foreach ($sub_tests as $test_number => $sub_test) {
            /** @var Lexer $lexer */
            list ($lexer, $expect_tokens) = $sub_test;
            $parsed_tokens_count = 0;
            foreach ($lexer->parse($test_input) as $i => $token) {
                list ($expect_type, $expect_content) = $expect_tokens[$i];
                $this->assertEquals($expect_type, $token->getType(), "token[$i]->type for subtest [$test_number]");
                $this->assertEquals($expect_content, $token->getContent(),
                    "token[$i]->type for subtest [$test_number]");
                ++$parsed_tokens_count;
            }
            $this->assertEquals(count($expect_tokens), $parsed_tokens_count, "tokens count for subtest [$test_number]");
        }
    }

    public function testParseDefines()
    {
        $lexer = new Lexer(
            [
                'var' => '(?&id)',
                'num' => '(?&int)',
            ],
            ['\\s++'],
            [
                'id' => '[a-z_][a-z_0-9]*+',
                'int' => '\\d++',
            ]
        );
        $test_input = 'foo 42 foo42';
        $expect_tokens = [
            ['var', 'foo'],
            ['num', '42'],
            ['var', 'foo42'],
        ];
        $found_tokens = 0;
        foreach ($lexer->parse($test_input) as $i => $token) {
            $this->assertArrayHasKey($i, $expect_tokens, "want token [$i]");
            list ($expect_type, $expect_content) = $expect_tokens[$i];
            $this->assertEquals($expect_type, $token->getType(), "tokens[$i]->type");
            $this->assertEquals($expect_content, $token->getContent(), "tokens[$i]->content");
            ++$found_tokens;
        }
        $this->assertEquals(count($expect_tokens), $found_tokens, "tokens count");
    }

    public function testParseComments()
    {
        $lexer = new Lexer(
            ['a' => 'A'],
            [
                '\\s++',
                '(?:#|\\/\\/)[^\\r\\n]*+(?:\\z|\\r\\n?|\\n)',
                '\\/\\*(?:[^*]++|\\*(?!\\/))*+\\*\\/',
            ]
        );

        $test_inputs = [
            "AA  A\nA\n\n  A" => 5,
            "A /* comment A */ A /* A comment\n */ A /**/ AA  A" => 6,
            "# comment\n A # comment\r\n A A //comment\n\n \n A AAA\n" => 7,
            "//comment\n#comment\n/*comment*/" => 0,
            "" => 0,
            "#comment" => 0,
            "#comment\n" => 0,
            "//comment" => 0,
        ];
        foreach ($test_inputs as $test_input => $expect_count) {
            $found_count = 0;
            foreach ($lexer->parse($test_input) as $i => $token) {
                $this->assertInstanceOf(Token::class, $token, "token [$i] is Token for input <$test_input>");
                $this->assertEquals('a', $token->getType(), "token[$i]->type for input <$test_input>");
                $this->assertEquals('A', $token->getContent(), "token[$i]->type for input <$test_input>");
                ++$found_count;
            }
            $this->assertEquals($expect_count, $found_count, "tokens count for input <$test_input>");
        }
    }

    public function testParseFail()
    {
        $lexer = new Lexer([
            'var' => '[a-z]+',
            'int' => '\\d+',
        ]);
        $test_input = 'foo42bar37?!@';
        $expected_first = [
            ['var', 'foo'],
            ['int', '42'],
            ['var', 'bar'],
            ['int', '37'],
        ];
        $last_valid_index = count($expected_first) - 1;

        $tokens = $lexer->parse($test_input);
        $tokens->rewind();
        foreach ($expected_first as $i => list ($expect_type, $expect_content)) {
            $this->assertTrue($tokens->valid(), "found token [$i]");
            /** @var Token $token */
            $token = $tokens->current();
            $this->assertInstanceOf(Token::class, $token, "token [$i]");
            $this->assertEquals($expect_type, $token->getType(), "token[$i]->type");
            $this->assertEquals($expect_content, $token->getContent(), "token[$i]->content");
            if ($last_valid_index === $i) {
                $this->setExpectedException(ParseException::class);
            }
            $tokens->next();
        }
        $this->fail('should not reach here due to parse error');
    }

    public function testFailNoTerminals()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new Lexer([]);
    }

    public function testFailOverlappedNames()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new Lexer(
            [
                'foo' => '(?&lol)',
                'bar' => '(?&bar)',
            ],
            [],
            [
                'lol' => 'lo++l',
                'bar' => '\\d++',
            ]
        );
    }

    public function testFailBadNamesTerminals()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new Lexer([
            'int' => '\\d++',
            'bad-name' => '\\s++',
        ]);
    }

    public function testFailBadNamesDefined()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new Lexer(
            [
                'number' => '(?&int)',
            ],
            [],
            [
                'int' => '\\d++',
                'bad-name' => '\\s++',
            ]
        );
    }

    public function testFailEmptyMatch()
    {
        $lexer = new Lexer([
            'empty' => '.{0}',
        ]);
        $this->setExpectedException(DevException::class);
        foreach ($lexer->parse('.') as $token) {
            $this->assertNotEquals('', $token->getContent());
        }
    }
}
