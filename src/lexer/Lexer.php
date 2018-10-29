<?php
namespace VovanVE\parser\lexer;

/**
 * Just a Lexer from old version
 *
 * This Lexer is not used neither by Grammar nor by Parser. It is used
 * for tests only to test base class, which is ancestor of Grammar class.
 * You can used this class for dev or test purpose. For real work with Grammar
 * just use Grammar.
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
 * ```
 *
 * Mostly you can define only named tokens. Inline tokens will be added later
 * from grammar.
 *
 * @package VovanVE\parser
 */
class Lexer extends BaseLexer
{
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
    public function defines(array $defines): self
    {
        if (!$defines) {
            return $this;
        }

        $dup_keys = array_intersect_key($this->defines, $defines);
        if ($dup_keys) {
            throw new \InvalidArgumentException(
                "Cannot redefine defines: " . join(', ', array_keys($dup_keys))
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
     * @param string[] $whitespaces Additional whitespaces regexps. Duplicating currently
     * is not checked, so it on your own.
     * @return static
     * @since 1.4.0
     */
    public function whitespaces(array $whitespaces): self
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
     * @param string[] $fixed Additional fixed tokens. Duplicating names are restricted.
     * @return static
     * @throws \InvalidArgumentException In case of name duplication.
     * @since 1.5.0
     */
    public function fixed(array $fixed): self
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
    public function inline(array $inline): self
    {
        if (!$inline) {
            return $this;
        }

        $copy = clone $this;
        $copy->inlines = array_merge($this->inlines, $inline);
        return $copy;
    }

    /**
     * Create new Lexer extending this one with terminals
     *
     * Named tokens defined with regexps. Named regexps from DEFINEs can be
     * referenced here with named recursion `(?&name)`.
     *
     * > Note: Returned object can be the same one in case of empty additions.
     * @param string[] $terminals Additional terminals. Only named are acceptable.
     * is restricted.
     * @return static
     * @since 1.4.0
     */
    public function terminals(array $terminals): self
    {
        if (!$terminals) {
            return $this;
        }

        $new_terminals = $this->addNamedTokens($this->regexpMap, $terminals, 'terminal');

        $copy = clone $this;
        $copy->regexpMap = $new_terminals;
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
    public function modifiers(string $modifiers): self
    {
        if ('' === $modifiers) {
            return $this;
        }

        $copy = clone $this;
        $copy->modifiers .= $modifiers;
        return $copy;
    }

    /**
     * Extends array of named tokens
     * @param string[] $oldTokens Existing tokens
     * @param string[] $addTokens New tokens to add
     * @param string $errorType Tokens type to insert in error message
     * @return string[] New merged array of tokens
     * @throws \InvalidArgumentException In case of name duplication.
     * @since 1.5.0
     */
    private function addNamedTokens(array $oldTokens, array $addTokens, string $errorType): array
    {
        $dup_keys = array_intersect_key($oldTokens, $addTokens);
        if ($dup_keys) {
            throw new \InvalidArgumentException(
                "Cannot redefine $errorType: " . join(', ', array_keys($dup_keys))
            );
        }

        return $oldTokens + $addTokens;
    }
}
