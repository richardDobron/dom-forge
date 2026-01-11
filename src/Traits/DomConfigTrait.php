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

    /**
     * @param string $tag
     * @return void
     */
    public static function addSelfClosingTag(string $tag)
    {
        $tagLower = strtolower($tag);
        if (! in_array($tagLower, self::$selfClosingTags, true)) {
            self::$selfClosingTags[] = $tagLower;
        }
    }

    /**
     * @param string $tag
     * @return void
     */
    public static function removeSelfClosingTag(string $tag)
    {
        $tagLower = strtolower($tag);
        $index = array_search($tagLower, self::$selfClosingTags, true);
        if ($index !== false) {
            array_splice(self::$selfClosingTags, $index, 1);
        }
    }

    /**
     * @param string[] $tags
     * @return void
     */
    public static function registerSelfClosingTags(array $tags)
    {
        self::$selfClosingTags = array_unique(
            array_merge(self::$selfClosingTags, array_map('strtolower', array_values($tags)))
        );
    }

    /**
     * @return string[]
     */
    public static function getSelfClosingTags(): array
    {
        return self::$selfClosingTags;
    }

    /**
     * @return void
     */
    public static function resetSelfClosingTags()
    {
        self::$selfClosingTags = [
            'area', 'base', 'br', 'col', 'embed',
            'hr', 'img', 'input', 'link', 'meta',
            'param', 'source', 'track', 'wbr',
        ];
    }

    public static function isSelfClosingTag(string $tag): bool
    {
        return in_array(strtolower($tag), self::$selfClosingTags, true);
    }
}
