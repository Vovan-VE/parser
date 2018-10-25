<?php
namespace VovanVE\parser\tests\unit\grammar\exporter;

use VovanVE\parser\grammar\exporter\ArrayExporter;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\loaders\TextLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class ArrayExporterTest extends BaseTestCase
{
    /**
     * @param Grammar $grammar
     * @param array $expected
     * @dataProvider arrayDataProvider
     */
    public function testExportGrammar(Grammar $grammar, array $expected)
    {
        $exporter = new ArrayExporter();
        $actual = $exporter->exportGrammar($grammar);
        $this->assertEquals($expected, $actual);
    }

    public function arrayDataProvider(): array
    {
        return [
            [
                TextLoader::createGrammar('G: a $'),
                [
                    'rules' => [
                        [
                            'name' => 'G',
                            'eof' => true,
                            'definition' => ['a'],
                        ],
                    ],
                    'terminals' => [
                        [
                            'name' => 'a',
                        ],
                    ],
                ]
            ],
            [
                TextLoader::createGrammar(<<<'_END'
                    G      : A $
                    A(loop): A a
                    A      : a
                    A      : b
                    A      : "x"
                    A      : .c
                    a      : "y"
                    b      : /(?&int)/
                    &int   : /\d+/
_END
                ),
                [
                    'rules' => [
                        [
                            'name' => 'G',
                            'eof' => true,
                            'definition' => ['A'],
                        ],
                        [
                            'name' => 'A',
                            'tag' => 'loop',
                            'definition' => ['A', 'a'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => ['a'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => ['b'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => ['x'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => [['name' => 'c', 'hidden' => true]],
                        ],
                    ],
                    'terminals' => [
                        [
                            'name' => 'a',
                            'match' => 'y',
                            'isText' => true,
                        ],
                        [
                            'name' => 'b',
                            'match' => '(?&int)',
                        ],
                        [
                            'name' => 'c',
                        ],
                        'x',
                    ],
                    'defines' => [
                        'int' => '\\d+'
                    ],
                ]
            ],
        ];
    }
}
