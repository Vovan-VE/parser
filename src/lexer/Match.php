<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\Token;

class Match extends BaseObject
{
    /** @var Token */
    public $token;
    /** @var int */
    public $nextOffset;
}
