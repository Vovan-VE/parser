<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\DevException;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Token;

class Lexer extends BaseObject
{
    const DUMP_NEAR_LENGTH = 30;
    const RE_NAME = '/^\\.?[a-z][_a-z0-9]*$/i';

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

        $this->checkMapNames($this->defines);

        $inline = [];
        $hidden = [];
        $normal = [];
        $map = [];
        $this->splitTerminals($this->terminals, $inline, $hidden, $normal, $map);

        $this->checkOverlappedNames($inline, $hidden, $normal);

        $this->hiddens = $hidden;
        $this->aliased = [];

        $inline_re_map = $this->buildInlines($inline);
        $same = array_intersect_key($map, $inline_re_map);
        if ($same) {
            throw new \LogicException("Duplicating inline and named tokens: " . join(', ', $same));
        }

        $terminals_map = $inline_re_map + $map;
        if (!$terminals_map) {
            throw new \InvalidArgumentException('No terminals defined');
        }

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
     * @param array $inline
     * @param array $hidden
     * @param array $normal
     * @since 1.3.2
     */
    private function splitTerminals(array $terminals, &$inline, &$hidden, &$normal, &$named)
    {
        foreach ($terminals as $key => $value) {
            if (is_int($key)) {
                $inline[$value] = $value;
            } elseif (preg_match(self::RE_NAME, $key)) {
                if ('.' === mb_substr($key, 0, 1, '8bit')) {
                    $name = mb_substr($key, 1, null, '8bit');
                    $hidden[$name] = true;
                    $named[$name] = $value;
                } else {
                    $normal[$key] = true;
                    $named[$key] = $value;
                }
            } else {
                throw new \InvalidArgumentException("Bad token name <$key>");
            }
        }
    }

    /**
     * @param array $inline `["plain" => mixed, ...]`
     * @param array $hidden `["name" => mixed, ...]`
     * @param array $normal `["name" => mixed, ...]`
     * @since 1.3.2
     */
    private function checkOverlappedNames(array $inline, array $hidden, array $normal)
    {
        foreach (
            [
                'named normal and hidden' => [$normal, $hidden],
                'named tokens and inline quoted' => [$normal, $inline],
                'named hidden tokens and inline quoted' => [$hidden, $inline],
            ]
            as $message => $pair
        ) {
            list ($a, $b) = $pair;
            $same = array_intersect_key($a, $b);
            if ($same) {
                throw new \InvalidArgumentException(
                    "Duplicating $message tokens: " . join(', ', $same)
                );
            }
        }
    }

    /**
     * @param array $inlines
     * @return array
     * @since 1.3.2
     */
    private function buildInlines(array $inlines)
    {
        // sort in reverse order to let more long items match first
        // so /'$$' | '$'/ will find ['$$', '$'] in '$$$' and not ['$', '$', '$']
        rsort($inlines, SORT_STRING);

        $re_map = [];

        $alias_name = 'a';
        foreach ($inlines as $text) {
            $name = '_' . $alias_name;
            // string increment
            $alias_name++;

            $this->aliased[$name] = $text;
            $this->hiddens[$text] = true;
            $re_map[$name] = preg_quote($text, '/');
        }

        return $re_map;
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
     * @since 1.3.2
     */
    private function checkMapNames(array $map)
    {
        $names = array_keys($map);
        $bad_names = preg_grep(self::RE_NAME, $names, PREG_GREP_INVERT);
        if ($bad_names) {
            throw new \InvalidArgumentException(
                'Bad names: ' . join(', ', $bad_names)
            );
        }
    }
}
