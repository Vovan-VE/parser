<?php
namespace VovanVE\parser\common;

class Token extends BaseObject
{
    /** @var string */
    public $type;
    /** @var string */
    public $content;
    /** @var array */
    public $match;
}
