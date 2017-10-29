<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
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
     * @var integer[]
     */
    public $terminalActions = [];
    /**
     * Goto switches. Key is symbol name. Value is state index to shift to.
     * @var integer[]
     */
    public $gotoSwitches = [];
    /** @var Rule|null Rule to reduce by if any */
    public $reduceRule;

    /**
     * @return string
     */
    public function __toString()
    {
        $out = [];
        if (true === $this->eofAction) {
            $out[] = '{eof}->accept';
        }

        foreach ([$this->terminalActions, $this->gotoSwitches] as $map) {
            foreach ($map as $name => $index) {
                if (is_int($index)) {
                    $out[] = $name . '->' . $index;
                }
            }
        }

        return ($out) ? join('; ', $out) : 'reduce';
    }
}
