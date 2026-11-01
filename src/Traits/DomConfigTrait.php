<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

trait DomConfigTrait
{
    /** @var string[] */
    protected static $selfClosingTags = [
        'area', 'base', 'br', 'col', 'embed',
        'hr', 'img', 'input', 'link', 'meta',
        'param', 'source', 'track', 'wbr',
    ];

    /** @var array<string, int>|null Lookup map built from $selfClosingTags, reset whenever the list changes */
    protected static $selfClosingTagMap = null;

    public static function addSelfClosingTag(string $tag): void
    {
        $tagLower = strtolower($tag);
        if (! in_array($tagLower, self::$selfClosingTags, true)) {
            self::$selfClosingTags[] = $tagLower;
            self::$selfClosingTagMap = null;
        }
    }

    public static function removeSelfClosingTag(string $tag): void
    {
        $tagLower = strtolower($tag);
        $index = array_search($tagLower, self::$selfClosingTags, true);
        if ($index !== false) {
            array_splice(self::$selfClosingTags, $index, 1);
            self::$selfClosingTagMap = null;
        }
    }

    /**
     * @param string[] $tags
     */
    public static function registerSelfClosingTags(array $tags): void
    {
        self::$selfClosingTags = array_unique(
            array_merge(self::$selfClosingTags, array_map('strtolower', array_values($tags)))
        );
        self::$selfClosingTagMap = null;
    }

    /**
     * @return string[]
     */
    public static function getSelfClosingTags(): array
    {
        return self::$selfClosingTags;
    }

    public static function resetSelfClosingTags(): void
    {
        self::$selfClosingTags = [
            'area', 'base', 'br', 'col', 'embed',
            'hr', 'img', 'input', 'link', 'meta',
            'param', 'source', 'track', 'wbr',
        ];
        self::$selfClosingTagMap = null;
    }

    public static function isSelfClosingTag(string $tag): bool
    {
        if (self::$selfClosingTagMap === null) {
            self::$selfClosingTagMap = array_flip(self::$selfClosingTags);
        }

        return isset(self::$selfClosingTagMap[strtolower($tag)]);
    }
}
