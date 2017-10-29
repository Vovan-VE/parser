<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\DevException;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Token;

/**
 * Lexer parses input text into tokens stream
 *
 * Tokens are atomic parts of a grammar.
 *
 * ```php
 * $lexer = new Lexer(
 *     // all terminals to parse
 *     [
 *         // inline tokens literally, order does not matter
 *         // inline tokens are always hidden
 *         '++',
 *         '+',
 *         '--',
 *         '-',
 *         '*',
 *         '/',
 *
 *         // named tokens are RegExp parts
 *         'int'   => '\\d++',
 *         'const' => '(?&name)',
 *         'var'   => '\\$(?&name)',
 *         '.foo'  => ';',            // hidden named token
 *     ],
 *     // whitespaces and comments to skip completely
 *     [
 *         '\\s++',           // linear whitespaces
 *         '#\\N*+\\n?+',     // line #comments
 *     ],
 *     // DEFINEs can only be referenced from tokens as named recursion `(?&name)`
 *     [
 *         'name' => '[a-z_][a-z_0-9]*+',
 *     ],
 *     'iu'
 * );
 *
 * Mostly you can define only named tokens. Inline tokens will be added later
 * from grammar inline tokens.
 * ```
 *
 * @package VovanVE\parser
 * @see \VovanVE\parser\LexerBuilder
 */
class Lexer extends BaseObject
{
    // REFACT: minimal PHP >= 7.1: private const
    const DUMP_NEAR_LENGTH = 30;
    // REFACT: minimal PHP >= 7.1: private const
    const RE_NAME = '/^\\.?[a-z][_a-z0-9]*$/i';
    // REFACT: minimal PHP >= 7.1: private const
    const RE_DEFINE_NAME = '/^[a-z][_a-z0-9]*$/i';

    /**
     * @var array Map of RegExp DEFINE's to reference from terminals and whitespaces.
     * Key is name and value is a part of RegExp
     */
    private $defines;
    /**
     * @var array Terminals definition. Items of the array can be
     * either key=>value for named tokens (here Key is name and value is a part of RegExp;
     * DEFINEs can be referred with `(?&name)` regexp recursion)
     * or just a value (integer auto index) with inline token text literally.
     */
    private $terminals;
    /**
     * @var array List of RegExp parts to define whitespaces to ignore in an input text.
     * DEFINEs can be referred with `(?&name)` regexp recursion.
     */
    private $whitespaces;
    /**
     * @var string Modifiers to whole regexp.
     *
     * Same modifiers will be applied both to tokens and whitespaces regexps.
     *
     * Here only "global" modifiers like `u`, `x`, `D`, etc.
     * should be used. Other modifiers like `i` should (but not required) be used locally
     * in specific parts like `(?i)[a-z]` or `(?i:[a-z])`.
     */
    private $modifiers = '';

    /** @var bool Was the lexer already compiled into regexps from provided configuration */
    private $isCompiled = false;

    /** @var string Compiled full RegExp for whitespaces and comments */
    private $regexpWhitespace;
    /** @var string Compiled full RegExp for tokens */
    private $regexp;
    /**
     * @var array Map for hidden tokens to mark output Tokens.
     * Keys are either names or inline values. Values are any non-null value. */
    private $hiddens = [];
    /**
     * @var array Aliases map for inline tokens. Key is generated name,
     * value is source string for Symbol name
     */
    private $aliased = [];

    /**
     * Constructor
     *
     * See class description for details
     * @param array $terminals Terminals definitions. Key=>value pairs are named tokens.
     * Plain value with auto index is inline tokens literally.
     * @param array $whitespaces List of regexp parts to define whitespaces and comments to skip
     * @param array $defines Map of DEFINEs regexp parts to reference from terminals and whitespaces.
     * @param string $modifiers Mogifiers to both whole regexps. Here should be (but not required)
     * used only "global" modifiers like `u`, `x`, `D` etc. Other modifiers like `i` is better
     * to use locally like `(?i)[a-z]` or `(?i:[a-z])`.
     * @see \VovanVE\parser\LexerBuilder
     */
    public function __construct(
        array $terminals = [],
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
     * Create new Lexer extending this one
     * @param array $terminals Additional terminals. Both inline and named are acceptable.
     * Duplicating inline tokens is not permitted, but redefinition of named tokens
     * is restricted.
     * @param array $whitespaces Additional whitespaces regexps. Duplicating currently
     * is not checked, so it on your own.
     * @param array $defines Additional DEFINEs regexps. Duplicating names is restricted.
     * @param string $modifiers Additional modifiers to whole regexps.
     * @return static New Lexer object not compiled yet.
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
     * Create new Lexer extending this one with DEFINEs
     * @param array $defines Additional DEFINEs regexps. Duplicating names is restricted.
     * @return static
     * @since 1.3.2
     */
    public function defines(array $defines)
    {
        return $this->extend([], [], $defines);
    }

    /**
     * Create new Lexer extending this one with whitespaces
     * @param array $whitespaces Additional whitespaces regexps. Duplicating currently
     * is not checked, so it on your own.
     * @return static
     * @since 1.3.2
     */
    public function whitespaces(array $whitespaces)
    {
        return $this->extend([], $whitespaces);
    }

    /**
     * Create new Lexer extending this one with terminals
     * @param array $terminals Additional terminals. Both inline and named are acceptable.
     * Duplicating inline tokens is not permitted, but redefinition of named tokens
     * is restricted.
     * @return static
     * @since 1.3.2
     */
    public function terminals($terminals)
    {
        return $this->extend($terminals);
    }

    /**
     * Create new Lexer extending this one with RegExp modifiers
     * @param string $modifiers Additional modifiers to whole regexps.
     * @return static
     * @since 1.3.2
     */
    public function modifiers($modifiers)
    {
        return $this->extend([], [], [], $modifiers);
    }

    /**
     * Compile internal regexps
     *
     * Build internal regexps from configured parts. There are some logic checks.
     * Also both compiled regexps will be checked itself in the end by test matching.
     *
     * As of specific lexer instance is immutable, compilation will be
     * performed only once. Subsequent calls to the method does nothing special.
     *
     * Method will be called automatically with first call to `parse()` method.
     * Direct call to the method can be used to divide compilation errors from other errors
     * from `parse()` method.
     * @return $this
     * @since 1.3.2
     */
    public function compile()
    {
        if ($this->isCompiled) {
            return $this;
        }

        $this->checkMapNames($this->defines, self::RE_DEFINE_NAME);

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
            throw new \InvalidArgumentException('PCRE error in main RegExp', preg_last_error());
        }

        if ($this->whitespaces) {
            $re_whitespaces = join('|', $this->whitespaces);
            $this->regexpWhitespace = "/$re_defines\\G(?:$re_whitespaces)+/" . $this->modifiers;
            if (false === preg_match($this->regexpWhitespace, null)) {
                throw new \InvalidArgumentException(
                    'PCRE error in whitespaces RegExp',
                    preg_last_error()
                );
            }
        } else {
            $this->regexpWhitespace = null;
        }

        $this->isCompiled = true;
        return $this;
    }

    /**
     * Was lexer compiled or not yet
     * @return bool
     * @since 1.3.2
     */
    public function isCompiled()
    {
        return $this->isCompiled;
    }

    /**
     * Parse input text into Tokens
     *
     * Lexer will be compiled if didn't yet.
     *
     * @param string $input Input text to parse
     * @return \Generator|Token[] Returns generator of `Token`s. Generator has no its own return value.
     * @throws ParseException Nothing matched in a current position
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
     * Split terminals definition in different types
     *
     * @param array $terminals Input terminals definitions array
     * @param array $inline Variable to store inline tokens. Both keys and value are same.
     * @param array $hidden Variable to store hidden named tokens. Key is name without leading
     * dot and value is non-null.
     * @param array $normal Variable to store normal named tokens. Key is name and value is non-null.
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
     * Check defined tokens for duplicating names
     *
     * Different tokens must to produce different output Tokens.
     * So names and inline tokens must not overlap.
     * @param array $inline Extracted inline tokens `["plain" => mixed, ...]`
     * @param array $hidden Extracted hidden tokens `["name" => mixed, ...]`
     * @param array $normal Extracted normal tokens `["name" => mixed, ...]`
     * @throws \InvalidArgumentException Some overlapping names
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
     * Build inline tokens into regexp map
     *
     * **Note** about side effect of changing properties in addition to returned map.
     *
     * Inline tokens all are aliased with generated names. Returned regexps map has that
     * generated named in keys. Aliases map is stored in `$aliased` property. As of all inline
     * tokens are hidden, `$hiddens` map will be updated too with generated names.
     * @param array $inlines List of inline token texts.
     * @return array Returns regexps map with generated names in keys. Also properties `$aliased`
     * and `$hiddens` will be updated.
     * @since 1.3.2
     */
    private function buildInlines(array $inlines)
    {
        // sort in reverse order to let more long items with match first
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
     * Build regexp map into one regexp part or list of ready parts
     *
     * Input map is key=>value paired array. Key which is name will become name for named regexp
     * group, and value will becove its body. So `'int' => '\\d+'` will become `(?<int>\\d+)`.
     *
     * The result is one regexp part of built subparts joined with delimiter. Joining can be
     * bypassed with `$join = false` argument.
     * @param array $map Input map of regexps.
     * @param string|bool $join Join with delimiter. `false` cause to return list of built parts.
     * @return string|string[] Returns either joined regexp part or list or regexp subparts
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
     * Search a match in input text at a position
     *
     * Search is performed by `parse()` after `compile()` call. Match is searched
     * exactly in the given position by `\G`.
     * @param string $input Input text to search where
     * @param int $pos Position inthe text to match where
     * @return Match|null Match object on success match. `null` if no match found.
     * @throws \RuntimeException Error from PCRE
     * @throws DevException Error by end developer in lexer configuration
     * @throws InternalException Error in the package
     */
    private function match($input, $pos)
    {
        if (false === preg_match($this->regexp, $input, $match, 0, $pos)) {
            $error_code = preg_last_error();
            throw new \RuntimeException(
                "PCRE error #" . $error_code
                . " for token at input pos $pos; REGEXP = {$this->regexp}",
                $error_code
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
     * Search a presens of whitespaces in input text in a position
     *
     * Search is performed by `parse()` after `compile()` call. Match is searched
     * exactly in the given position by `\G`.
     * @param string $input Input text to search where
     * @param int $pos Position inthe text to match where
     * @return int Returns matched length of whitespaces. Returns 0 if no whitespaces
     * found in the position.
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
                $error_code = preg_last_error();
                throw new \RuntimeException(
                    "PCRE error #$error_code for whitespace at input pos $pos"
                    . '; REGEXP = ' . $this->regexpWhitespace,
                    $error_code
                );
            }

            if ($match) {
                return strlen($match[0]);
            }
        }

        return 0;
    }

    /**
     * Check names in regexps map for validity
     * @param array $map Map of regexp parts to check
     * @param string $nameRegExp Regexp for names to check against.
     * @throws \InvalidArgumentException Some bad named was found.
     * @since 1.3.2
     */
    private function checkMapNames(array $map, $nameRegExp)
    {
        $names = array_keys($map);
        $bad_names = preg_grep($nameRegExp, $names, PREG_GREP_INVERT);
        if ($bad_names) {
            throw new \InvalidArgumentException(
                'Bad names: ' . join(', ', $bad_names)
            );
        }
    }
}
