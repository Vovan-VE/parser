<?php
namespace VovanVE\parser\lexer;

use VovanVE\parser\SyntaxException;

/**
 * Cannot match any of defined tokens in the given position
 * @package VovanVE\parser
 * @deprecated >= 1.6.0: Replaced with `UnknownCharacterException`
 */
class ParseException extends SyntaxException
{
}
