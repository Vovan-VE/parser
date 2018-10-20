<?php
namespace VovanVE\parser\grammar\loaders;

use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;
use VovanVE\parser\grammar\Rule;

/**
 * Class TextLoader
 *
 * Grammar object can be easily created with plain text like so:
 *
 * ```php
 * $grammar = Grammar::create(<<<'_END'
 *     Goal        : Sum $
 *     Sum(add)    : Sum "+" Product
 *     Sum(sub)    : Sum "-" Product
 *     Sum(P)      : Product
 *     Product(mul): Product "*" Value
 *     Product(div): Product "/" Value
 *     Product(V)  : Value
 *     Value(neg)  : "-" Value
 *     Value       : "+" Value
 *     Value       : "(" Sum ")"
 *     Value       : int
 *     int         : /\d+/
 * _END
 * );
 * ```
 *
 * Rules in grammar text can be separated either by new-line characters or semicolon `;`.
 *
 * Here is an example rule:
 *
 * ```
 * Subject ( tag ) : foo "+" .bar
 * Subject         : "+"
 * Subject         : /regexp/
 * ```
 *
 * Explanation:
 *
 * *   `Subject` - subject symbol of a rule
 * *   `( tag )` - optional tag to reference rule(s) from actions
 * *   `foo "+" .bar` - definition body - space separated list of tokens
 *     *   `foo` - normal symbol
 *     *   `"+"` - inline defined token. Can be `"..."`, `'...'` or `<...>`. There is no escaping
 *         or something similar. So, you cannot use `"` inside `"..."`, `'` inside `'...'`
 *         and `<` or `>` inside `<...>`.
 *     *   `.bar` - hidden symbol
 * *   `/regexp/` - RegExp definition for terminal. Usage of NOWDOC for grammar text
 *         lets you to avoid double escaping. RegExp definition introduced since 1.5.0.
 * *   `$` - EOF marker for main rule.
 *
 * Here is some terms:
 *
 * *   Grammar must to contain exactly one main rule which has EOF marker `$` in the end.
 *     Subject symbol name for the main rule currently does not matter, but must be present for
 *     consistency and must be unique. Say it can be for example `Goal` or `TOP`.
 *     Tag `(tag)` for the main rule also has no usage.
 * *   Order of rules in a grammar does not matter, but regexp definitions order may
 *     have difference in some cases.
 * *   All symbols which are subjects of rules will become non-terminal.
 * *   Named symbols are case sensitive. It is recommended, but not required, to use
 *     lower case names (`foo`) for terminals and upper CamelCase (`FooBar`) for non-terminals.
 * *   Inline quoted terminals are always hidden. It will not produce a nodes in the resulting tree.
 * *   Named symbols can be marked as hidden too. Add a dot `.` in front of name: `.foo`.
 *     This can be done locally in a rule (both for terminals and non-terminals) or "globally"
 *     in Lexer (for terminals only).
 * *   Rule with the only inline token and without tag like `name: "text"` can be removed
 *     internally to define fixed terminal in Lexer instead when the inline has no other
 *     references and subject symbol has no other rules. This feature is introduced since 1.5.0.
 * *   RegExp definition must be the only item in a rule: `name: /regexp/`
 * *   RegExp subject symbol must not have any definitions in another rules.
 *
 * @package VovanVE\parser
 * @since 1.7.0
 */
class TextLoader
{
    // REFACT: const expression: extract and reuse defines

    private const RE_RULE_LINE = <<<'_REGEXP'
~
    \G
    \h*+
    (?<rule>
        (?: [^\v;"'<>/]++
        |   " [^\v"]*+   (?: " | $ )
        |   ' [^\v']*+   (?: ' | $ )
        |   < [^\v<>]*+  (?: > | $ )
        |   /
            (?: [^\v/\\]++
            |   \\ [^\v]
            )*+
            \\?+
            (?: / | $ )
        )*+
    )
    (?= $ | [\v;])
    [\v;]*+
    (?<eof> $ )?
~xD
_REGEXP;

    private const RE_INPUT_RULE = <<<'_REGEXP'
/
    (?(DEFINE)
        (?<name> [a-z][a-z_0-9]*+ )
    )
    ^
    (?<subj> (?&name) )
    \s*+
    (?:
        \(
        \s*+
        (?<tag> (?&name) )
        \s*+
        \)
        \s*+
    )?
    :
    \s*+
    (?<def>
        (?: [^$]++
        |   \$ (?! \s*+ $ )
        )++
    )
    (?<eof> \$ )?
    $
/xi
_REGEXP;

    private const RE_RULE_DEF_ITEM = <<<'_REGEXP'
/
    \G
    \s*+
    (?:
        (?: (?<word> \.? [a-z][a-z_0-9]*+  )
        |
            (?<q> " [^"]+ "
            |     ' [^']+ '
            |     < [^<>]+ >
            )
        )
        \s*+
    |
        (?<end> $ )
    )
/xi
_REGEXP;

    private const RE_RULE_DEF_REGEXP = '~
        ^
        /
        (?<re>
            (?:
                [^/\\\\]++
            |
                \\\\.
            )*+
        )
        (?<closed> / )?+
        (?<end> $ )?
    ~x';

    /**
     * Create grammar object from a text
     *
     * See class description for details.
     * @param string $text Grammar definition text
     * @return Grammar Grammar object
     * @throws GrammarException Errors in grammar syntax or logic
     */
    public static function createGrammar($text)
    {
        $rules_strings = self::splitIntoRules($text);

        $inlines = [];
        $rules = [];

        $inline_ref_count = [];
        /** @var Rule[] $inline_ref_rule */
        $inline_ref_rule = [];
        $subject_rules_count = [];

        /** @var Symbol[][] $symbols */
        $symbols = [];
        $get_symbol = function ($name, $isInline = false) use (&$symbols, &$inlines) {
            if ($isInline) {
                if (!isset($inlines[$name]) && isset($symbols[$name])) {
                    throw new GrammarException(
                        "Inline '$name' conflicts with token <$name> defined previously"
                    );
                }
                $inlines[$name] = $name;
                $plain_name = $name;
                $is_hidden = true;
            } else {
                $is_hidden = '.' === $name[0];
                $plain_name = $is_hidden
                    ? substr($name, 1)
                    : $name;

                if (isset($inlines[$plain_name])) {
                    throw new GrammarException(
                        "Token <$plain_name> conflicts with inline '$plain_name' defined previously"
                    );
                }
            }

            return (isset($symbols[$plain_name][$is_hidden]))
                ? $symbols[$plain_name][$is_hidden]
                : ($symbols[$plain_name][$is_hidden] = new Symbol($plain_name, true, $is_hidden));
        };

        $non_terminals_names = [];
        $regexp_map = [];

        foreach ($rules_strings as $rule_string) {
            if ('' === $rule_string) {
                continue;
            }

            try {
                if (!preg_match(self::RE_INPUT_RULE, $rule_string, $match)) {
                    throw new GrammarException("Invalid rule format");
                }

                $subject_name = $match['subj'];

                /** @var Symbol $subject */

                $regexp_definition = self::matchRegexpDefinition($match['def']);
                if (null !== $regexp_definition) {
                    if (isset($match['tag']) && '' !== $match['tag']) {
                        throw new GrammarException("Rule tag cannot be used in RegExp rules");
                    }

                    if (isset($regexp_map[$subject_name])) {
                        throw new GrammarException("Duplicate RegExp rule for symbol `$subject_name`");
                    }

                    $get_symbol($subject_name);
                    $regexp_map[$subject_name] = $regexp_definition;
                    continue;
                }

                $subject = $get_symbol($subject_name);
                $subject->setIsTerminal(false);
                $non_terminals_names[$subject->getName()] = true;

                $rule_inlines = [];
                $definition_list = self::parseDefinitionItems($match['def'], $rule_inlines);

                $definition = [];
                foreach ($definition_list as $definition_item) {
                    $definition[] = $get_symbol(
                        $definition_item,
                        isset($rule_inlines[$definition_item])
                    );
                }

                $eof = !empty($match['eof']);
            } catch (GrammarException $e) {
                throw new GrammarException($e->getMessage() . " - rule '$rule_string'");
            }

            $rule = new Rule(
                $subject,
                $definition,
                $eof,
                isset($match['tag']) ? $match['tag'] : null
            );
            $rules[] = $rule;

            $subject_rules_count[$subject_name] = ($subject_rules_count[$subject_name] ?? 0) + 1;

            foreach ($rule_inlines as $inline_token) {
                $inline_ref_count[$inline_token] = ($inline_ref_count[$inline_token] ?? 0) + 1;

                // it is needed in case of the only reference,
                // so array of an subject is not needed
                $inline_ref_rule[$inline_token] = $rule;
            }
        }

        // Hidden symbols all are terminals still
        foreach ($non_terminals_names as $name => $_) {
            if (isset($symbols[$name][true])) {
                $symbols[$name][true]->setIsTerminal(false);
            }
        }

        // RegExp tokens cannot be non-terminals in the same time
        // since there is no anonymous RegExp terminals
        foreach (array_intersect_key($regexp_map, $non_terminals_names) as $name => $_) {
            throw new GrammarException(
                "Symbol `$name` defined as non-terminal and as regexp terminal in the same time"
            );
        }

        // Convert non-terminals defined only once with inline tokens
        // `Subject: "inline"`
        // into fixed terminals
        $fixed = [];
        foreach ($inline_ref_count as $inline_token => $ref_count) {
            if (1 === $ref_count) {
                $rule = $inline_ref_rule[$inline_token];
                if (1 === count($rule->getDefinition()) && null === $rule->getTag()) {
                    $subject = $rule->getSubject();
                    $name = $subject->getName();

                    if (1 === $subject_rules_count[$name]) {
                        // remove rule
                        $key = array_search($rule, $rules, true);
                        if (false !== $key) {
                            unset($rules[$key]);
                        }

                        // remove inline
                        unset($inlines[$inline_token]);

                        // make terminal
                        $subject->setIsTerminal(true);
                        if (isset($symbols[$name][true])) {
                            $symbols[$name][true]->setIsTerminal(true);
                        }

                        $fixed[$name] = $inline_token;
                    }
                }
            }
        }

        return new Grammar($rules, $inlines, $fixed, $regexp_map);
    }

    /**
     * @param string $grammarText
     * @return array|false|mixed|string[]
     */
    private static function splitIntoRules($grammarText)
    {
        if (false === preg_match_all(self::RE_RULE_LINE, $grammarText, $matches, PREG_SET_ORDER)) {
            throw new InternalException('PCRE error', preg_last_error());
        }

        if (!$matches) {
            throw new GrammarException('Cannot parse grammar text near start');
        }

        $last_match = $matches[count($matches) - 1];

        $rules = array_column($matches, 'rule');
        $rules = preg_replace('/^\\s+|\\s+$/u', '', $rules);
        $rules = array_filter($rules, 'strlen');

        if (!isset($last_match['eof'])) {
            if ($rules) {
                throw new GrammarException(
                    "Cannot parse grammar after rule `{$rules[count($rules) - 1]}`"
                );
            }
            throw new GrammarException('Could not parse grammar after some empty start');
        }
        return $rules;
    }

    /**
     * Parse rule definition body into tokens as strings
     * @param string $input Input string with rule definition body
     * @param array $inlines Variable to store values of inline tokens. Key are same as values
     * @return string[] List of tokens strings
     */
    private static function parseDefinitionItems($input, array &$inlines)
    {
        if (!preg_match_all(self::RE_RULE_DEF_ITEM, $input, $matches, PREG_SET_ORDER)) {
            throw new GrammarException("Invalid rule definition");
        }

        $last_match = array_pop($matches);
        if (!isset($last_match['end'])) {
            throw new GrammarException("Invalid rule definition");
        }

        $items = [];
        foreach ($matches as $match) {
            if (isset($match['q'])) {
                $inline = substr($match['q'], 1, -1);
                if (!isset($inlines[$inline]) && in_array($inline, $items, true)) {
                    throw new GrammarException(
                        "Inline token {$match['q']} conflicts with token <$inline>"
                    );
                }

                $items[] = $inline;
                $inlines[$inline] = $inline;
            } elseif (isset($match['word'])) {
                $word = $match['word'];
                $items[] = $word;

                $name = ltrim($word, '.');
                if (isset($inlines[$name])) {
                    throw new GrammarException(
                        "Token <$word> conflicts with inline token '$name'"
                    );
                }
            } else {
                throw new InternalException('Unexpected item match');
            }
        }

        return $items;
    }

    /**
     * Try to parse rule definition as RegExp rule
     *
     * RegExp rule must to consist of a RegExp only: `/.../`.
     *
     * If input definition does not begin with delimiter `/`,
     * parsing is successfully discarded with returning `null`.
     *
     * Otherwise parsed RegExp given between delimiters will
     * be returned is it parsed successfully. In case of error
     * an `GrammarException` will be thrown.
     *
     * > Note: RegExp body itself will not be checked or parsed
     * > in details.
     *
     * > Note: Escaped slash `\/` (`'\\/'` in string literal) **can** be used.
     * @param string $input Input rule body from grammar text
     * @return string|null RegExp body without delimiters. `null` will be returned
     * when input definition does not begin with RegExp delimiter `/`.
     * @throws GrammarException RegExp syntax is started with delimiter but then comes an error.
     */
    private static function matchRegexpDefinition($input)
    {
        if (!preg_match(self::RE_RULE_DEF_REGEXP, $input, $match)) {
            // self failure check
            if ('' !== $input && '/' === $input[0]) {
                throw new InternalException('RegExp definition start `/...` did not match');
            }

            return null;
        }

        if (!isset($match['closed']) || '' === $match['closed']) {
            throw new GrammarException('RegExp definition is not terminated with final delimiter');
        }
        if (!isset($match['end'])) {
            throw new GrammarException('RegExp definition must be the only in a rule');
        }

        $regexp_part = $match['re'];
        if ('' === $regexp_part) {
            throw new GrammarException('Empty RegExp definition');
        }

        return $regexp_part;
    }
}
