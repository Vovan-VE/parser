<?php
namespace VovanVE\parser\grammar\config;

use VovanVE\parser\common\BaseObject;
use VovanVE\parser\common\InternalException;
use VovanVE\parser\grammar\GrammarException;

/**
 * Whitespaces option handler for grammar config
 * @package VovanVE\parser
 * @since 1.5.0
 */
class WhiteSpaceOption extends BaseObject implements OptionHandlerInterface
{
    /**
     * @inheritdoc
     */
    public function updateData($data, $tag, $value, $isRegExp)
    {
        if (null !== $tag) {
            throw new GrammarException('Option does not allow `(tag)`');
        }
        if (!is_array($data)) {
            throw new InternalException('Source data is not array');
        }
        $result = $data;
        $result[(int)$isRegExp][] = $value;
        return $result;
    }
}
