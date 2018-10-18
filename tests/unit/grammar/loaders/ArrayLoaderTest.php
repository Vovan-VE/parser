<?php
namespace VovanVE\parser\tests\unit\grammar\loaders;

use VovanVE\parser\grammar\exporter\ArrayExporter;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\loaders\ArrayLoader;
use VovanVE\parser\tests\helpers\BaseTestCase;

class ArrayLoaderTest extends BaseTestCase
{
    /**
     * @param array $array
     * @dataProvider arrayDataProvider
     */
    public function testCreateGrammar($array)
    {
        $grammar = ArrayLoader::createGrammar($array);
        $this->assertInstanceOf(Grammar::class, $grammar);
        $this->assertEquals($array, (new ArrayExporter)->exportGrammar($grammar));
    }

    public function arrayDataProvider()
    {
        return [
            [
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
                            'match' => '\\d+',
                        ],
                    ],
                ]
            ],
            [
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
                            'match' => '\\d+',
                        ],
                        [
                            'name' => 'c',
                            'match' => 'z',
                        ],
                        'x',
                    ],
                ]
            ],
        ];
    }
}
