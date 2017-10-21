<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseRule;
use VovanVE\parser\common\Symbol;

class Rule extends BaseRule
{
    /** @var Symbol[] */
    private $definition;

    /**
     * @param Rule $a
     * @param Rule $b
     * @param bool $checkTag [since 1.3.0]
     * @return int
     */
    public static function compare($a, $b, $checkTag = false)
    {
        return Symbol::compare($a->subject, $b->subject)
            ?: Symbol::compareList($a->definition, $b->definition)
                ?: ($b->eof - $a->eof)
                    ?: ($checkTag ? self::compareTag($a->tag, $b->tag) : 0);
    }

    /**
     * @param Symbol $subject
     * @param Symbol[] $definition
     * @param bool $eof
     * @param string|null $tag [since 1.3.0]
     */
    public function __construct($subject, array $definition, $eof = false, $tag = null)
    {
        parent::__construct($subject, $eof, $tag);

        $this->definition = $definition;
    }

    /**
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
