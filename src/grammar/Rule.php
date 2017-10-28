<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseRule;
use VovanVE\parser\common\Symbol;

/**
 * A Rule of a grammar
 * @package VovanVE\parser
 */
class Rule extends BaseRule
{
    /** @var Symbol[] Definition body symbols */
    private $definition;

    /**
     * Compare two rules
     *
     * Rules are equal when its' subject symbols, definition lists and EOF marker are equal.
     * Equality of tags is not required by default, and is under control of `$checkTag` argument.
     * @param Rule $a
     * @param Rule $b
     * @param bool $checkTag [since 1.3.0] Whether to check tags too.
     * Default is false to ignore tags
     * @return int Returns 0 when rules are equal
     */
    public static function compare($a, $b, $checkTag = false)
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->definition, $b->definition)
                ?: ($b->eof - $a->eof)
                    ?: ($checkTag ? self::compareTag($a->tag, $b->tag) : 0);
    }

    /**
     * @param Symbol $subject Subject of the rule
     * @param Symbol[] $definition Definition body symbols
     * @param bool $eof Must EOF be found in the end of input text
     * @param string|null $tag [since 1.3.0] Optional tag name in addition to subject
     */
    public function __construct($subject, array $definition, $eof = false, $tag = null)
    {
        parent::__construct($subject, $eof, $tag);

        $this->definition = $definition;
    }

    /**
     * Definition body symbols
     * @return Symbol[]
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @inheritdoc
     */
    protected function toStringContent()
    {
        return join(self::DUMP_SPACE, $this->definition);
    }
}
