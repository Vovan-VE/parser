<?php
namespace VovanVE\parser\tests\unit\grammar\exporter;

use VovanVE\parser\grammar\exporter\JsonExporter;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class JsonExporterTest extends BaseTestCase
{
    /**
     * @param Grammar $grammar
     * @param array $expected
     * @dataProvider jsonDataProvider
     */
    public function testExportGrammar($grammar, $expected)
    {
        $exporter = new JsonExporter();
        $actual = $exporter->exportGrammar($grammar);
        $this->assertEquals($expected, $actual);
    }

    public function jsonDataProvider()
    {
        return [
            [
                TextLoader::createGrammar('G: a $'),
                '{"rules":[{"name":"G","eof":true,"definition":["a"]}],"terminals":[{"name":"a"}]}'
            ],
            [
                TextLoader::createGrammar('G: A $ ; A (loop) : A a ; A: a; A: b; A: "x"; A: .c; a: "y"; b: /\\d+/'),
                '{"rules":[{"name":"G","eof":true,"definition":["A"]},{"name":"A","tag":"loop","definition":["A","a"]},{"name":"A","definition":["a"]},{"name":"A","definition":["b"]},{"name":"A","definition":["x"]},{"name":"A","definition":[{"name":"c","hidden":true}]}],"terminals":[{"name":"a","match":"y","isText":true},{"name":"b","match":"\\\\d+"},{"name":"c"},"x"]}'
            ],
        ];
    }
}
