<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseRule;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Rule;

/**
 * Item of parser state items set
 *
 * Item is a rule-like object, where definition body is spited by Current Position into
 * passed and further symbols. So each Rule can produce exactly N+1 Items where N is count
 * of symbols in Rule definition body.
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class Item extends BaseRule
{
    /** @var Symbol[] Already passed symbols behind current position */
    public $passed;
    /** @var Symbol[] Further symbols expected next to current position */
    public $further;

    /**
     * Compare two items
     *
     * Two items are equal when its both has equal subjects, passed and further symbols
     * and EOF marker.
     * @param Item $a
     * @param Item $b
     * @return int Returns 0 when items are equal
     */
    public static function compare(Item $a, Item $b): int
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->passed, $b->passed)
                ?: Symbol::compareList($a->further, $b->further)
                    ?: ($b->eof - $a->eof);
    }

    /**
     * Create Item from a Rule
     *
     * Current position is placed in the start of rule body.
     * @param Rule $rule Source rule
     * @return static Returns new Item
     */
    public static function createFromRule(Rule $rule): self
    {
        return new static(
            $rule->getSubject(),
            [],
            $rule->getDefinition(),
            $rule->hasEofMark(),
            $rule->getTag()
        );
    }

    /**
     * @param Symbol $subject Subject of a rule
     * @param Symbol[] $passed Already passed symbols behind current position
     * @param Symbol[] $further Further symbols expected next to current position
     * @param bool $eof EOF marker
     * @param string|null $tag [since 1.3.0] Tag name from the rule to use with actions
     */
    public function __construct(
        Symbol $subject,
        array $passed = [],
        array $further = [],
        bool $eof = false,
        ?string $tag = null
    ) {
        parent::__construct($subject, $eof, $tag);

        $this->passed = array_values($passed);
        $this->further = array_values($further);
    }

    /**
     * Get next expected Symbol if any
     * @return Symbol|null Next symbol expected after current position.
     * `null` when nothing more is expected.
     */
    public function getExpected(): ?Symbol
    {
        return ($this->further) ? $this->further[0] : null;
    }

    /**
     * Create new Item by shifting current position to the next symbol
     * @return static|null Returns new Item with current position shifted to the next symbol.
     * Returns `null` then there is not next expected symbol.
     */
    public function shift(): ?self
    {
        $further = $this->further;
        if (!$further) {
            return null;
        }
        $passed = $this->passed;

        $passed[] = array_shift($further);
        return new static($this->subject, $passed, $further, $this->eof, $this->tag);
    }

    /**
     * Reconstruct a source rule
     * @return Rule New rule object which is equal to source one
     */
    public function getAsRule(): Rule
    {
        return new Rule(
            $this->subject,
            array_merge($this->passed, $this->further),
            $this->eof,
            $this->tag
        );
    }

    private const DUMP_MARKER = '•';

    /**
     * @inheritdoc
     */
    protected function toStringContent(): string
    {
        return join(self::DUMP_SPACE, array_merge(
            $this->passed,
            [self::DUMP_MARKER],
            $this->further
        ));
    }
}
