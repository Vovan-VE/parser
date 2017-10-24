<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\DevException;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Token;

class Lexer extends BaseObject
{
    const DUMP_NEAR_LENGTH = 30;
    const RE_NAME = '/^\\.?[_a-z][_a-z0-9]*$/i';

    /** @var array */
    private $defines;
    /** @var array */
    private $terminals;
    /** @var array */
    private $whitespaces;
    /** @var string */
    private $modifiers = '';

    /** @var bool */
    private $isCompiled = false;

    /** @var string */
    private $regexpWhitespace;
    /** @var string */
    private $regexp;
    /** @var array */
    private $hiddens = [];
    /** @var array Key is generated name, value is source string for Symbol name */
    private $aliased = [];

    /**
     * @param array $terminals
     * @param array $whitespaces
     * @param array $defines
     * @param string $modifiers
     */
    public function __construct(
        array $terminals,
        array $whitespaces = [],
        array $defines = [],
        $modifiers = 'u'
    ) {
        if (!$terminals) {
            throw new \InvalidArgumentException('Empty terminals map');
        }

        $this->defines = $defines;
        $this->terminals = $terminals;
        $this->whitespaces = $whitespaces;
        $this->modifiers = $modifiers;
    }

    /**
     * @param array $terminals
     * @param array $whitespaces
     * @param array $defines
     * @param string $modifiers
     * @return static
     * @since 1.3.2
     */
    public function extend(
        array $terminals = [],
        array $whitespaces = [],
        array $defines = [],
        $modifiers = ''
    ) {
        $new_terminals = $this->terminals;
        foreach ($terminals as $name => $re) {
            if (is_int($name)) {
                $new_terminals[] = $re;
            } elseif (isset($new_terminals[$name])) {
                throw new \InvalidArgumentException(
                    'Cannot redefine terminal: ' . var_export($name, true)
                );
            } else {
                $new_terminals[$name] = $re;
            }
        }

        $dup_keys = array_intersect_key($this->defines, $defines);
        if ($dup_keys) {
            throw new \InvalidArgumentException(
                "Cannot redefine defines: " . var_export($dup_keys, true)
            );
        }

        return new static(
            $new_terminals,
            array_merge($this->whitespaces, $whitespaces),
            $this->defines + $defines,
            $this->modifiers . $modifiers
        );
    }

    /**
     * @return $this
     * @since 1.3.2
     */
    public function compile()
    {
        if ($this->isCompiled) {
            return $this;
        }

        $this->checkMapNames($this->terminals, true);
        $this->checkMapNames($this->defines);

        $terminals_map = $this->parseHiddenTerminals($this->terminals);

        $regexp = [];

        if ($this->defines) {
            if (array_intersect_key($this->defines, $terminals_map)) {
                throw new \InvalidArgumentException(
                    'Declarations and defines has duplicated names'
                );
            }

            $re_defines = $this->buildMap($this->defines, '');
            $re_defines = "(?(DEFINE)$re_defines)";
            $regexp[] = $re_defines;
        } else {
            $re_defines = '';
        }
        $regexp[] = '\\G';

        $alt = $this->buildMap($terminals_map, '|');
        $regexp[] = "(?:$alt)";

        $regexp = join('', $regexp);

        $this->regexp = "/$regexp/" . $this->modifiers;
        if (false === preg_match($this->regexp, null)) {
            throw new \InvalidArgumentException('PCRE error');
        }

        if ($this->whitespaces) {
            $re_whitespaces = join('|', $this->whitespaces);
            $this->regexpWhitespace = "/$re_defines\\G(?:$re_whitespaces)+/" . $this->modifiers;
        } else {
            $this->regexpWhitespace = null;
        }

        $this->isCompiled = true;
        return $this;
    }

    /**
     * @return bool
     * @since 1.3.2
     */
    public function isCompiled()
    {
        return $this->isCompiled;
    }

    /**
     * @param string $input
     * @return \Generator|Token[]
     */
    public function parse($input)
    {
        $this->compile();

        $length = strlen($input);
        $pos = 0;
        while ($pos < $length) {
            $whitespace_length = $this->getWhitespaceLength($input, $pos);
            if ($whitespace_length) {
                $pos += $whitespace_length;
                if ($pos >= $length) {
                    break;
                }
            }
            $match = $this->match($input, $pos);
            if (!$match) {
                $near = substr($input, $pos, self::DUMP_NEAR_LENGTH);
                if ("" === $near || false === $near) {
                    $near = '<EOF>';
                } else {
                    $near = '"' . $near . '"';
                }
                throw new ParseException(
                    "Cannot parse valid token near $near",
                    $pos
                );
            }
            $pos = $match->nextOffset;
            yield $match->token;
        }
    }

    /**
     * @param array $terminals
     * @return array
     * @uses $hiddens
     * @uses $aliased
     * @since 1.3.2
     */
    private function parseHiddenTerminals(array $terminals) {
        $map = [];
        $hidden = [];
        $aliased = [];
        $alias_name = 'a';
        foreach ($terminals as $name => $re) {
            if (is_int($name)) {
                $name = '_' . $alias_name;
                $hidden[$re] = $name;
                $aliased[$name] = $re;
                $re = preg_quote($re, '/');

                // string increment
                $alias_name++;
            } elseif ('.' === mb_substr($name, 0, 1, '8bit')) {
                $true_name = mb_substr($name, 1, null, '8bit');
                if (isset($map[$true_name])) {
                    throw new \InvalidArgumentException(
                        "Hidden name '$name' conflicts with existing '$true_name'"
                    );
                }
                $name = $true_name;
                $hidden[$name] = $name;
            } elseif (isset($map[$name])) {
                throw new \InvalidArgumentException(
                    "Name '$name' conflicts with existing hidden name '.$name'"
                );
            }
            $map[$name] = $re;
        }
        $this->hiddens = $hidden;
        $this->aliased = $aliased;
        return $map;
    }

    /**
     * @param array $map
     * @param string|bool $join
     * @return string|string[]
     */
    private function buildMap(array $map, $join = false)
    {
        $alt = [];
        foreach ($map as $type => $re) {
            $alt[] = "(?<$type>$re)";
        }
        if (false !== $join) {
            $alt = join($join, $alt);
        }
        return $alt;
    }

    /**
     * @param string $input
     * @param int $pos
     * @return Match|null
     */
    private function match($input, $pos)
    {
        if (false === preg_match($this->regexp, $input, $match, 0, $pos)) {
            throw new \RuntimeException(
                "PCRE error #" . preg_last_error()
                . " for token at input pos $pos; REGEXP = {$this->regexp}"
            );
        }

        if (!$match) {
            return null;
        }

        $full_match = $match[0];
        if ('' === $full_match) {
            throw new DevException('Tokens should not match empty string');
        }

        // remove null, empty "" values and integer keys but [0]
        foreach ($match as $key => $value) {
            if (
                null === $value
                || '' === $value
                || (0 !== $key && is_int($key))
            ) {
                unset($match[$key]);
            }
        }

        $named = $match;
        unset($named[0]);
        if (1 !== count($named)) {
            throw new InternalException('Match with multiple named group');
        }

        $content = reset($named);
        $type = key($named);
        if (isset($this->aliased[$type])) {
            $type = $this->aliased[$type];
        }
        $token = new Token($type, $content, $match, $pos, isset($this->hiddens[$type]));

        $result = new Match();
        $result->token = $token;
        $result->nextOffset = $pos + strlen($full_match);

        return $result;
    }

    /**
     * @param string $input
     * @param int $pos
     * @return int
     */
    private function getWhitespaceLength($input, $pos)
    {
        if ($this->regexpWhitespace) {
            if (
                false === preg_match(
                    $this->regexpWhitespace,
                    $input,
                    $match,
                    0,
                    $pos
                )
            ) {
                throw new InternalException(
                    'PCRE error #' . preg_last_error()
                    . ' for whitespace at input pos ' . $pos
                    . '; REGEXP = ' . $this->regexpWhitespace
                );
            }

            if ($match) {
                return strlen($match[0]);
            }
        }

        return 0;
    }

    /**
     * @param array $map
     * @param bool $allowInlines
     * @since 1.3.2
     */
    private function checkMapNames(array $map, $allowInlines = false)
    {
        $names = array_keys($map);
        if ($allowInlines) {
            $names = array_filter($names, 'is_string');
        }
        $bad_names = preg_grep(self::RE_NAME, $names, PREG_GREP_INVERT);
        if ($bad_names) {
            throw new \InvalidArgumentException(
                'Bad names: ' . join(', ', $bad_names)
            );
        }
    }
}
