<?php
namespace VovanVE\parser\grammar\exporter;

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

    public function exportGrammar($grammar)
    {
        return json_encode(parent::exportGrammar($grammar), $this->jsonOptions);
    }
}
