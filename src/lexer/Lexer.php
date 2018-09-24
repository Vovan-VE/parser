<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\DevException;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\common\Symbol;
use VovanVE\parser\common\Token;

/**
 * Lexer parses input text into tokens stream
 *
 * Tokens are atomic parts of a grammar.
 *
 * ```php
 * $lexer = (new Lexer)
 *     // inline tokens literally
 *     ->inline([
 *         // order does not matter
 *         // inline tokens are always hidden
 *         '++',
 *         '+',
 *         '-',
 *         '--',
 *         '*',
 *         '/',
 *     ])
 *     // fixed tokens literally
 *     ->fixed([
 *         // order does not matter
 *         'semicolon'    => ';',
 *         '.colon'       => ':', // hidden named token
 *         'double_colon' => '::',
 *         // order between inline and fixed does not matter too
 *     ])
 *     // terminals are RegExp parts
 *     ->terminals([
 *         'int'   => '\\d++',
 *         'const' => '(?&name)',
 *         'var'   => '\\$(?&name)',
 *         '.foo'  => '\\?++', // hidden named token
 *     ])
 *     // whitespaces and comments to skip completely
 *     ->whitespaces([
 *         '\\s++',           // linear whitespaces
 *         '#\\N*+\\n?+',     // line #comments
 *     ])
 *     // DEFINEs can only be referenced from tokens and whitespaces as named recursion `(?&name)`
 *     ->defines([
 *         'name' => '[a-z_][a-z_0-9]*+',
 *     ])
 *     ->modifiers('i');
 *
 * Mostly you can define only named tokens. Inline tokens will be added later
 * from grammar.
 * ```
 *
 * @package VovanVE\parser
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
     * @var array Fixed terminal definition. Values are plain strings to parse as is.
     * Keys are names.
     */
    private $fixed;
    /**
     * @var string[] Inline terminals definition. Values are plain strings to parse as is.
     * Keys does not matter.
     */
    private $inline;
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

    /** @var string Compiled RegExp part for DEFINEs */
    private $regexpDefines;
    /** @var string Compiled full RegExp for whitespaces and comments */
    private $regexpWhitespace;
    /** @var string Compiled full RegExp for tokens */
    private $regexp;
    /** @var string[] Cache for compiled full RegExp to parse limited set of tokens */
    private $regexpForTokens = [];
    /** @var string[] Prepared RegExp map for fixed and aliased inlines */
    private $regexpFixedAndInlineMap;
    /** @var string[] Prepared RegExp map for terminals */
    private $regexpTerminalsMap;
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
     * @var array Reverse aliases map for inline tokens. Key is source string for Symbol name,
     * value is generated name
     */
    private $aliasOf = [];

    /**
     * Constructor
     *
     * See class description for details.
     *
     * > Deprecation notice: Utilizing all of arguments is deprecated in favor
     * > to corresponding methods. Arguments will be removed in future.
     * @param array $terminals Terminals definitions. Key=>value pairs are named tokens.
     * Plain value with auto index is inline tokens literally.
     * @param array $whitespaces List of regexp parts to define whitespaces and comments to skip
     * @param array $defines Map of DEFINEs regexp parts to reference from terminals and whitespaces.
     * @param string $modifiers Modifiers to both whole regexps. Here should be (but not required)
     * used only "global" modifiers like `u`, `x`, `D` etc. Other modifiers like `i` is better
     * to use locally like `(?i)[a-z]` or `(?i:[a-z])`.
     */
    public function __construct(
        array $terminals = [],
        array $whitespaces = [],
        array $defines = [],
        $modifiers = 'u'
    ) {
        $this->defines = $defines;
        $this->fixed = [];
        $this->inline = [];
        $this->terminals = $terminals;
        $this->whitespaces = $whitespaces;
        $this->modifiers = $modifiers;
    }

    public function __clone()
    {
        $this->isCompiled = false;
        $this->regexpWhitespace = null;
        $this->regexp = null;
        $this->hiddens = [];
        $this->aliased = [];
    }

    /**
     * Create new Lexer extending this one
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param array $terminals Additional terminals. Both inline and named are acceptable.
     * Duplicating inline tokens is not permitted, but redefinition of named tokens
     * is restricted.
     * @param array $whitespaces Additional whitespaces regexps. Duplicating currently
     * is not checked, so it on your own.
     * @param array $defines Additional DEFINEs regexps. Duplicating names is restricted.
     * @param string $modifiers Additional modifiers to whole regexps.
     * @return static New Lexer object not compiled yet.
     * @since 1.4.0
     * @deprecated >= 1.5.0: Use corresponding extension method
     */
    public function extend(
        array $terminals = [],
        array $whitespaces = [],
        array $defines = [],
        $modifiers = ''
    ) {
        return $this
            ->defines($defines)
            ->terminals($terminals)
            ->whitespaces($whitespaces)
            ->modifiers($modifiers);
    }

    /**
     * Create new Lexer extending this one with DEFINEs
     *
     * DEFINEs are named regexps to be used as from terminals and whitespaces
     * with named recursion `(?&name)` to simplify regexp code duplication.
     * DEFINEs can refer to each other. Order should not matter.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param array $defines Additional DEFINEs regexps. Duplicating names is restricted.
     * @return static
     * @since 1.4.0
     */
    public function defines(array $defines)
    {
        if (!$defines) {
            return $this;
        }

        $dup_keys = array_intersect_key($this->defines, $defines);
        if ($dup_keys) {
            throw new \InvalidArgumentException(
                "Cannot redefine defines: " . var_export($dup_keys, true)
            );
        }

        $copy = clone $this;
        $copy->defines += $defines;
        return $copy;
    }

    /**
     * Create new Lexer extending this one with whitespaces
     *
     * Whitespaces are searched between all actual tokens and completely ignored.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param array $whitespaces Additional whitespaces regexps. Duplicating currently
     * is not checked, so it on your own.
     * @return static
     * @since 1.4.0
     */
    public function whitespaces(array $whitespaces)
    {
        if (!$whitespaces) {
            return $this;
        }

        $copy = clone $this;
        $copy->whitespaces = array_merge($copy->whitespaces, $whitespaces);
        return $copy;
    }

    /**
     * Create new Lexer extending this one with fixed tokens
     *
     * Named tokens defined with fixed strings to parse as is.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param array $fixed Additional fixed tokens. Duplicating names are restricted.
     * @return static
     * @throws \InvalidArgumentException In case of name duplication.
     * @since 1.5.0
     */
    public function fixed($fixed)
    {
        if (!$fixed) {
            return $this;
        }

        $new_fixed = $this->addNamedTokens($this->fixed, $fixed, 'fixed');

        $copy = clone $this;
        $copy->fixed = $new_fixed;
        return $copy;
    }

    /**
     * Create new Lexer extending this one with inline tokens
     *
     * Inline tokens defined only with fixed strings without names. Inline tokens
     * are always hidden.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param string[] $inline Additional inline tokens. Duplication is not permitted.
     * @return static
     * @since 1.5.0
     */
    public function inline($inline)
    {
        if (!$inline) {
            return $this;
        }

        $copy = clone $this;
        $copy->inline = array_merge($this->inline, $inline);
        return $copy;
    }

    /**
     * Create new Lexer extending this one with terminals
     *
     * Named tokens defined with regexps. Named regexps from DEFINEs can be
     * referenced here with named recursion `(?&name)`.
     *
     * > Deprecation notice: Inline tokens without names introduced in 1.4.0 should
     * be defined with `inline()` method since 1.5.0. So, defining inline tokens within
     * terminals is deprecated.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param array $terminals Additional terminals. Both inline and named are acceptable.
     * Duplicating inline tokens is not permitted, but redefinition of named tokens
     * is restricted.
     * @return static
     * @since 1.4.0
     */
    public function terminals($terminals)
    {
        if (!$terminals) {
            return $this;
        }

        $new_terminals = $this->addMixedTokens($this->terminals, $terminals, 'terminal');

        $copy = clone $this;
        $copy->terminals = $new_terminals;
        return $copy;
    }

    /**
     * Create new Lexer extending this one with RegExp modifiers
     *
     * Same modifiers will be applied both to tokens and whitespaces regexps.
     *
     * Here only "global" modifiers like `u`, `x`, `D`, etc. should be used.
     * Other modifiers like `i` should (but not required) be used locally
     * in specific parts like `(?i)[a-z]` or `(?i:[a-z])`.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param string $modifiers Additional modifiers to whole regexps.
     * @return static
     * @since 1.4.0
     */
    public function modifiers($modifiers)
    {
        if ('' === (string)$modifiers) {
            return $this;
        }

        $copy = clone $this;
        $copy->modifiers .= $modifiers;
        return $copy;
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
     * @throws \InvalidArgumentException
     * @since 1.4.0
     */
    public function compile()
    {
        if ($this->isCompiled) {
            return $this;
        }

        $this->checkMapNames($this->defines, self::RE_DEFINE_NAME);

        if (count($this->fixed) !== count(array_unique($this->fixed, SORT_STRING))) {
            throw new \InvalidArgumentException('Duplicating fixed strings found');
        }

        $inline = [];

        $fixed_hidden = [];
        $fixed_normal = [];
        $fixed_map = [];
        $this->splitTerminals($this->fixed, $inline, $fixed_hidden, $fixed_normal, $fixed_map);
        if ($inline) {
            throw new \InvalidArgumentException('Mixing inline tokens in fixed tokens map');
        }

        $inline = array_combine($this->inline, $this->inline);
        $more_inline = [];
        $hidden = [];
        $normal = [];
        $map = [];
        $this->splitTerminals($this->terminals, $more_inline, $hidden, $normal, $map);

        if ($more_inline) {
            // DEPRECATED: inline tokens in terminals map
            $inline += $more_inline;
        }

        $this->checkOverlappingNames([
            'fixed hidden' => $fixed_hidden,
            'fixed normal' => $fixed_normal,
            'inline' => $inline,
            'hidden' => $hidden,
            'normal' => $normal,
        ]);

        $this->hiddens = $fixed_hidden + $hidden;
        $this->aliased = [];
        $this->aliasOf = [];

        $fixed_and_inline_re_map = $this->buildFixedAndInlines($fixed_map, $inline);
        $same = array_intersect_key($map, $fixed_and_inline_re_map);
        if ($same) {
            throw new \LogicException("Duplicating inline and named tokens: " . join(', ', $same));
        }

        $this->regexpFixedAndInlineMap = $fixed_and_inline_re_map;
        $this->regexpTerminalsMap = $map;
        $terminals_map = $fixed_and_inline_re_map + $map;
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
            self::validateRegExp("/$re_defines\\G/" . $this->modifiers, 'DEFINEs');
            $regexp[] = $re_defines;
        } else {
            $re_defines = '';
        }
        $this->regexpDefines = $re_defines;

        foreach ($map as $name => $re_part) {
            self::validateRegExp(
                "/$re_defines\\G(?<$name>$re_part)/" . $this->modifiers,
                Symbol::dumpType($name) . " definition /$re_part/"
            );
        }

        $alt = $this->buildMap($terminals_map, '|');
        $regexp[] = "\\G(?:$alt)";

        $regexp = join('', $regexp);

        $this->regexp = "/$regexp/" . $this->modifiers;
        self::validateRegExp($this->regexp, 'main');

        if ($this->whitespaces) {
            $re_whitespaces = join('|', $this->whitespaces);
            $this->regexpWhitespace = "/$re_defines\\G(?:$re_whitespaces)+/" . $this->modifiers;
            self::validateRegExp($this->regexpWhitespace, 'whitespaces');
        } else {
            $this->regexpWhitespace = null;
        }

        $this->isCompiled = true;
        return $this;
    }

    /**
     * Was lexer compiled or not yet
     * @return bool
     * @since 1.4.0
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
     * Note: Parsing in this way will be context independent. To utilize context dependent
     * parsing use `parseOne()` in you own loop.
     *
     * @param string $input Input text to parse
     * @return \Generator|Token[] Returns generator of `Token`s. Generator has no its own return value.
     * @throws ParseException Nothing matched in a current position
     */
    public function parse($input)
    {
        $pos = 0;
        while (null !== ($match = $this->parseOne($input, $pos))) {
            $pos = $match->nextOffset;
            yield $match->token;
        }
    }

    /**
     * Parse one next token from input at the given offset
     * @param string $input Input text to parse
     * @param int $pos Offset to parse at
     * @param string[] $preferredTokens Preferred tokens types to match first
     * @return Match|null Returns match on success match. Returns `null` on EOF.
     * @since 1.5.0
     */
    public function parseOne($input, $pos, $preferredTokens = [])
    {
        $length = strlen($input);
        if ($pos >= $length) {
            return null;
        }

        $this->compile();

        $whitespace_length = $this->getWhitespaceLength($input, $pos);
        if ($whitespace_length) {
            $pos += $whitespace_length;
            if ($pos >= $length) {
                return null;
            }
        }

        $match = $this->match($input, $pos, $preferredTokens);
        if (!$match) {
            $near = substr($input, $pos, self::DUMP_NEAR_LENGTH);
            if ("" === $near || false === $near) {
                $near = '<EOF>';
            } else {
                $near = '"' . $near . '"';
            }
            throw new ParseException(
                "Cannot parse none of expected tokens near $near",
                $pos
            );
        }

        return $match;
    }

    /**
     * Extends array of named tokens
     * @param array $oldTokens Existing tokens
     * @param array $addTokens New tokens to add
     * @param string $errorType Tokens type to insert in error message
     * @return array New merged array of tokens
     * @throws \InvalidArgumentException In case of name duplication.
     * @since 1.5.0
     */
    private function addNamedTokens($oldTokens, $addTokens, $errorType)
    {
        $dup_keys = array_intersect_key($oldTokens, $addTokens);
        if ($dup_keys) {
            throw new \InvalidArgumentException(
                "Cannot redefine $errorType: " . var_export($dup_keys, true)
            );
        }

        return $oldTokens + $addTokens;
    }

    /**
     * Extends array of mixed tokens definition with another array of tokens
     *
     * Array of mixed tokens has both named (name=>definition) and anonymous
     * tokens (only definition in value).
     * @param array $oldTokens Existing tokens array
     * @param array $addTokens New tokens to add
     * @param string $errorType Tokens type to insert in error message.
     * @return array
     * @throws \InvalidArgumentException In case of name duplication.
     * @since 1.5.0
     */
    private function addMixedTokens($oldTokens, $addTokens, $errorType)
    {
        $new_terminals = $oldTokens;
        foreach ($addTokens as $name => $re) {
            if (is_int($name)) {
                $new_terminals[] = $re;
            } elseif (isset($new_terminals[$name])) {
                throw new \InvalidArgumentException(
                    "Cannot redefine $errorType: " . var_export($name, true)
                );
            } else {
                $new_terminals[$name] = $re;
            }
        }
        return $new_terminals;
    }

    /**
     * Split terminals definition in different types
     *
     * @param array $terminals Input terminals definitions array
     * @param array $inline Variable to store inline tokens. Both keys and value are same.
     * @param array $hidden Variable to store hidden named tokens. Key is name without leading
     * dot and value is non-null.
     * @param array $normal Variable to store normal named tokens. Key is name and value is non-null.
     * @param array $named Variable to store all named tokens. Key is name and value is definition.
     * @since 1.4.0
     */
    private function splitTerminals(array $terminals, &$inline, &$hidden, &$normal, &$named)
    {
        foreach ($terminals as $key => $value) {
            if (is_int($key)) {
                $inline[$value] = $value;
            } elseif (preg_match(self::RE_NAME, $key)) {
                if ('.' === substr($key, 0, 1)) {
                    $name = substr($key, 1);
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
     * Each token's name must be used only once. Inline tokens must not overlap
     * with names of named tokens.
     * @param array $maps Map on different tokens' maps. Key is token type for error message.
     * Value is tokens map of the type in form `[name => mixed, ...]`. Inline tokens use
     * its strings as name.
     * @throws \InvalidArgumentException Some overlapping names
     * @since 1.5.0
     */
    private function checkOverlappingNames(array $maps)
    {
        $index = 0;
        foreach ($maps as $type => $map) {
            $rest_maps = array_slice($maps, $index + 1, null, true);
            foreach ($rest_maps as $type2 => $map2) {
                $same = array_intersect_key($map, $map2);
                if ($same) {
                    throw new \InvalidArgumentException(
                        "Duplicating $type tokens and $type2 tokens: " . join(', ', $same)
                    );
                }
            }
            ++$index;
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
     * @param array $fixedMap Fixed tokens map
     * @param array $inlines List of inline token texts.
     * @return array Returns regexps map with generated names in keys. Also properties `$aliased`
     * and `$hiddens` will be updated.
     * @since 1.5.0
     */
    private function buildFixedAndInlines(array $fixedMap, array $inlines)
    {
        $overlapped = array_intersect($fixedMap, $inlines);
        if ($overlapped) {
            throw new \InvalidArgumentException(
                "Duplicating fixed tokens and inline tokens strings: "
                . join(', ', array_map('json_encode', $overlapped))
            );
        }

        // $fixedMap all keys are valid names, so + with integer keys will not loss anything
        $all = $fixedMap + array_values($inlines);

        // sort in reverse order to let more long items match first
        // so /'$$' | '$'/ will find ['$$', '$'] in '$$$' and not ['$', '$', '$']
        arsort($all, SORT_STRING);

        $re_map = [];

        $alias_name = 'a';
        foreach ($all as $name => $text) {
            // inline?
            if (is_int($name)) {
                $name = '_' . $alias_name;
                // string increment
                $alias_name++;

                $this->aliased[$name] = $text;
                $this->aliasOf[$text] = $name;
                $this->hiddens[$text] = true;
            }
            $re_map[$name] = $this->textToRegExp($text);
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
     * @param string[] $preferredTokens Preferred tokens types to match first
     * @return Match|null Match object on success match. `null` if no match found.
     * @throws \RuntimeException Error from PCRE
     * @throws DevException Error by end developer in lexer configuration
     * @throws InternalException Error in the package
     */
    private function match($input, $pos, $preferredTokens)
    {
        $current_regexp = $this->getRegexpForTokens($preferredTokens);

        if (false === preg_match($current_regexp, $input, $match, 0, $pos)) {
            $error_code = preg_last_error();
            throw new \RuntimeException(
                "PCRE error #" . $error_code
                . " for token at input pos $pos; REGEXP = $current_regexp",
                $error_code
            );
        }

        if (!$match) {
            return null;
        }

        $full_match = $match[0];
        if ('' === $full_match) {
            throw new DevException(
                'Tokens should not match empty string'
                . '; context: `' . substr($input, $pos, 10) . '`'
                . '; expected: ' . json_encode($preferredTokens)
                . "; REGEXP: $current_regexp"
            );
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
        $is_inline = false;
        if (isset($this->aliased[$type])) {
            $type = $this->aliased[$type];
            $is_inline = true;
        }
        $token = new Token($type, $content, $match, $pos, isset($this->hiddens[$type]), $is_inline);

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
     * @param string[] $preferredTokens
     * @return string
     */
    private function getRegexpForTokens($preferredTokens)
    {
        if (!$preferredTokens) {
            return $this->regexp;
        }

        $key = json_encode($preferredTokens);
        if (isset($this->regexpForTokens[$key])) {
            return $this->regexpForTokens[$key];
        }

        $fixed_names = [];
        $terminals_names = [];
        foreach ($preferredTokens as $type) {
            if (isset($this->aliasOf[$type])) {
                $fixed_names[$this->aliasOf[$type]] = true;
            } elseif (isset($this->regexpFixedAndInlineMap[$type])) {
                $fixed_names[$type] = true;
            } elseif (isset($this->regexpTerminalsMap[$type])) {
                $terminals_names[$type] = $this->regexpTerminalsMap[$type];
            } else {
                throw new \InvalidArgumentException("Unknown token type: `$type`");
            }
        }

        $terminals_map =
            // enum in regexp order
            array_intersect_key($this->regexpFixedAndInlineMap, $fixed_names)
            // enum in expectation order, so subject non-terminal
            // can define order by its alternatives
            + $terminals_names;

        // add rest regexps
        $terminals_map += array_diff_key($this->regexpFixedAndInlineMap, $fixed_names);
        $terminals_map += array_diff_key($this->regexpTerminalsMap, $terminals_names);

        $regexps = [];
        if ($this->regexpDefines) {
            $regexps[] = $this->regexpDefines;
        }
        $alt = $this->buildMap($terminals_map, '|');
        $regexps[] = "\\G(?:$alt)";
        $regexp = join('', $regexps);
        $regexp = "/$regexp/" . $this->modifiers;
        self::validateRegExp($regexp, 'partial');

        return $this->regexpForTokens[$key] = $regexp;
    }

    /**
     * Check names in regexps map for validity
     * @param array $map Map of regexp parts to check
     * @param string $nameRegExp Regexp for names to check against.
     * @throws \InvalidArgumentException Some bad named was found.
     * @since 1.4.0
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

    /**
     * Validate given RegExp
     * @param string $regExp RegExp to validate
     * @param string $displayName Display name for error message
     * @since 1.5.0
     * @throws \InvalidArgumentException
     */
    private static function validateRegExp($regExp, $displayName)
    {
        /** @uses convertErrorToException() */
        set_error_handler([__CLASS__, 'convertErrorToException'], E_WARNING);
        try {
            if (false === preg_match($regExp, null)) {
                throw new \InvalidArgumentException(
                    "PCRE error in $displayName RegExp: $regExp",
                    preg_last_error()
                );
            }
        } catch (\ErrorException $e) {
            throw new \InvalidArgumentException(
                "PCRE error in $displayName RegExp: " . $e->getMessage() . "; RegExp: $regExp",
                preg_last_error(),
                $e
            );
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @throws \ErrorException
     */
    private static function convertErrorToException($level, $message, $file, $line)
    {
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Escape plain text to be a RegExp matching the text
     *
     * Native `preg_quote()` does not care about possible `/x` modifier and does
     * not take modifiers with delimiter. So, with `/x` modifier we use `\Q...\E`
     * instead if `$text` contains some `/x`-mode meta characters: `#` or spaces.
     * @param string $text Plain text
     * @return string RegExp matching the text
     * @since 1.5.0
     * @see https://3v4l.org/brdeT Comparison between `preg_quote()` and `\Q...\E`
     * when `/x` modifier is used
     * @see https://3v4l.org/tnnap This function test
     */
    protected function textToRegExp($text)
    {
        // preg_quote() is useless with /x modifier: https://3v4l.org/brdeT

        if (false === strpos($this->modifiers, 'x') || !preg_match('~[\\s/#]|\\\\E~', $text)) {
            return preg_quote($text, '/');
        }

        // foo\Efoo --> \Qfoo\E\\\QEfoo\E
        // ---^====       ---  ^^  ====
        //
        // foo/foo  --> \Qfoo\E\/\Qfoo\E
        // ---^===        ---  ^^  ===
        return '\\Q' . strtr($text, ['\\E' => '\\E\\\\\\QE', '/' => '\\E\\/\\Q']) . '\\E';
    }
}
