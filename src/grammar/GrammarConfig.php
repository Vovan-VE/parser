<?php
namespace VovanVE\parser\grammar;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\grammar\config\OptionHandlerInterface;
use VovanVE\parser\grammar\config\WhiteSpaceOption;

/**
 * Grammar config
 * @package VovanVE\parser
 * @since 1.5.0
 */
class GrammarConfig extends BaseObject
{
    /**
     * @var string[][] First level key is boolean `is regexp`. List in `[0]` is array
     * of plain strings. List in `[1]` is array or RegExp.
     */
    private $ws = [0 => [], 1 => []];

    // REFACT: minimal PHP >= 7.0: const array isset
    private static $handlers = [
        /** @uses $ws */
        'ws' => WhiteSpaceOption::class,
    ];

    /**
     * Get whitespaces option
     *
     * First level key is boolean `is regexp`.
     * List in `[0]` is array of plain strings.
     * List in `[1]` is array or RegExp parts.
     * Each final string is one type of whitespace to match.
     * @return string[][]
     */
    public function getWhiteSpaces()
    {
        return $this->ws;
    }

    /**
     * @param string $name
     * @param string|null $tag
     * @param string $value
     * @param bool $isRegExp
     */
    public function setOption($name, $tag, $value, $isRegExp = false)
    {
        if (!isset(self::$handlers[$name])) {
            throw new GrammarException("Unsupported config option '$name'");
        }
        $handler_class = self::$handlers[$name];
        /** @var OptionHandlerInterface $handler */
        $handler = new $handler_class;

        $this->$name = $handler->updateData($this->$name, $tag, $value, $isRegExp);
    }
}
