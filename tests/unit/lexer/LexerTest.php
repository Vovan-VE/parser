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
        $lexer = (new Lexer)
            ->inline([
                '--INLINE--',
                '$',
                '\\',
            ])
            ->fixed([
                'inc' => '++',
                'dec' => '--',
                '.mul' => '*',
            ])
            ->terminals([
                'int' => '\\d++',
                'var' => '[a-z_][a-z_0-9]*+',
                'add' => '[-+]',
                '.bit' => '[|&]',
            ])
            ->whitespaces(['\\s++'])
            ->modifiers('i');

        $test_inputs = [
            'foo  + --bar - 42 + i++-37 & --INLINE-- | x$\\ * y23' => [
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
                ['bit', '&', true],
                ['--INLINE--', '--INLINE--', true],
                ['bit', '|', true],
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
                (new Lexer)
                    ->terminals([
                        'var' => '[a-z]',
                        'inc' => '\\+\\+',
                        'add' => '\\+',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['inc', '++'],
                    ['add', '+'],
                    ['var', 'b'],
                ],
            ],
            [
                (new Lexer)
                    ->terminals([
                        'var' => '[a-z]',
                        'add' => '\\+',
                        'inc' => '\\+\\+',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['add', '+'],
                    ['add', '+'],
                    ['add', '+'],
                    ['var', 'b'],
                ],
            ],
            [
                (new Lexer)
                    ->inline([
                        '++',
                        '+',
                    ])
                    ->terminals([
                        'var' => '[a-z]',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['++', '++', true],
                    ['+', '+', true],
                    ['var', 'b'],
                ],
            ],
            [
                // order for inlines does not matter
                (new Lexer)
                    ->inline([
                        '+',
                        '++',
                    ])
                    ->terminals([
                        'var' => '[a-z]',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['++', '++', true],
                    ['+', '+', true],
                    ['var', 'b'],
                ],
            ],
            [
                (new Lexer)
                    ->fixed([
                        'inc' => '++',
                        'add' => '+',
                    ])
                    ->terminals([
                        'var' => '[a-z]',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['inc', '++'],
                    ['add', '+'],
                    ['var', 'b'],
                ],
            ],
            [
                // order for fixed does not matter
                (new Lexer)
                    ->fixed([
                        'add' => '+',
                        'inc' => '++',
                    ])
                    ->terminals([
                        'var' => '[a-z]',
                    ])
                    ->whitespaces(['\\s++'])
                    ->modifiers('i'),
                [
                    ['var', 'a'],
                    ['inc', '++'],
                    ['add', '+'],
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
        $lexer = (new Lexer)
            ->terminals([
                'var' => '(?&id)',
                'num' => '(?&int)',
            ])
            ->whitespaces(['\\s++'])
            ->defines([
                'id' => '[a-z_][a-z_0-9]*+',
                'int' => '\\d++',
            ]);
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
        $lexer = (new Lexer)
            ->terminals(['a' => 'A'])
            ->whitespaces([
                '\\s++',
                '(?:#|\\/\\/)[^\\r\\n]*+(?:\\z|\\r\\n?|\\n)',
                '\\/\\*(?:[^*]++|\\*(?!\\/))*+\\*\\/',
            ]);

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
        $lexer = (new Lexer)
            ->terminals([
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
        $lexer = new Lexer();
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailBadNamesTerminals()
    {
        $lexer = (new Lexer)
            ->terminals([
                'int' => '\\d++',
                'bad-name' => '\\s++',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailBadNamesDefined()
    {
        $lexer = (new Lexer)
            ->terminals([
                'number' => '(?&int)',
            ])
            ->defines([
                'int' => '\\d++',
                'bad-name' => '\\s++',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testFailEmptyMatch()
    {
        $lexer = (new Lexer)
            ->terminals([
                'empty' => '.{0}',
            ]);
        $this->setExpectedException(DevException::class);
        foreach ($lexer->parse('.') as $token) {
            $this->assertNotEquals('', $token->getContent());
        }
    }

    public function testExtend()
    {
        $base = (new Lexer)->terminals(['a' => 'a++']);
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
        $base = (new Lexer)
            ->terminals(['a' => 'a++'])
            ->defines(['x' => 'x++']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->extend(['a' => 'A'], [], ['x' => 'X']);
    }

    public function testDefineDuplicate()
    {
        $base = (new Lexer)
            ->defines(['x' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->defines(['x' => 'y']);
    }

    public function testFixedDuplicate()
    {
        $base = (new Lexer)
            ->fixed(['x' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->fixed(['x' => 'y']);
    }

    public function testFixedDuplicateValues()
    {
        $base = (new Lexer)
            ->fixed([
                'x' => 'a',
                'y' => 'a',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->compile();
    }

    public function testTerminalDuplicate()
    {
        $base = (new Lexer)
            ->terminals(['x' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $base->terminals(['x' => 'y']);
    }

    public function testFlowCreation()
    {
        $a = new Lexer();
        $b = $a->defines(['name' => '[a-z]++']);
        $c = $b->terminals(['var' => '\\$(?&name)']);
        $d = $c->whitespaces(['\\s++']);
        $e = $d->modifiers('i');
        $f = $e->inline(['+', ',']);
        $g = $f->fixed(['inc' => '++']);

        $this->assertNotSame($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertNotSame($c, $d);
        $this->assertNotSame($d, $e);
        $this->assertNotSame($e, $f);
        $this->assertNotSame($f, $g);

        $this->assertNotSame($a, $g);

        foreach ([$a, $b, $c, $d, $e, $f, $g] as $lexer) {
            $this->assertInstanceOf(Lexer::class, $lexer);
            $this->assertFalse($lexer->isCompiled());
        }
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
        $lexer = (new Lexer)
            ->terminals([
                'a' => 'x',
                '.a' => 'y',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictHiddenVisible()
    {
        $lexer = (new Lexer)
            ->terminals([
                '.a' => 'y',
                'a' => 'x',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictVisibleInline()
    {
        $lexer = (new Lexer)
            ->inline(['a'])
            ->terminals(['a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictHiddenInline()
    {
        $lexer = (new Lexer)
            ->inline(['a'])
            ->terminals(['.a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictFixedVisibleHidden()
    {
        $lexer = (new Lexer)
            ->fixed([
                'a' => 'x',
                '.a' => 'y',
            ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictFixedVisibleInline()
    {
        $lexer = (new Lexer)
            ->inline(['a'])
            ->fixed(['a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictInlineFixedValue()
    {
        $lexer = (new Lexer)
            ->inline(['a'])
            ->fixed(['x' => 'a']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictFixedHiddenInline()
    {
        $lexer = (new Lexer)
            ->inline(['a'])
            ->fixed(['.a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictTerminalVisibleFixedVisible()
    {
        $lexer = (new Lexer)
            ->terminals(['a' => 'y'])
            ->fixed(['a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictTerminalVisibleFixedHidden()
    {
        $lexer = (new Lexer)
            ->terminals(['a' => 'y'])
            ->fixed(['.a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictTerminalHiddenFixedVisible()
    {
        $lexer = (new Lexer)
            ->terminals(['.a' => 'y'])
            ->fixed(['a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictTerminalHiddenFixedHidden()
    {
        $lexer = (new Lexer)
            ->terminals(['.a' => 'y'])
            ->fixed(['.a' => 'x']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictDefineFixedVisible()
    {
        $lexer = (new Lexer)
            ->defines(['a' => 'x'])
            ->fixed(['a' => 'y']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictDefineFixedHidden()
    {
        $lexer = (new Lexer)
            ->defines(['a' => 'x'])
            ->fixed(['.a' => 'y']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictDefineTerminalVisible()
    {
        $lexer = (new Lexer)
            ->defines(['a' => 'x'])
            ->terminals(['a' => 'y']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }

    public function testConflictDefineTerminalHidden()
    {
        $lexer = (new Lexer)
            ->defines(['a' => 'x'])
            ->terminals(['.a' => 'y']);
        $this->setExpectedException(\InvalidArgumentException::class);
        $lexer->compile();
    }
}
