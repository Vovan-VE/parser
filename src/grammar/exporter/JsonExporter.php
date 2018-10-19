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
     * @refact PHP >= 7.0: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
     */
    public $jsonOptions = 320;

    public function exportGrammar($grammar)
    {
        return json_encode(parent::exportGrammar($grammar), $this->jsonOptions);
    }
}
