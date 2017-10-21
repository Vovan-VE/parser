<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseRule;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Rule;

class Item extends BaseRule
{
    /** @var Symbol[] */
    public $passed;
    /** @var Symbol[] */
    public $further;

    /**
     * @param Item $a
     * @param Item $b
     * @return integer
     */
    public static function compare($a, $b)
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->passed, $b->passed)
                ?: Symbol::compareList($a->further, $b->further)
                    ?: ($b->eof - $a->eof);
    }

    /**
     * @param Rule $rule
     * @return static
     */
    public static function createFromRule($rule)
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
     * @param Symbol $subject
     * @param Symbol[] $passed
     * @param Symbol[] $further
     * @param bool $eof
     * @param string|null $tag [since 1.3.0]
     */
    public function __construct(
        $subject,
        $passed = [],
        $further = [],
        $eof = false,
        $tag = null
    ) {
        parent::__construct($subject, $eof, $tag);

        $this->passed = array_values($passed);
        $this->further = array_values($further);
    }

    /**
     * @return Symbol|null
     */
    public function getExpected()
    {
        return ($this->further) ? $this->further[0] : null;
    }

    /**
     * @return static|null
     */
    public function shift()
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
     * @return Rule
     */
    public function getAsRule()
    {
        return new Rule(
            $this->subject,
            array_merge($this->passed, $this->further),
            $this->eof,
            $this->tag
        );
    }

    const DUMP_MARKER = '.';

    /**
     * @inheritdoc
     */
    protected function toStringContent()
    {
        return join(self::DUMP_SPACE, array_merge(
            $this->passed,
            [self::DUMP_MARKER],
            $this->further
        ));
    }
}
