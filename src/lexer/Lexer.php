<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\DevException;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Token;

class Lexer extends BaseObject
{
    const DUMP_NEAR_LENGTH = 30;

    /** @var string */
    private $regexpWhitespace;
    /** @var string */
    private $regexp;

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

        $regexp = [];

        if ($defines) {
            if (array_intersect_key($defines, $terminals)) {
                throw new \InvalidArgumentException(
                    'Declarations and defines has duplicated names'
                );
            }

            $re_defines = $this->buildMap($defines, '');
            $re_defines = "(?(DEFINE)$re_defines)";
            $regexp[] = $re_defines;
        } else {
            $re_defines = '';
        }
        $regexp[] = '\\G';

        $alt = $this->buildMap($terminals, '|');
        $regexp[] = "(?:$alt)";

        $regexp = join('', $regexp);

        $this->regexp = "/$regexp/$modifiers";
        if (false === preg_match($this->regexp, null)) {
            throw new \InvalidArgumentException('PCRE error');
        }

        if ($whitespaces) {
            $re_whitespaces = join('|', $whitespaces);
            $this->regexpWhitespace = "/$re_defines\\G(?:$re_whitespaces)+/$modifiers";
        } else {
            $this->regexpWhitespace = null;
        }
    }

    /**
     * @param string $input
     * @return \Generator|Token[]
     */
    public function parse($input)
    {
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
     * @param array $map
     * @param string|bool $join
     * @return string|string[]
     */
    private function buildMap(array $map, $join = false)
    {
        $names = array_keys($map);
        $bad_names = preg_grep(
            '/^[a-z][_a-z0-9]*$/i',
            $names,
            PREG_GREP_INVERT
        );
        if ($bad_names) {
            throw new \InvalidArgumentException(
                'Bad names: ' . join(', ', $bad_names)
            );
        }

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

        $token_content = reset($named);
        $token_type = key($named);
        $token = new Token($token_type, $token_content, $match, $pos);

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
}
