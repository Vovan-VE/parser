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
    /** @var bool Whether then token is inline with respect to `Grammar` inline tokens */
    private $isInline = false;
    /** @var array|null Match data for the token given from `preg_match()` */
    private $match;
    /** @var int|null Position of the token in the input text */
    private $offset;
    /** @var mixed Value made with action */
    private $made;

    /**
     * @param string $type Type of token which is `Symbol` name
     * @param string $content Matched content of the token
     * @param array|null $match Match data for the token given from `preg_match()`
     * @param int|null $offset Position of the token in the input text
     * @param bool $isHidden [since 1.4.0] Whether then token is hidden with respect to `Symbol`
     * definition
     * @param bool $isInline [since 1.5.0] Whether then token is inline with respect to `Grammar`
     * inline tokens
     */
    public function __construct(
        string $type,
        string $content,
        ?array $match = null,
        ?int $offset = null,
        bool $isHidden = false,
        bool $isInline = false
    ) {
        $this->type = $type;
        $this->content = $content;
        $this->match = $match;
        $this->offset = $offset;
        $this->isHidden = $isHidden;
        $this->isInline = $isInline;
    }

    /**
     * Type of token which is `Symbol` name
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Matched content of the token
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Whether then token is hidden with respect to `Symbol` definition
     * @return bool
     * @since 1.4.0
     */
    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * Whether then token is inline with respect to `Grammar` inline tokens
     * @return bool
     * @since 1.5.0
     */
    public function isInline(): bool
    {
        return $this->isInline;
    }

    /**
     * Match data for the token given from `preg_match()`
     * @return array|null
     */
    public function getMatch(): ?array
    {
        return $this->match;
    }

    /**
     * Position of the token in the input text
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @inheritdoc
     */
    public function getNodeName(): string
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getNodeTag(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildrenCount(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function getChild(int $index): TreeNodeInterface
    {
        throw new \OutOfBoundsException('No children');
    }

    /**
     * @inheritdoc
     * @since 1.1.0
     */
    public function getChildren(): array
    {
        return [];
    }

    /**
     * @param string[] $nodeNames
     * @return bool
     * @since 1.2.0
     */
    public function areChildrenMatch(array $nodeNames): bool
    {
        return [] === $nodeNames;
    }

    /**
     * @inheritdoc
     */
    public function dumpAsString(string $indent = '', bool $last = true): string
    {
        return $indent . ' `- ' . $this->type . ' <' . $this->content . '>' . PHP_EOL;
    }

    /**
     * @inheritdoc
     * @since 1.3.0
     */
    public function make($value): void
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

    /**
     * @inheritdoc
     * @since 2.0.0
     */
    public function prune(): void
    {
        $this->content = '';
    }
}
