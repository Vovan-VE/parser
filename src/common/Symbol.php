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
    /** @var bool Is symbol terminal or not */
    private $isTerminal;
    /** @var bool Is symbol defined as hidden to not produce resulting tree node */
    private $isHidden = false;

    /**
     * Compares two symbols
     *
     * Two symbols are equal when its `$name` and `$isTerminal` are the same.
     *
     * Note: Value of `$isHidden` is not used for comparison.
     * @param Symbol $a
     * @param Symbol $b
     * @return int Returns 0 when both symbols are equal.
     */
    public static function compare(Symbol $a, Symbol $b): int
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
     * @return int Returns 0 then lists are equals
     */
    public static function compareList(array $a, array $b): int
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
    public function __construct(string $name, bool $isTerminal = false, bool $isHidden = false)
    {
        $this->name = $name;
        $this->isTerminal = $isTerminal;
        $this->isHidden = $isHidden;
    }

    /**
     * Name of the symbol, case sensitive
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Whether the symbol is terminal or not
     * @return bool
     */
    public function isTerminal(): bool
    {
        return $this->isTerminal;
    }

    /**
     * Changes whether the symbol is terminal or not
     *
     * This method is for internal use.
     * @param bool $value
     */
    public function setIsTerminal(bool $value): void
    {
        $this->isTerminal = $value;
    }

    /**
     * Whether the symbol is hidden or not
     * @return bool
     * @since 1.4.0
     */
    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * Changes whether the symbol is hidden or not
     *
     * This method is for internal use.
     * @param bool $value
     * @since 2.0.0
     */
    public function setIsHidden(bool $value): void
    {
        $this->isHidden = $value;
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
    public static function dumpName(string $name): string
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
    public static function dumpType(string $type): string
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
    public static function dumpInline(string $inline): string
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
    protected static function isLikeName(string $type): bool
    {
        return (bool)preg_match('/^[a-z][a-z_0-9]*$/i', $type);
    }
}
