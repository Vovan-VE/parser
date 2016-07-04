<?php
namespace VovanVE\parser\tests\unit\common;

use VovanVE\parser\common\Token;
use VovanVE\parser\tests\helpers\BaseTestCase;

class TokenTest extends BaseTestCase
{
    public function testDumpAsString()
    {
        $type = 'foo';
        $content = 'bar';

        $token = new Token($type, $content);

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
    }
}
