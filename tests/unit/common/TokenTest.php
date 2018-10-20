<?php
namespace VovanVE\parser\tests\unit\common;

use VovanVE\parser\common\Token;
use VovanVE\parser\tests\helpers\BaseTestCase;

class TokenTest extends BaseTestCase
{
    public function testBasic()
    {
        $type = 'foo';
        $content = 'bar';

        $token = new Token($type, $content);

        $this->assertEquals(0, $token->getChildrenCount());
        $this->assertCount(0, $token->getChildren());

        $dump_end = " `- $type <$content>" . PHP_EOL;

        foreach (['', '    ', "\t"] as $indent) {
            foreach ([false, true] as $last) {
                $this->assertEquals(
                    $indent . $dump_end,
                    $token->dumpAsString($indent, $last),
                    'indent=' . json_encode($indent) . ', last=' . ($last ? 'true' : 'false')
                );
            }
        }

        return $token;
    }

    /**
     * @param Token $token
     * @depends testBasic
     */
    public function testChildrenMatch($token)
    {
        $this->assertTrue($token->areChildrenMatch([]));
        $this->assertFalse($token->areChildrenMatch(['lorem']));
        $this->assertFalse($token->areChildrenMatch(['ipsum', 'dolor']));
    }

    /**
     * @param Token $token
     * @depends testBasic
     */
    public function testMake($token)
    {
        $this->assertNull($token->made());
        $token->make(42);
        $this->assertEquals(42, $token->made());

        $o = new \stdClass;
        $token->make($o);
        $this->assertSame($o, $token->made());
    }

    /**
     * @param Token $token
     * @depends testBasic
     */
    public function testHidden($token)
    {
        $this->assertFalse($token->isHidden(), 'token must not be hidden by default');
        $hidden = new Token('a', 'b', null, null, true);
        $this->assertTrue($hidden->isHidden(), 'hidden token');
    }

    /**
     * @param Token $token
     * @depends testBasic
     */
    public function testInline($token)
    {
        $this->assertFalse($token->isInline(), 'token must not be inline by default');
        $inline = new Token('*', '*', null, null, false, true);
        $this->assertTrue($inline->isInline(), 'inline token');
    }

    /**
     * @param Token $token
     * @depends testBasic
     */
    public function testNoChild($token)
    {
        $this->expectException(\OutOfBoundsException::class);
        $token->getChild(0);
    }
}
