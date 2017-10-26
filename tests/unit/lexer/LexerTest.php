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
                '--INLINE--',

                'int' => '\\d++',
                'var' => '[a-z_][a-z_0-9]*+',
                'inc' => '\\+\\+',
                'dec' => '--',
                'add' => '[-+]',
                '.mul' => '\\*',

                '$',
                '\\',
            ],
            ['\\s++'],
            [],
            'i'
        );

        $test_inputs = [
            'foo  + --bar - 42 + i++-37 --INLINE-- x$\\ * y23' => [
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
                ['--INLINE--', '--INLINE--', true],
                ['var', 'x'],
                ['$', '$', true],
                ['\\', '\\', true],
                ['mul', '*', true],
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
        $n = 0;
        foreach ($test_inputs as $test_input => $expect_tokens) {
            $tokens = $lexer->parse($test_input);
            $this->assertInstanceOf(\Generator::class, $tokens, "Lexer->parse() is Generator");
            $parsed_tokens_count = 0;
            foreach ($tokens as $i => $token) {
                $this->assertInstanceOf(Token::class, $token, "$n: token[$i] is Token");
                $this->assertArrayHasKey($i, $expect_tokens, "$n: want token[$i]");
                list ($expect_type, $expect_content, $expect_hidden) = $expect_tokens[$i] + [2 => false];
                $this->assertEquals($expect_type, $token->getType(), "$n: token[$i] type");
                $this->assertEquals($expect_content, $token->getContent(), "$n: token[$i] content");
                $this->assertEquals($expect_hidden, $token->isHidden(), "$n: token[$i] isHidden");
                ++$parsed_tokens_count;
            }
            $this->assertEquals(count($expect_tokens), $parsed_tokens_count, "$n: tokens count");
            ++$n;
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
            [
                new Lexer(
                    [
                        'var' => '[a-z]',
                        '++',
                        '+',
                    ],
                    ['\\s++'],
                    [],
                    'i'
                ),
                [
                    ['var', 'a'],
                    ['++', '++', true],
                    ['+', '+', true],
                    ['var', 'b'],
                ],
            ],
            [
                //order for inlines does not matter
                new Lexer(
                    [
                        'var' => '[a-z]',
                        '+',
                        '++',
                    ],
                    ['\\s++'],
                    [],
                    'i'
                ),
                [
                    ['var', 'a'],
                    ['++', '++', true],
                    ['+', '+', true],
                    ['var', 'b'],
                ],
            ],
        ];

        foreach ($sub_tests as $n => $sub_test) {
            /** @var Lexer $lexer */
            list ($lexer, $expect_tokens) = $sub_test;
            $parsed_tokens_count = 0;
            foreach ($lexer->parse($test_input) as $i => $token) {
                list ($expect_type, $expect_content, $expect_hidden) = $expect_tokens[$i] + [2 => false];
                $this->assertEquals($expect_type, $token->getType(), "$n: token[$i] type");
                $this->assertEquals($expect_content, $token->getContent(), "$n: token[$i] content");
                $this->assertEquals($expect_hidden, $token->isHidden(), "$n: token[$i] isHidden");
                ++$parsed_tokens_count;
            }
            $this->assertEquals(count($expect_tokens), $parsed_tokens_count, "$n: tokens count");
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
                $this->assertInstanceOf(Token::class, $token, "token [$i] is Token");
                $this->assertEquals('a', $token->getType(), "token[$i]->type");
                $this->assertEquals('A', $token->getContent(), "token[$i]->type");
                ++$found_count;
            }
            $this->assertEquals($expect_count, $found_count, "tokens count");
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
        $lexer = new Lexer([]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailOverlappedNames()
    {
        $lexer = new Lexer(
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
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailBadNamesTerminals()
    {
        $lexer = new Lexer([
            'int' => '\\d++',
            'bad-name' => '\\s++',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailBadNamesDefined()
    {
        $lexer = new Lexer(
            [
                'number' => '(?&int)',
            ],
            [],
            [
                'int' => '\\d++',
                'bad-name' => '\\s++',
            ]
        );
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
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

    public function testExtend()
    {
        $base = new Lexer(['a' => 'a++']);
        $ext1 = $base->extend(['b' => 'b++']);
        $ext2 = $base->extend(['c' => 'c++']);
        $this->assertNotSame($base, $ext1, 'extended lexer is new one');
        $this->assertNotSame($base, $ext2, 'extended lexer is new one');
        $this->assertNotSame($ext1, $ext2, 'both extended lexers are new');

        $this->assertFalse($ext1->isCompiled(), 'ext1 is not compiled yet');
        $ext1->compile();

        $this->assertFalse($base->isCompiled(), 'base is not compiled yet');
        $base->compile();

        $this->assertFalse($ext2->isCompiled(), 'ext is not compiled yet');
    }

    public function testExtendDuplicate()
    {
        $base = new Lexer(['a' => 'a++'], [], ['x' => 'x++']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->extend(['a' => 'A'], [], ['x' => 'X']);
    }

    public function testArrayMixedKeys()
    {
        foreach (
            [
                [['a' => null, 'b' => null, null, null], ['a', 'b', 0, 1]],
                [['a' => null, null, 'b' => null, null], ['a', 0, 'b', 1]],
                [[null, 'a' => null, null, 'b' => null], [0, 'a', 1, 'b']],
                [[null, null, 'a' => null, 'b' => null], [0, 1, 'a', 'b']],
            ]
            as $case
        ) {
            list ($array, $keys) = $case;
            $this->assertSame($keys, array_keys($array));
        }
    }

    public function testAliasGeneration()
    {
        foreach (
            [
                'a' => 'b',
                'y' => 'z',
                'z' => 'aa',
                'aa' => 'ab',
                'az' => 'ba',
                'ba' => 'bb',
                'zz' => 'aaa',
                'aaa' => 'aab',
            ]
            as $a => $b
        ) {
            $next = $a;
            $next++;
            $this->assertSame($b, $next, "'$a'++ => '$b'");
        }
    }

    public function testConflictVisibleHidden()
    {
        $lexer = new Lexer([
            'a' => 'x',
            '.a' => 'y',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictHiddenVisible()
    {
        $lexer = new Lexer([
            '.a' => 'y',
            'a' => 'x',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictVisibleInline()
    {
        $lexer = new Lexer([
            'a' => 'x',
            'a',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictInlineVisible()
    {
        $lexer = new Lexer([
            'a',
            'a' => 'x',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictHiddenInline()
    {
        $lexer = new Lexer([
            '.a' => 'x',
            'a',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictInlineHidden()
    {
        $lexer = new Lexer([
            'a',
            '.a' => 'x',
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }
}
