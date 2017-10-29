<?php
namespace VovanVE\parser;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\lexer\Lexer;

/**
 * Helper class to simplify Lexer creation
 * @package VovanVE\parser
 * @deprecated Use `Lexer` constructor directly, optionally simplified
 * with expansion methods.
 */
class LexerBuilder extends BaseObject
{
    /** @var array */
    public $defines = [];
    /** @var array */
    public $whitespaces = ['\\s+'];
    /** @var array */
    public $terminals = [];
    /** @var string */
    public $modifiers = 'u';

    /**
     * @return Lexer
     */
    public function create()
    {
        return new Lexer(
            $this->terminals,
            $this->whitespaces,
            $this->defines,
            $this->modifiers
        );
    }

    /**
     * @param array $defines
     * @return $this
     */
    public function defines(array $defines)
    {
        $this->defines = $defines;
        return $this;
    }

    /**
     * @param array $whitespaces
     * @return $this
     */
    public function whitespaces(array $whitespaces)
    {
        $this->whitespaces = $whitespaces;
        return $this;
    }

    /**
     * @param array $terminals
     * @return $this
     */
    public function terminals($terminals)
    {
        $this->terminals = $terminals;
        return $this;
    }

    /**
     * @param  $modifiers
     * @return $this
     */
    public function modifiers($modifiers)
    {
        $this->modifiers = $modifiers;
        return $this;
    }
}
