<?php
namespace VovanVE\parser\tests\unit\grammar\loaders;

use VovanVE\parser\grammar\exporter\JsonExporter;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\loaders\JsonLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class JsonLoaderTest extends BaseTestCase
{
    /**
     * @param string $json
     * @dataProvider jsonDataProvider
     */
    public function testCreateGrammar($json)
    {
        $grammar = JsonLoader::createGrammar($json);
        $this->assertInstanceOf(Grammar::class, $grammar);
        $this->assertEquals($json, (new JsonExporter)->exportGrammar($grammar));
    }

    public function jsonDataProvider()
    {
        return [
            ['{"rules":[{"name":"G","eof":true,"definition":["a"]}],"terminals":[{"name":"a","match":"\\\\d+"}],"defines":{"int":"\\\\d+","var":"[a-z]+"},"whitespaces":["\\\\s+"],"modifiers":"u"}'],
            ['{"rules":[{"name":"G","eof":true,"definition":["A"]},{"name":"A","tag":"loop","definition":["A","a"]},{"name":"A","definition":["a"]},{"name":"A","definition":["b"]},{"name":"A","definition":["x"]},{"name":"A","definition":[{"name":"c","hidden":true}]}],"terminals":[{"name":"a","match":"y","isText":true},{"name":"b","match":"\\\\d+"},{"name":"c","match":"z"},"x"]}'],
        ];
    }
}
