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
                TextLoader::createGrammar('G: a $; a: /a+/'),
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
                            'match' => 'a+',
                        ],
                    ],
                ]
            ],
            [
                TextLoader::createGrammar('
                    G   : A $
                    A   : Foo
                    A   : Bar
                    Foo : /\d+/
                    Bar : /[a-z][a-z\d]*/
                '),
                [
                    'rules' => [
                        [
                            'name' => 'G',
                            'eof' => true,
                            'definition' => ['A'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => ['Foo'],
                        ],
                        [
                            'name' => 'A',
                            'definition' => ['Bar'],
                        ],
                    ],
                    'terminals' => [
                        [
                            'name' => 'Foo',
                            'match' => '\\d+',
                        ],
                        [
                            'name' => 'Bar',
                            'match' => '[a-z][a-z\\d]*',
                        ],
                    ],
                ]
            ],
            [
                TextLoader::createGrammar(<<<'_END'
                    G      : A $
                    A      : D
                    A(loop): A a
                    A      : a
                    A      : b
                    A      : "x"
                    A      : .c
                    a      : "y"
                    b      : /(?&int)/
                    c      : /c+/
                    .D     : d
                    .d     : /d+/
                    &int   : /\d+/
                    -ws    : /\s+/
                    -ws    : /#.*/
                    -mod   : 'iu'
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
                            'definition' => [
                                ['name' => 'D', 'hidden' => true],
                            ],
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
                        [
                            'name' => 'D',
                            'definition' => [['name' => 'd', 'hidden' => true]],
                        ],
                    ],
                    'terminals' => [
                        'x',
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
                            'match' => 'c+',
                        ],
                        [
                            'name' => 'd',
                            'match' => 'd+',
                        ],
                    ],
                    'defines' => [
                        'int' => '\\d+'
                    ],
                    'whitespaces' => [
                        '\\s+',
                        '#.*',
                    ],
                    'modifiers' => 'iu',
                ]
            ],
        ];
    }
}
