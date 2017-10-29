<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Token;

/**
 * Matched token
 *
 * This lass is used internally in Lexer.
 * @package VovanVE\parser
 */
class Match extends BaseObject
{
    /** @var Token Mathed token */
    public $token;
    /** @var int Position to continue search from. */
    public $nextOffset;
}
