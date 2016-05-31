<?php
namespace VovanVE\parser\common;

class BaseObject
{
    /**
     * @return string
     */
    public static function className()
    {
        return get_called_class();
    }
}
