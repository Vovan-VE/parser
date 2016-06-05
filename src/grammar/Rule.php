<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseRule;
use VovanVE\parser\common\Symbol;

class Rule extends BaseRule
{
    /** @var Symbol[] */
    public $definition;

    /**
     * @param self $a
     * @param self $b
     * @return integer
     */
    public static function compare(self $a, self $b)
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->definition, $b->definition)
            ?: ($b->eof - $a->eof);
    }

    /**
     * @param Symbol $subject
     * @param Symbol[] $definition
     */
    public function __construct($subject, array $definition, $eof = false)
    {
        parent::__construct($subject, $eof);

        $this->definition = $definition;
    }

    /**
     * @inheritdoc
     */
    protected function toStringContent()
    {
        return join(self::DUMP_SPACE, $this->definition);
    }
}
