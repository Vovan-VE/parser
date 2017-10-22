<?php
namespace VovanVE\parser\common;

class Symbol extends BaseObject
{
    /** @var string */
    private $name;
    /** @var boolean */
    private $isTerminal;
    /** @var boolean */
    private $isHidden = false;

    /**
     * @param Symbol $a
     * @param Symbol $b
     * @return integer
     */
    public static function compare($a, $b)
    {
        return ($a->isTerminal - $b->isTerminal)
            ?: strcmp($a->name, $b->name);
    }

    /**
     * @param Symbol[] $a
     * @param Symbol[] $b
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
            $result = static::compare($symbol, $b[$i]);
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
     * @param bool $isHidden [since 1.3.2]
     */
    public function __construct($name, $isTerminal = false, $isHidden = false)
    {
        $this->name = $name;
        $this->isTerminal = $isTerminal;
        $this->isHidden = $isHidden;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function isTerminal()
    {
        return $this->isTerminal;
    }

    /**
     * @param boolean $value
     */
    public function setIsTerminal($value)
    {
        $this->isTerminal = (bool)$value;
    }

    /**
     * @return bool
     * @since 1.3.2
     */
    public function isHidden()
    {
        return $this->isHidden;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return ($this->isHidden ? '.' : '') . $this->name;
    }
}
