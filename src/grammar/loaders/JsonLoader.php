<?php
namespace VovanVE\parser\grammar\loaders;

use VovanVE\parser\grammar\Grammar;
use VovanVE\parser\grammar\GrammarException;

/**
 * Class JsonLoader
 * @package VovanVE\parser
 * @since 1.7.0
 */
class JsonLoader extends ArrayLoader
{
    /**
     * Create grammar object from a text
     *
     * See class description for details.
     * @param string $json Grammar definition array
     * @return Grammar Grammar object
     * @throws GrammarException Errors in grammar syntax or logic
     */
    public static function createGrammar($json)
    {
        $array = json_decode($json, true);
        if (null === $array && 'null' !== $json) {
            throw new \InvalidArgumentException('JSON error: ' . json_last_error_msg());
        }
        if (!is_array($array)) {
            throw new \InvalidArgumentException('Incorrect JSON data');
        }

        return parent::createGrammar($array);
    }
}
