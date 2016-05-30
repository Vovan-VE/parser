<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\Token;

class Lexer
{
    /** @var array */
    public $defines = [];
    /** @var string[] */
    public $whitespaces = [];
    /** @var array */
    private $declarations = [];
    /** @var string */
    private $regexpDefines;
    /** @var string */
    private $regexpWhitespace;
    /** @var string */
    private $regexp;

    public function prepare(array $declarations)
    {
        if (!$declarations) {
            throw new \InvalidArgumentException('Empty declarations map');
        }

        $regexp = [];

        if ($this->defines) {
            if (array_intersect_key($this->defines, $declarations)) {
                throw new \InvalidArgumentException('Declarations and defines has duplicated names');
            }

            $defines = $this->buildMap($this->defines, '');
            $defines = "(?(DEFINE)$defines)";
            $regexp[] = $defines;
        } else {
            $defines = '';
        }
        $regexp[] = '\\G';

        $alt = $this->buildMap($declarations, '|');
        $regexp[] = "(?:$alt)";

        $regexp = join('', $regexp);

        $this->declarations = $declarations;
        $this->regexp = "/$regexp/u";

        if ($this->whitespaces) {
            $whitespaces = join('|', $this->whitespaces);
            $this->regexpWhitespace = "/$defines\\G(?:$whitespaces)+/u";
        } else {
            $this->regexpWhitespace = null;
        }
    }

    /**
     * @param string $input
     * @return Token[]
     */
    public function parse($input)
    {
        $tokens = [];
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
                $near = substr($input, $pos, 20);
                if ("" === $near || false === $near) {
                    $near = '<EOF>';
                } else {
                    $near = '"' . $near . '"';
                }
                throw new \RuntimeException("Cannot parse input at offset $pos near $near");
            }
            $pos = $match->nextOffset;
            $tokens[] = $match->token;
        }
        return $tokens;
    }

    /**
     * @param array $map
     * @param string|false $join
     * @return string|string[]
     */
    private function buildMap(array $map, $join = false)
    {
        $names = array_keys($map);
        $bad_names = preg_grep('/^[a-z][_a-z0-9]*$/i', $names, PREG_GREP_INVERT);
        if ($bad_names) {
            throw new \InvalidArgumentException('Bad names: ' . join(', ', $bad_names));
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
                "PCRE error #" . preg_last_error() . " for token at input pos $pos; REGEXP = {$this->regexp}"
            );
        }

        if (!$match) {
            return null;
        }

        $full_match = $match[0];
        if ('' === $full_match) {
            throw new \RuntimeException('Tokens should not match empty string');
        }

        // remove null
        $match = array_filter($match, 'is_scalar');
        // remove empty ""
        $match = array_filter($match, 'strlen');
        $match = array_diff_key($match, array_fill(1, count($match), null));

        $named = $match;
        unset($named[0]);

        $token = new Token();
        $token->content = reset($named);
        $token->type = key($named);
        $token->match = $match;

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
            if (false === preg_match($this->regexpWhitespace, $input, $match, 0, $pos)) {
                throw new \RuntimeException(
                    "PCRE error #" . preg_last_error() . " for whitespace at input pos $pos; REGEXP = {$this->regexpWhitespace}"
                );
            }

            if ($match) {
                return strlen($match[0]);
            }
        }

        return 0;
    }
}
