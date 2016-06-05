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
    public $futher;

    /**
     * @param self $a
     * @param self $b
     * @return integer
     */
    public static function compare(self $a, self $b)
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->passed, $b->passed)
            ?: Symbol::compareList($a->futher, $b->futher)
            ?: ($b->eof - $a->eof);
    }

    /**
     * @param Rule $rule
     * @return static
     */
    public static function createFromRule($rule)
    {
        return new static($rule->subject, [], $rule->definition, $rule->eof);
    }

    /**
     * @param Symbol $subject
     * @param Symbol[] $passed
     * @param Symbol[] $futher
     * @param bool $eof
     */
    public function __construct($subject, $passed = [], $futher = [], $eof = false)
    {
        parent::__construct($subject, $eof);

        $this->passed = array_values($passed);
        $this->futher = array_values($futher);
    }

    /**
     * @return Symbol|null
     */
    public function getExpected()
    {
        return ($this->futher) ? $this->futher[0] : null;
    }

    /**
     * @return static|null
     */
    public function shift()
    {
        $futher = $this->futher;
        if (!$futher) {
            return null;
        }
        $passed = $this->passed;

        $passed[] = array_shift($futher);
        return new static($this->subject, $passed, $futher, $this->eof);
    }

    /**
     * @return Rule
     */
    public function getAsRule()
    {
        return new Rule($this->subject, array_merge($this->passed, $this->futher), $this->eof);
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
            $this->futher
        ));
    }
}
