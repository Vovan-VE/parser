<?php
namespace VovanVE\parser\tests\unit;

use VovanVE\parser\lexer\Lexer;
use VovanVE\parser\LexerBuilder;
use VovanVE\parser\tests\helpers\BaseTestCase;

class LexerBuilderTest extends BaseTestCase
{
    public function testCreate()
    {
        $builder = new LexerBuilder();
        $this->assertSame($builder, $builder->defines(['digits' => '\\d++']));
        $this->assertSame($builder, $builder->whitespaces(['\\s++', '#[^\\r\\n]*+(?:\\z|\\r\\n?|\\n)']));
        $this->assertSame($builder, $builder->terminals(['int' => '(?&digits)']));
        $this->assertSame($builder, $builder->modifiers('u'));
        $lexer = $builder->create();
        $this->assertInstanceOf(Lexer::class, $lexer);
    }
}
