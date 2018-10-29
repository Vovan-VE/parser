<?php
namespace VovanVE\parser\grammar\exporter;

use VovanVE\parser\grammar\Grammar;

/**
 * Class JsonExporter
 * @package VovanVE\parser
 * @since 1.7.0
 */
class JsonExporter extends ArrayExporter
{
    /**
     * @var int
     */
    public $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param Grammar $grammar
     * @return string
     */
    public function exportGrammar(Grammar $grammar)
    {
        return json_encode(parent::exportGrammar($grammar), $this->jsonOptions);
    }
}
