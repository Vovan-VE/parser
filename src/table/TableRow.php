<?php
namespace VovanVE\parser\table;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\grammar\Rule;

class TableRow extends BaseObject
{
    /**
     * @var true|null
     * - true - accept
     * - null - no action
     */
    public $eofAction;
    /**
     * @var integer[]
     * array of:
     * - integer - shift to the state
     */
    public $terminalActions = [];
    /**
     * @var integer[]
     * array of:
     * - integer - shift to the state
     */
    public $gotoSwitches = [];
    /** @var Rule|null */
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
