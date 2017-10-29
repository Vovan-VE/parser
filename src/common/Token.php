<?php
namespace VovanVE\parser\common;

/**
 * Matched terminal token from an input
 * @package VovanVE\parser
 */
class Token extends BaseObject implements TreeNodeInterface
{
    /** @var string Type of token which is `Symbol` name */
    private $type;
    /** @var string Matched content of the token */
    private $content;
    /** @var bool Whether then token is hidden with respect to `Symbol` definition */
    private $isHidden = false;
    /** @var array|null Match data for the token given from `preg_match()` */
    private $match;
    /** @var integer|null Position of the token in the input text */
    private $offset;
    /** @var mixed Value made with action */
    private $made;

    /**
     * @param string $type Type of token which is `Symbol` name
     * @param string $content Matched content of the token
     * @param null $match Match data for the token given from `preg_match()`
     * @param null $offset Position of the token in the input text
     * @param bool $isHidden [since 1.4.0] Whether then token is hidden with respect to `Symbol`
     * definition
     */
    public function __construct($type, $content, $match = null, $offset = null, $isHidden = false)
    {
        $this->type = $type;
        $this->content = $content;
        $this->match = $match;
        $this->offset = $offset;
        $this->isHidden = $isHidden;
    }

    /**
     * Type of token which is `Symbol` name
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Matched content of the token
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Whether then token is hidden with respect to `Symbol` definition
     * @return bool
     * @since 1.4.0
     */
    public function isHidden()
    {
        return $this->isHidden;
    }

    /**
     * Match data for the token given from `preg_match()`
     * @return array|null
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * Position of the token in the input text
     * @return integer
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @inheritdoc
     */
    public function getNodeName()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getNodeTag()
    {
        return null;
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildrenCount()
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getChild($index)
    {
        throw new \OutOfBoundsException('No children');
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildren()
    {
        return [];
    }

    /**
     * @param string[] $nodeNames
     * @return bool
     * @since 1.2.0
     */
    public function areChildrenMatch($nodeNames)
    {
        return [] === $nodeNames;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString($indent = '', $last = true)
    {
        return $indent . ' `- ' . $this->type . ' <' . $this->content . '>' . PHP_EOL;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function make($value)
    {
        $this->made = $value;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function made()
    {
        return $this->made;
    }
}
