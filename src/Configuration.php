<?php

declare(strict_types=1);

namespace dobron\DomForge;

final class Configuration
{
    /** @var string */
    private $targetCharset = 'UTF-8';

    /** @var bool */
    private $lowercase = true;

    /** @var bool */
    private $forceTagsClosed = true;

    /** @var bool */
    private $removeLineBreaks = true;

    /** @var string */
    private $defaultBrText = "\n";

    /** @var string */
    private $defaultSpanText = ' ';

    /** @var string[]|null */
    private $selfClosingTags = null;

    public static function create(): self
    {
        return new self();
    }

    public function getTargetCharset(): string
    {
        return $this->targetCharset;
    }

    public function setTargetCharset(string $targetCharset): self
    {
        $this->targetCharset = $targetCharset;

        return $this;
    }

    public function isLowercase(): bool
    {
        return $this->lowercase;
    }

    public function setLowercase(bool $lowercase): self
    {
        $this->lowercase = $lowercase;

        return $this;
    }

    public function isForceTagsClosed(): bool
    {
        return $this->forceTagsClosed;
    }

    public function setForceTagsClosed(bool $forceTagsClosed): self
    {
        $this->forceTagsClosed = $forceTagsClosed;

        return $this;
    }

    public function shouldRemoveLineBreaks(): bool
    {
        return $this->removeLineBreaks;
    }

    public function setRemoveLineBreaks(bool $removeLineBreaks): self
    {
        $this->removeLineBreaks = $removeLineBreaks;

        return $this;
    }

    public function getDefaultBrText(): string
    {
        return $this->defaultBrText;
    }

    public function setDefaultBrText(string $defaultBrText): self
    {
        $this->defaultBrText = $defaultBrText;

        return $this;
    }

    public function getDefaultSpanText(): string
    {
        return $this->defaultSpanText;
    }

    public function setDefaultSpanText(string $defaultSpanText): self
    {
        $this->defaultSpanText = $defaultSpanText;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getSelfClosingTags(): ?array
    {
        return $this->selfClosingTags;
    }

    /**
     * @param string[] $tags
     * @return self
     */
    public function setSelfClosingTags(array $tags): self
    {
        $this->selfClosingTags = $tags;

        return $this;
    }

    public function addSelfClosingTags(string ...$tags): self
    {
        if ($this->selfClosingTags === null) {
            $this->selfClosingTags = [];
        }
        $this->selfClosingTags = array_merge($this->selfClosingTags, $tags);

        return $this;
    }
}
