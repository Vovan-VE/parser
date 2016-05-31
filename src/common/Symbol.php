<?php
namespace VovanVE\parser\common;

class Symbol extends BaseObject
{
    /** @var string */
    public $name;
    /** @var boolean */
    public $isTerminal;

    /**
     * @param self $a
     * @param self $b
     * @return integer
     */
    public static function compare(self $a, self $b)
    {
        return ($a->isTerminal - $b->isTerminal)
            ?: strcmp($a->name, $b->name);
    }

    /**
     * @param self[] $a
     * @param self[] $b
     * @return integer
     */
    public static function compareList($a, $b)
    {
        foreach ($a as $i => $symbol) {
            // $b finished, but $a not yet
            if (!isset($b[$i])) {
                // $a > $b
                return 1;
            }
            // $a[$i] <=> $b[$i]
            $result = $symbol->compare($b[$i]);
            if ($result) {
                //# $a[$i] <> $b[$i]
                return $result;
            }
        }
        // $b starts with $a
        // if $b longer then $a
        if (count($b) > count($a)) {
            // $a < $b
            return -1;
        }
        // $a == $b
        return 0;
    }

    /**
     * @param string $name
     * @param bool $isTerminal
     */
    public function __construct($name, $isTerminal = false)
    {
        $this->name = $name;
        $this->isTerminal = $isTerminal;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
