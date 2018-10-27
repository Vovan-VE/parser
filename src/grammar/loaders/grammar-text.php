<?php
return [
    'rules' => [
        [
            'name' => 'Goal',
            'eof' => true,
            'definition' => ['Definitions'],
        ],
        [
            'name' => 'Definitions',
            'tag' => 'first',
            'definition' => ['DefinitionOrComment', 'DefinitionsContinue'],
        ],
        [
            'name' => 'Definitions',
            'tag' => 'only',
            'definition' => ['DefinitionOrComment'],
        ],
        [
            'name' => 'Definitions',
            'definition' => ['DefinitionsContinue'],
        ],
        [
            'name' => 'DefinitionsContinue',
            'tag' => 'list',
            'definition' => ['DefinitionsContinue', 'NextDefinition'],
        ],
        [
            'name' => 'DefinitionsContinue',
            'tag' => 'first',
            'definition' => ['NextDefinition'],
        ],
        [
            'name' => 'NextDefinition',
            'definition' => [
                [
                    'name' => 'separator',
                    'hidden' => true,
                ],
                'DefinitionOrComment',
            ],
        ],
        [
            'name' => 'NextDefinition',
            'tag' => 'empty',
            'definition' => [
                [
                    'name' => 'separator',
                    'hidden' => true,
                ],
            ],
        ],
        [
            'name' => 'DefinitionOrComment',
            'definition' => ['Definition'],
        ],
        [
            'name' => 'DefinitionOrComment',
            'tag' => 'empty',
            'definition' => [
                [
                    'name' => 'comment',
                    'hidden' => true,
                ],
            ],
        ],
        [
            'name' => 'Definition',
            'definition' => ['Define'],
        ],
        [
            'name' => 'Definition',
            'definition' => ['Option'],
        ],
        [
            'name' => 'Definition',
            'definition' => ['Rule'],
        ],
        [
            'name' => 'Define',
            'definition' => ['&', 'name', ':', 'regexp'],
        ],
        [
            'name' => 'Option',
            'definition' => ['-', 'name', ':', 'OptionValue'],
        ],
        [
            'name' => 'OptionValue',
            'tag' => 'str',
            'definition' => ['String'],
        ],
        [
            'name' => 'OptionValue',
            'tag' => 're',
            'definition' => ['regexp'],
        ],
        [
            'name' => 'Rule',
            'definition' => ['RuleSubjectTagged', ':', 'RuleDefinition'],
        ],
        [
            'name' => 'RuleSubjectTagged',
            'tag' => 'tag',
            'definition' => ['name', '(', 'name', ')'],
        ],
        [
            'name' => 'RuleSubjectTagged',
            'definition' => ['name'],
        ],
        [
            'name' => 'RuleDefinition',
            'tag' => 'regexp',
            'definition' => ['regexp'],
        ],
        [
            'name' => 'RuleDefinition',
            'tag' => 'main',
            'definition' => ['Symbols', '$'],
        ],
        [
            'name' => 'RuleDefinition',
            'definition' => ['Symbols'],
        ],
        [
            'name' => 'Symbols',
            'tag' => 'list',
            'definition' => ['Symbols', 'Symbol'],
        ],
        [
            'name' => 'Symbols',
            'tag' => 'first',
            'definition' => ['Symbol'],
        ],
        [
            'name' => 'Symbol',
            'tag' => 'name',
            'definition' => ['SymbolNamed'],
        ],
        [
            'name' => 'Symbol',
            'tag' => 'string',
            'definition' => ['String'],
        ],
        [
            'name' => 'SymbolNamed',
            'tag' => 'hidden',
            'definition' => ['.', 'name'],
        ],
        [
            'name' => 'SymbolNamed',
            'tag' => 'normal',
            'definition' => ['name'],
        ],
        [
            'name' => 'String',
            'definition' => ['qstring'],
        ],
        [
            'name' => 'String',
            'definition' => ['qqstring'],
        ],
        [
            'name' => 'String',
            'definition' => ['angle_string'],
        ],
    ],
    'terminals' => [
        '$',
        '&',
        '(',
        ')',
        '-',
        '.',
        ':',
        [
            'name' => 'angle_string',
            'match' => '<[^<>\\v]*>',
        ],
        [
            'name' => 'comment',
            'match' => '#[^\\v]++',
        ],
        [
            'name' => 'name',
            'match' => '(?i)[a-z][a-z_0-9]*+',
        ],
        [
            'name' => 'qqstring',
            'match' => '"[^"\\v]*"',
        ],
        [
            'name' => 'qstring',
            'match' => '\'[^\'\\v]*\'',
        ],
        [
            'name' => 'regexp',
            'match' => '\\/(?:[^\\v\\/\\\\]++|\\\\[^\\v])++\\/',
        ],
        [
            'name' => 'separator',
            'match' => '(?:;|\\R)++',
        ],
    ],
];
