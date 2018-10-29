<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\grammar\Rule;

/**
 * A row of parser states table
 * @package VovanVE\parser
 * @see https://en.wikipedia.org/wiki/LR_parser
 */
class TableRow extends BaseObject
{
    /**
     * EOF action
     *
     * * true - accept
     * * null - no action
     * @var true|null
     */
    public $eofAction;
    /**
     * Terminal actions. Key is symbol name. Value is state index to shift to.
     * @var int[]|array
     */
    public $terminalActions = [];
    /**
     * Goto switches. Key is symbol name. Value is state index to shift to.
     * @var int[]|array
     */
    public $gotoSwitches = [];
    /** @var Rule|null Rule to reduce by if any */
    public $reduceRule;

    /**
     * Whether the row is for reduce
     * @return bool
     * @since 1.5.0
     */
    public function isReduceOnly(): bool
    {
        return !$this->eofAction
            && !$this->terminalActions
            && !$this->gotoSwitches
            && $this->reduceRule;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $out = [];
        if (true === $this->eofAction) {
            $out[] = '{eof}->accept';
        }

        foreach (
            [
                'actions' => $this->terminalActions,
                'goto' => $this->gotoSwitches,
            ]
            as $type => $map
        ) {
            if ($map) {
                $sub_out = [];
                foreach ($map as $name => $index) {
                    if (is_int($index)) {
                        $sub_out[] = Symbol::dumpName($name) . '->' . $index;
                    }
                }
                $out[] = $type . ': ' . join(', ', $sub_out);
            }
        }

        return ($out) ? join(' | ', $out) : 'reduce';
    }
}
