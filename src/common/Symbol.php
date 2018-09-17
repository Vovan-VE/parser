<?php
namespace VovanVE\parser\common;

/**
 * Atomic part of a grammar rules
 *
 * Symbol is used internally as atomic part of a rules and state table.
 * @package VovanVE\parser
 */
class Symbol extends BaseObject
{
    /** @var string Name of the symbol, case sensitive */
    private $name;
    /** @var boolean Is symbol terminal or not */
    private $isTerminal;
    /** @var boolean Is symbol defined as hidden to not produce resulting tree node */
    private $isHidden = false;

    /**
     * Compares two symbols
     *
     * Two symbols are equal when its `$name` and `$isTerminal` are the same.
     *
     * Note: Value of `$isHidden` is not used for comparison.
     * @param Symbol $a
     * @param Symbol $b
     * @return integer Returns 0 when both symbols are equal.
     */
    public static function compare($a, $b)
    {
        return ($a->isTerminal - $b->isTerminal)
            ?: strcmp($a->name, $b->name);
    }

    /**
     * Compare two lists of symbols
     *
     * Lists are equal when both contains equal symbols in the same order.
     * @param Symbol[] $a
     * @param Symbol[] $b
     * @return integer Returns 0 then lists are equals
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
     * @param string $name Name for the symbol, case sensitive
     * @param bool $isTerminal Whether the symbol is terminal or not
     * @param bool $isHidden [since 1.4.0] Whether the symbol is hidden or not.
     * Hidden symbols does not produce nodes in the resulting tree.
     */
    public function __construct($name, $isTerminal = false, $isHidden = false)
    {
        $this->name = $name;
        $this->isTerminal = (bool)$isTerminal;
        $this->isHidden = $isHidden;
    }

    /**
     * Name of the symbol, case sensitive
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Whether the symbol is terminal or not
     * @return boolean
     */
    public function isTerminal()
    {
        return $this->isTerminal;
    }

    /**
     * Changes whether the symbol is terminal or not
     * @param boolean $value
     */
    public function setIsTerminal($value)
    {
        $this->isTerminal = (bool)$value;
    }

    /**
     * Whether the symbol is hidden or not
     * @return bool
     * @since 1.4.0
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
        return ($this->isHidden ? '.' : '') . self::dumpName($this->name);
    }

    /**
     * Dump symbol name for debug purpose
     * @param string $name
     * @return string
     * @since 1.5.0
     */
    public static function dumpName($name)
    {
        if (self::isLikeName($name)) {
            return $name;
        }
        return self::dumpInline($name);
    }

    /**
     * Dump symbol type for debug purpose
     * @param string $type
     * @return string
     * @since 1.5.0
     */
    public static function dumpType($type)
    {
        if (self::isLikeName($type)) {
            return "<$type>";
        }
        return self::dumpInline($type);
    }

    /**
     * Dump inline symbol for debug purpose
     * @param string $inline
     * @return string
     * @since 1.5.0
     */
    public static function dumpInline($inline)
    {
        if (false === strpos($inline, '"')) {
            return '"' . $inline . '"';
        }
        if (false === strpos($inline, "'")) {
            return "'$inline'";
        }
        return "<$inline>";
    }

    /**
     * @param string $type
     * @return bool
     * @since 1.5.0
     */
    protected static function isLikeName($type)
    {
        return (bool)preg_match('/^[a-z][a-z_0-9]*$/i', $type);
    }
}
