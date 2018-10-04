<?php
namespace VovanVE\parser\errors;

use VovanVE\parser\lexer\ParseException;

/**
 * Cannot match any of defined tokens in the given position
 * @package VovanVE\parser
 * @since 1.6.0
 */
class UnknownCharacterException extends ParseException
{
}
