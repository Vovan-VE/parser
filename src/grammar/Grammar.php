<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;

class Grammar extends BaseObject
{
    const RE_INPUT_RULE = '/
        (?(DEFINE)
            (?<name> [a-z][a-z_0-9]*+ )
        )
        ^
        (?<subj> (?&name) )
        \\s*+
        (?:
            \(
            \\s*+
            (?<tag> (?&name) )
            \\s*+
            \)
            \\s*+
        )?
        :
        \\s*+
        (?<def> (?: [^$] | \\$ (?! \\s*+ $ ) )++ )
        (?<eof> \\$ )?
        $
    /xi';

    const RE_RULE_DEF_ITEM = '/
        \\G
        \\s*+
        (?:
            (?:
                (?<word> \\.? [a-z][a-z_0-9]*+  )
            |   (?<q>    " [^"]+ "
                |       \' [^\']+ \'
                |        < [^<>]+ >  )
            )
            \\s*+
        |
            (?<end> $ )
        )
    /xi';

    /** @var Rule[] */
    private $rules;
    /** @var string[] */
    private $inlines = [];
    /** @var Rule */
    private $mainRule;
    /** @var Symbol[] */
    private $symbols;
    /** @var Symbol[] */
    private $terminals;
    /** @var Symbol[] */
    private $nonTerminals;

    /**
     * @param string $text
     * @return static
     */
    public static function create($text)
    {
        $rules_strings = preg_split(
            '/[;\\r\\n\\f]+/u',
            $text,
            0,
            PREG_SPLIT_NO_EMPTY
        );
        $rules_strings = preg_replace('/^\\s+|\\s+$/u', '', $rules_strings);

        $inlines = [];
        $rules = [];

        $symbols = [];
        $get_symbol = function ($name, $isInline = false) use (&$symbols, &$inlines) {
            if ($isInline) {
                if (!isset($inlines[$name]) && isset($symbols[$name])) {
                    throw new \InvalidArgumentException(
                        "Inline '$name' conflicts with token <$name> defined previously"
                    );
                }
                $inlines[$name] = $name;
                $plain_name = $name;
                $is_hidden = true;
            } else {
                $is_hidden = '.' === mb_substr($name, 0, 1, '8bit');
                $plain_name = $is_hidden
                    ? mb_substr($name, 1, null, '8bit')
                    : $name;

                if (isset($inlines[$plain_name])) {
                    throw new \InvalidArgumentException(
                        "Token <$plain_name> conflicts with inline '$plain_name' defined previously"
                    );
                }
            }

            return (isset($symbols[$plain_name][$is_hidden]))
                ? $symbols[$plain_name][$is_hidden]
                : ($symbols[$plain_name][$is_hidden] = new Symbol($plain_name, true, $is_hidden));
        };

        foreach ($rules_strings as $rule_string) {
            if ('' === $rule_string) {
                continue;
            }

            try {
                if (!preg_match(self::RE_INPUT_RULE, $rule_string, $match)) {
                    throw new \InvalidArgumentException("Invalid rule format");
                }

                /** @var Symbol $subject */
                $subject = $get_symbol($match['subj']);
                $subject->setIsTerminal(false);

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
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException($e->getMessage() . " - rule '$rule_string'");
            }

            $rules[] = new Rule(
                $subject,
                $definition,
                $eof,
                isset($match['tag']) ? $match['tag'] : null
            );
        }

        return new static($rules, $inlines);
    }

    /**
     * @param Rule[] $rules
     * @param string[] $inlines [since 1.3.2]
     */
    public function __construct(array $rules, array $inlines = [])
    {
        $this->rules = $rules;
        $this->inlines = array_values($inlines);
        $symbols = [];
        $terminals = [];
        $non_terminals = [];

        foreach ($rules as $rule) {
            if ($rule->hasEofMark()) {
                if ($this->mainRule) {
                    throw new GrammarException(
                        'Only one rule must to allow EOF'
                    );
                } else {
                    $this->mainRule = $rule;
                }
            }
            foreach (
                array_merge([$rule->getSubject()], $rule->getDefinition())
                as $symbol
            ) {
                /** @var Symbol $symbol */
                $symbol_name = $symbol->getName();
                if (!isset($symbols[$symbol_name])) {
                    $symbols[$symbol_name] = $symbol;
                    if ($symbol->isTerminal()) {
                        $terminals[$symbol_name] = $symbol;
                    } else {
                        $non_terminals[$symbol_name] = $symbol;
                    }
                }
            }
        }
        if (!$this->mainRule) {
            throw new GrammarException(
                'Exactly one rule must to allow EOF - it will be main rule'
            );
        }
        if (!$terminals) {
            throw new GrammarException('No terminals');
        }
        $this->symbols = $symbols;
        $this->terminals = $terminals;
        $this->nonTerminals = $non_terminals;
    }

    /**
     * @return string[]
     * @since 1.3.2
     */
    public function getInlines()
    {
        return $this->inlines;
    }

    /**
     * @return Rule[]
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @return Rule
     */
    public function getMainRule()
    {
        return $this->mainRule;
    }

    /**
     * @return Symbol[]
     */
    public function getTerminals()
    {
        return $this->terminals;
    }

    /**
     * @return Symbol[]
     */
    public function getNonTerminals()
    {
        return $this->nonTerminals;
    }

    /**
     * @param string $name
     * @return Symbol|null
     */
    public function getSymbol($name)
    {
        return isset($this->symbols[$name]) ? $this->symbols[$name] : null;
    }

    /**
     * @param Symbol $subject
     * @return Rule[]
     */
    public function getRulesFor($subject)
    {
        $rules = [];
        if (!$subject->isTerminal()) {
            foreach ($this->rules as $rule) {
                if (0 === Symbol::compare($rule->getSubject(), $subject)) {
                    $rules[] = $rule;
                }
            }
        }
        return $rules;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return join(PHP_EOL, $this->rules);
    }

    /**
     * @param string $input
     * @param array $inlines
     * @return string[]
     * @since 1.3.2
     */
    private static function parseDefinitionItems($input, array &$inlines)
    {
        if (!preg_match_all(self::RE_RULE_DEF_ITEM, $input, $matches, PREG_SET_ORDER)) {
            throw new \InvalidArgumentException("Invalid rule definition");
        }

        $last_match = array_pop($matches);
        if (!isset($last_match['end'])) {
            throw new \InvalidArgumentException("Invalid rule definition");
        }

        $items = [];
        foreach ($matches as $match) {
            if (isset($match['q'])) {
                $inline = mb_substr($match['q'], 1, -1, '8bit');
                if (!isset($inlines[$inline]) && in_array($inline, $items, true)) {
                    throw new \InvalidArgumentException(
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
                    throw new \InvalidArgumentException(
                        "Token <$word> conflicts with inline token '$name'"
                    );
                }
            } else {
                throw new \LogicException('Unexpected item match');
            }
        }

        return $items;
    }

}
