<?php

declare(strict_types=1);

namespace dobron\DomForge;

use dobron\DomForge\Traits\NodeAttributeTrait;
use dobron\DomForge\Traits\NodeSelectorTrait;
use dobron\DomForge\Traits\NodeTraversalTrait;

/**
 * @property string $outerHtml
 * @property string $innerHtml
 * @property-read string $textContent
 */
class Node
{
    use NodeAttributeTrait;
    use NodeSelectorTrait;
    use NodeTraversalTrait;

    /** @var int */
    public $nodetype = DomForge::TYPE_TEXT;

    /** @var string */
    public $tag = 'text';

    /** @var \stdClass */
    public $context;

    /** @var array */
    public $attributes = [];

    /** @var Node[] */
    public $children = [];

    /** @var Node[] */
    public $nodes = [];

    /** @var Node|null */
    public $parent = null;

    /** @var array */
    public $info = [];

    /** @var int */
    public $tagStart = 0;

    /** @var DomForge|null */
    private $dom;

    /** @var array<string, string[]|false> Source/target charset pair => [source, target], or false if no conversion is needed */
    private static $charsetConversions = [];

    public function __construct(DomForge $dom)
    {
        $this->dom = $dom;
        unset($this->context);
        $dom->nodes[] = $this;
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function __toString(): string
    {
        return $this->outerHtml();
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'context') {
            return $this->context = new \stdClass();
        }

        return $this->readProperty($name);
    }

    /**
     * @return mixed
     */
    protected function readProperty(string $name)
    {
        if (isset($this->attributes[$name])) {
            $value = $this->attributes[$name];

            if ($value === true) {
                return true;
            }

            return is_string($value) ? $this->convertText($value) : $value;
        }

        switch ($name) {
            case 'outerHtml':
                return $this->outerHtml();
            case 'innerHtml':
                return $this->innerHtml();
            case 'textContent':
                return $this->textContent();
        }

        return array_key_exists($name, $this->attributes);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'context':
                $this->context = $value;

                return;
            case 'outerHtml':
                $this->info[DomForge::INFO_OUTER] = $value;

                return;
            case 'innerHtml':
                if (isset($this->info[DomForge::INFO_TEXT])) {
                    $this->info[DomForge::INFO_TEXT] = $value;
                } else {
                    $this->info[DomForge::INFO_INNER] = $value;
                }

                $this->rebuild();

                return;
        }

        if (! isset($this->attributes[$name])) {
            $this->info[DomForge::INFO_SPACE][] = [' ', '', ''];
            $this->info[DomForge::INFO_QUOTE][] = DomForge::QUOTE_DOUBLE;
        }

        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        switch ($name) {
            case 'context':
            case 'outerHtml':
            case 'innerHtml':
            case 'textContent':
                return true;
        }

        return array_key_exists($name, $this->attributes) || isset($this->attributes[$name]);
    }

    public function __unset(string $name): void
    {
        if ($name !== 'context') {
            $this->removeAttribute($name);
        }
    }

    public function clear(): void
    {
        $this->dom = null;
        $this->nodes = [];
        $this->parent = null;
        $this->children = [];
        unset($this->context);
    }

    public function dom(): DomForge
    {
        return $this->dom;
    }

    public function innerHtml(): string
    {
        if (isset($this->info[DomForge::INFO_INNER])) {
            return $this->convertText($this->info[DomForge::INFO_INNER]);
        }

        if (isset($this->info[DomForge::INFO_TEXT])) {
            $text = $this->info[DomForge::INFO_TEXT];

            return $this->convertText($this->dom ? $this->dom->restoreNoise($text) : $text);
        }

        $result = '';
        foreach ($this->nodes as $node) {
            $result .= $node->outerHtml();
        }

        return $result;
    }

    public function outerHtml(): string
    {
        if ($this->tag === 'root') {
            return $this->innerHtml();
        }

        if ($this->dom && $this->dom->callback !== null) {
            call_user_func_array($this->dom->callback, [$this]);
        }

        if (isset($this->info[DomForge::INFO_OUTER])) {
            return $this->convertText($this->info[DomForge::INFO_OUTER]);
        }

        if (isset($this->info[DomForge::INFO_TEXT])) {
            $text = $this->info[DomForge::INFO_TEXT];

            return $this->convertText($this->dom ? $this->dom->restoreNoise($text) : $text);
        }

        $result = $this->makeup();

        $result = $this->convertText($result);

        if (isset($this->info[DomForge::INFO_INNER])) {
            if ($this->tag !== 'br') {
                $result .= $this->convertText($this->info[DomForge::INFO_INNER]);
            }
        } elseif ($this->nodes) {
            foreach ($this->nodes as $node) {
                $result .= $node->outerHtml();
            }
        }

        if (isset($this->info[DomForge::INFO_END])) {
            if ($this->info[DomForge::INFO_END] != 0) {
                $result .= '</' . $this->tag . '>';
            }
        } elseif (! isset($this->info[DomForge::INFO_BEGIN]) && $this->nodetype === DomForge::TYPE_ELEMENT && ! DomForge::isSelfClosingTag($this->tag)) {
            $result .= '</' . $this->tag . '>';
        }

        return $result;
    }

    public function textContent(): string
    {
        if (isset($this->info[DomForge::INFO_INNER])) {
            return $this->convertText($this->info[DomForge::INFO_INNER]);
        }

        switch ($this->nodetype) {
            case DomForge::TYPE_TEXT:
                $text = $this->info[DomForge::INFO_TEXT] ?? '';

                return $this->convertText($this->dom ? $this->dom->restoreNoise($text) : $text);
            case DomForge::TYPE_COMMENT:
            case DomForge::TYPE_UNKNOWN:
                return '';
        }

        if (strcasecmp($this->tag, 'script') === 0 || strcasecmp($this->tag, 'style') === 0) {
            return '';
        }

        $parts = [];
        if ($this->nodes !== null) {
            foreach ($this->nodes as $node) {
                if ($node->tag === 'p') {
                    $this->trimParts($parts);
                    $parts[] = "\n\n";
                }
                $parts[] = $node->textContent();
                if ($node->tag === 'span' && $this->dom) {
                    $parts[] = $this->dom->getConfiguration()->getDefaultSpanText();
                }
            }
        }

        return implode('', $parts);
    }

    /**
     * @param string[] $parts
     */
    protected function trimParts(array &$parts): void
    {
        while ($parts) {
            $last = rtrim(array_pop($parts));
            if ($last !== '') {
                $parts[] = $last;

                break;
            }
        }

        $start = 0;
        $count = count($parts);
        while ($start < $count && ($first = ltrim($parts[$start])) === '') {
            ++$start;
        }

        if ($start < $count) {
            $parts[$start] = $first;
        }

        if ($start > 0) {
            $parts = array_slice($parts, $start);
        }
    }

    public function makeup(): string
    {
        if ($this->tag === 'root') {
            return '';
        }

        $result = '<' . $this->tag;
        $attrIndex = -1;

        foreach ($this->attributes as $attrName => $attrValue) {
            ++$attrIndex;
            if ($attrValue === null || $attrValue === false) {
                continue;
            }

            $spaceInfo = $this->info[DomForge::INFO_SPACE][$attrIndex] ?? [' ', '', ''];
            $result .= $spaceInfo[0];

            if ($attrValue === true) {
                $result .= $attrName;
            } else {
                $quote = $this->info[DomForge::INFO_QUOTE][$attrIndex] ?? DomForge::QUOTE_DOUBLE;
                $equalSign = $spaceInfo[1] . '=' . $spaceInfo[2];

                switch ($quote) {
                    case DomForge::QUOTE_DOUBLE:
                        $result .= $attrName . $equalSign . '"' . $attrValue . '"';

                        break;
                    case DomForge::QUOTE_SINGLE:
                        $result .= $attrName . $equalSign . "'" . $attrValue . "'";

                        break;
                    default:
                        $result .= $attrName . $equalSign . $attrValue;
                }
            }
        }

        $result .= $this->info[DomForge::INFO_ENDSPACE] ?? '';

        return $result . '>';
    }

    public function convertText(string $text): string
    {
        if (! $this->dom || ! preg_match('/[\x80-\xFF]/', $text)) {
            return $text;
        }

        $targetCharset = $this->dom->getConfiguration()->getTargetCharset();
        $cacheKey = $this->dom->charset . '|' . $targetCharset;

        if (! isset(self::$charsetConversions[$cacheKey])) {
            $source = strtoupper($this->dom->charset);
            $target = strtoupper($targetCharset);
            self::$charsetConversions[$cacheKey] = empty($source) || empty($target) || $source === $target ? false : [$source, $target];
        }

        $conversion = self::$charsetConversions[$cacheKey];
        if ($conversion === false || ($conversion[1] === 'UTF-8' && mb_check_encoding($text, 'UTF-8'))) {
            return $this->stripBom($text);
        }

        $converted = @iconv($conversion[0], $conversion[1], $text);

        return $this->stripBom($converted !== false ? $converted : $text);
    }

    public function isElement(): bool
    {
        return $this->nodetype === DomForge::TYPE_ELEMENT;
    }

    public function isText(): bool
    {
        return $this->nodetype === DomForge::TYPE_TEXT;
    }

    public function isComment(): bool
    {
        return $this->nodetype === DomForge::TYPE_COMMENT;
    }

    public function isSelfClosing(): bool
    {
        return isset($this->info[DomForge::INFO_ENDSPACE]) && strpos($this->info[DomForge::INFO_ENDSPACE], '/') !== false;
    }

    public function isNamespacedElement(): bool
    {
        return strpos($this->tag, ':') !== false;
    }

    protected function rebuild(): void
    {
        $innerHtml = $this->innerHtml();

        $this->nodes = [];
        $this->children = [];

        unset($this->info[DomForge::INFO_INNER]);

        if (empty(trim($innerHtml))) {
            return;
        }

        $tempDom = DomForge::fromHtml('<root>' . $innerHtml . '</root>', $this->dom ? $this->dom->getConfiguration() : null);
        $tempRoot = $tempDom->findOne('root');

        if ($tempRoot) {
            foreach ($tempRoot->nodes as $child) {
                $child->parent = $this;
                $this->nodes[] = $child;
            }
            foreach ($tempRoot->children as $child) {
                $this->children[] = $child;
            }
        }
    }

    protected function stripBom(string $text): string
    {
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            return substr($text, 3);
        }

        return $text;
    }
}
