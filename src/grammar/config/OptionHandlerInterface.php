<?php
namespace VovanVE\parser\grammar\config;

/**
 * Interface for internal handling grammar config options
 * @package VovanVE\parser
 * @since 1.5.0
 */
interface OptionHandlerInterface
{
    /**
     * Assign value to data and return new data
     * @param mixed $data
     * @param string|null $tag
     * @param string $value
     * @param bool $isRegExp
     * @return mixed New updated data
     */
    public function updateData($data, $tag, $value, $isRegExp);
}
