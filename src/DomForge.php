<?php

declare(strict_types=1);

namespace dobron\DomForge;

use dobron\DomForge\Traits\DomConfigTrait;
use dobron\DomForge\Traits\DomFinderTrait;

/**
 * @property-read string $outerHtml
 * @property-read string $innerHtml
 * @property-read string $textContent
 */
class DomForge
{
    use DomConfigTrait;
    use DomFinderTrait;

    // Node types
    public const TYPE_ELEMENT = 1;
    public const TYPE_COMMENT = 2;
    public const TYPE_TEXT = 3;
    public const TYPE_ENDTAG = 4;
    public const TYPE_ROOT = 5;
    public const TYPE_UNKNOWN = 6;

    // Quote types
    public const QUOTE_DOUBLE = 0;
    public const QUOTE_SINGLE = 1;
    public const QUOTE_NO = 3;

    // Info array keys
    public const INFO_BEGIN = 0;
    public const INFO_END = 1;
    public const INFO_QUOTE = 2;
    public const INFO_SPACE = 3;
    public const INFO_TEXT = 4;
    public const INFO_INNER = 5;
    public const INFO_OUTER = 6;
    public const INFO_ENDSPACE = 7;

    protected const WHITESPACE_CHARS = " \t\r\n";

    protected const BLOCK_TAGS = [
        'body', 'div', 'form', 'root', 'span', 'table',
    ];

    /** @var Configuration */
    protected $configuration;

    /** @var Node|null */
    public $root = null;

    /** @var Node[] */
    public $nodes = [];

    /** @var callable|null */
    public $callback = null;

    /** @var int */
    public $originalSize = 0;

    /** @var int */
    public $size = 0;

    /** @var string */
    public $charset = '';

    /** @var int */
    protected $position = 0;

    /** @var string */
    protected $html = '';

    /** @var string|null */
    protected $currentChar = null;

    /** @var int */
    protected $cursor = 0;

    /** @var Node|null */
    protected $currentParent = null;

    /** @var array */
    protected $noiseMap = [];

    /** @var array<string, string[]> */
    protected $optionalClosingTags = [
        'b' => ['b'],
        'dd' => ['dd', 'dt'],
        'dl' => ['dd', 'dt'],
        'dt' => ['dd', 'dt'],
        'li' => ['li'],
        'optgroup' => ['optgroup', 'option'],
        'option' => ['optgroup', 'option'],
        'p' => ['p'],
        'rp' => ['rp', 'rt'],
        'rt' => ['rp', 'rt'],
        'td' => ['td', 'th'],
        'th' => ['td', 'th'],
        'tr' => ['td', 'th', 'tr'],
    ];

    public function __construct(?Configuration $configuration = null)
    {
        $this->configuration = $configuration ?? new Configuration();

        if (! $this->configuration->isForceTagsClosed()) {
            $this->optionalClosingTags = [];
        }
    }

    public static function fromHtml(string $html, ?Configuration $configuration = null): self
    {
        if ($configuration !== null && $configuration->getSelfClosingTags() !== null) {
            self::registerSelfClosingTags($configuration->getSelfClosingTags());
        }

        $dom = new self($configuration);
        $dom->load($html);

        return $dom;
    }

    public static function fromFile(string $filepath, ?Configuration $configuration = null)
    {
        if (! is_file($filepath) || ! is_readable($filepath)) {
            return false;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            return false;
        }

        return self::fromHtml($content, $configuration);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function __toString(): string
    {
        return $this->root ? $this->root->innerHtml() : '';
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'outerHtml':
                return $this->root ? $this->root->outerHtml() : '';
            case 'innerHtml':
                return $this->root ? $this->root->innerHtml() : '';
            case 'textContent':
                return $this->root ? $this->root->textContent() : '';
        }

        return null;
    }

    public function save(string $filepath = ''): string
    {
        $result = $this->root ? $this->root->innerHtml() : '';
        if ($filepath !== '') {
            file_put_contents($filepath, $result, LOCK_EX);
        }

        return $result;
    }

    /**
     * @param string $tag Tag name
     * @param string|null $content Inner HTML content
     * @param array<string, string|bool> $attributes Element attributes
     * @return Node
     */
    public function createElement(string $tag, ?string $content = null, array $attributes = []): Node
    {
        $node = new Node($this);
        $node->nodetype = self::TYPE_ELEMENT;
        $node->tag = $this->configuration->isLowercase() ? strtolower($tag) : $tag;
        $node->attributes = $attributes;

        foreach ($attributes as $value) {
            $node->info[self::INFO_SPACE][] = [' ', '', ''];
            $node->info[self::INFO_QUOTE][] = $value === true ? self::QUOTE_NO : self::QUOTE_DOUBLE;
        }

        if ($content !== null) {
            $node->info[self::INFO_INNER] = $content;
        }

        if (self::isSelfClosingTag($tag)) {
            $node->info[self::INFO_ENDSPACE] = '/';
            $node->info[self::INFO_END] = 0;
        }

        return $node;
    }

    public function createTextNode(string $text): Node
    {
        $node = new Node($this);
        $node->nodetype = self::TYPE_TEXT;
        $node->tag = 'text';
        $node->info[self::INFO_TEXT] = $text;

        return $node;
    }

    /**
     * @param string $comment
     * @return Node
     */
    public function createComment(string $comment): Node
    {
        $node = new Node($this);
        $node->nodetype = self::TYPE_COMMENT;
        $node->tag = 'comment';
        $node->info[self::INFO_TEXT] = '<!--' . $comment . '-->';

        return $node;
    }

    public function clear(): void
    {
        foreach ($this->nodes as $node) {
            $node->clear();
        }

        $this->root = null;
        $this->html = '';
        $this->noiseMap = [];
        $this->nodes = [];
    }

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    public function removeCallback(): void
    {
        $this->callback = null;
    }

    public function restoreNoise(string $text): string
    {
        if (strpos($text, '___noise___') === false) {
            return $text;
        }

        return preg_replace_callback(
            '/___noise___(.{5})/',
            function (array $matches) {
                $key = '___noise___' . $matches[1];

                return $this->noiseMap[$key] ?? '';
            },
            $text
        );
    }

    protected function prepare(
        string $str
    ): void {
        $this->clear();

        $this->html = trim($str);
        $this->size = strlen($this->html);
        $this->originalSize = $this->size;
        $this->position = 0;
        $this->cursor = 1;
        $this->noiseMap = [];
        $this->nodes = [];

        $this->root = new Node($this);
        $this->root->tag = 'root';
        $this->root->info[self::INFO_BEGIN] = -1;
        $this->root->nodetype = self::TYPE_ROOT;
        $this->currentParent = $this->root;

        if ($this->size > 0) {
            $this->currentChar = $this->html[0];
        }
    }

    protected function load(string $str): self
    {
        $this->prepare($str);

        $this->removeNoise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->removeNoise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");

        if ($this->configuration->shouldRemoveLineBreaks()) {
            $this->html = str_replace("\r", ' ', $this->html);
            $this->html = str_replace("\n", ' ', $this->html);
            $this->size = strlen($this->html);
        }

        $this->removeNoise("'<!\[CDATA\[(.*?)\]\]>'is", true);
        $this->removeNoise("'<!--(.*?)-->'is");
        $this->removeNoise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->removeNoise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
        $this->removeNoise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        $this->removeNoise("'(<\?)(.*?)(\?>)'s", true);

        $this->parse();
        $this->root->info[self::INFO_END] = $this->cursor;
        $this->detectCharset();

        return $this;
    }

    protected function parse(): bool
    {
        while (true) {
            $textContent = $this->copyUntilChar('<');
            if ($textContent === '') {
                if ($this->readTag()) {
                    continue;
                }

                return true;
            }

            $node = new Node($this);
            ++$this->cursor;
            $node->info[self::INFO_TEXT] = $textContent;
            $this->linkNodes($node, false);
        }
    }

    protected function detectCharset(): string
    {
        $detectedCharset = null;

        $metaElement = $this->root->findOne('meta[http-equiv=Content-Type]', true);
        if ($metaElement) {
            $contentValue = $metaElement->getAttribute('content');
            if ($contentValue && preg_match('/charset=(.+)/i', $contentValue, $matches)) {
                $detectedCharset = $matches[1];
            }
        }

        if (empty($detectedCharset)) {
            $metaCharset = $this->root->findOne('meta[charset]');
            if ($metaCharset) {
                $detectedCharset = $metaCharset->getAttribute('charset');
            }
        }

        if (empty($detectedCharset) && function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($this->html, ['UTF-8', 'CP1252', 'ISO-8859-1']);
            if ($encoding !== false) {
                $detectedCharset = $encoding;
            }
        }

        if (empty($detectedCharset)) {
            $detectedCharset = 'UTF-8';
        }

        $lowerCharset = strtolower($detectedCharset);
        if ($lowerCharset === 'iso-8859-1' || $lowerCharset === 'latin1' || $lowerCharset === 'latin-1') {
            $detectedCharset = 'CP1252';
        }

        return $this->charset = $detectedCharset;
    }

    protected function readTag(): bool
    {
        if ($this->currentChar !== '<') {
            $this->root->info[self::INFO_END] = $this->cursor;

            return false;
        }

        $tagStartPosition = $this->position;
        $this->advance();

        if ($this->currentChar === '/') {
            return $this->readEndTag();
        }

        return $this->readStartTag($tagStartPosition);
    }

    protected function readEndTag(): bool
    {
        $this->advance();
        $this->skip(self::WHITESPACE_CHARS);
        $tag = $this->copyUntilChar('>');

        if (($spacePos = strpos($tag, ' ')) !== false) {
            $tag = substr($tag, 0, $spacePos);
        }

        $parentTagLower = strtolower($this->currentParent->tag);
        $tagLower = strtolower($tag);

        if ($parentTagLower !== $tagLower) {
            $isBlockTag = in_array($tagLower, self::BLOCK_TAGS, true);

            if (isset($this->optionalClosingTags[$parentTagLower]) && $isBlockTag) {
                $this->currentParent->info[self::INFO_END] = 0;
                $originalParent = $this->currentParent;

                $this->traverseToMatchingParent($tagLower);

                if (strtolower($this->currentParent->tag) !== $tagLower) {
                    $this->currentParent = $originalParent;
                    if ($this->currentParent->parent) {
                        $this->currentParent = $this->currentParent->parent;
                    }
                    $this->currentParent->info[self::INFO_END] = $this->cursor;

                    return $this->createOrphanEndTagNode($tag);
                }
            } elseif ($this->currentParent->parent && $isBlockTag) {
                $this->currentParent->info[self::INFO_END] = 0;
                $originalParent = $this->currentParent;

                $this->traverseToMatchingParent($tagLower);

                if (strtolower($this->currentParent->tag) !== $tagLower) {
                    $this->currentParent = $originalParent;
                    $this->currentParent->info[self::INFO_END] = $this->cursor;

                    return $this->createOrphanEndTagNode($tag);
                }
            } elseif ($this->currentParent->parent && strtolower($this->currentParent->parent->tag) === $tagLower) {
                $this->currentParent->info[self::INFO_END] = 0;
                $this->currentParent = $this->currentParent->parent;
            } else {
                return $this->createOrphanEndTagNode($tag);
            }
        }

        $this->currentParent->info[self::INFO_END] = $this->cursor;

        if ($this->currentParent->parent) {
            $this->currentParent = $this->currentParent->parent;
        }

        $this->advance();

        return true;
    }

    protected function traverseToMatchingParent(string $tagLower): void
    {
        while ($this->currentParent->parent && strtolower($this->currentParent->tag) !== $tagLower) {
            $this->currentParent = $this->currentParent->parent;
        }
    }

    /**
     * @param int $tagStartPosition
     * @return bool
     */
    protected function readStartTag(int $tagStartPosition): bool
    {
        $node = new Node($this);
        $node->info[self::INFO_BEGIN] = $this->cursor;
        ++$this->cursor;
        $tag = $this->copyUntil(" />\r\n\t");
        $node->tagStart = $tagStartPosition;

        if (isset($tag[0]) && $tag[0] === '!') {
            $node->info[self::INFO_TEXT] = '<' . $tag . $this->copyUntilChar('>');

            if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') {
                $node->nodetype = self::TYPE_COMMENT;
                $node->tag = 'comment';
            } else {
                $node->nodetype = self::TYPE_UNKNOWN;
                $node->tag = 'unknown';
            }

            if ($this->currentChar === '>') {
                $node->info[self::INFO_TEXT] .= '>';
            }

            $this->linkNodes($node, true);
            $this->advance();

            return true;
        }

        if (strpos($tag, '<') !== false) {
            $tag = '<' . substr($tag, 0, -1);
            $node->info[self::INFO_TEXT] = $tag;
            $this->linkNodes($node, false);
            $this->currentChar = $this->html[--$this->position];

            return true;
        }

        if (! preg_match('/^\w[\w:-]*$/', $tag)) {
            $node->info[self::INFO_TEXT] = '<' . $tag . $this->copyUntil('<>');

            if ($this->currentChar === '<') {
                $this->linkNodes($node, false);

                return true;
            }

            if ($this->currentChar === '>') {
                $node->info[self::INFO_TEXT] .= '>';
            }
            $this->linkNodes($node, false);
            $this->advance();

            return true;
        }

        $node->nodetype = self::TYPE_ELEMENT;
        $tagLower = strtolower($tag);
        $node->tag = $this->configuration->isLowercase() ? $tagLower : $tag;

        if (isset($this->optionalClosingTags[$tagLower])) {
            $parentTagLower = strtolower($this->currentParent->tag);
            while (in_array($parentTagLower, $this->optionalClosingTags[$tagLower], true)) {
                $this->currentParent->info[self::INFO_END] = 0;
                $this->currentParent = $this->currentParent->parent;
                $parentTagLower = strtolower($this->currentParent->tag);
            }
            $node->parent = $this->currentParent;
        }

        $guard = 0;
        $spacing = [$this->copySkip(self::WHITESPACE_CHARS), '', ''];

        do {
            $attrName = $this->copyUntil(' =/>');

            if ($attrName === '' && $this->currentChar !== null && $spacing[0] === '') {
                break;
            }

            if ($guard === $this->position) {
                $this->advance();

                continue;
            }

            $guard = $this->position;

            if ($this->position >= $this->size - 1 && $this->currentChar !== '>') {
                $node->nodetype = self::TYPE_TEXT;
                $node->info[self::INFO_END] = 0;
                $node->info[self::INFO_TEXT] = '<' . $tag . $spacing[0] . $attrName;
                $node->tag = 'text';
                $this->linkNodes($node, false);

                return true;
            }

            if ($this->html[$this->position - 1] === '<') {
                $node->nodetype = self::TYPE_TEXT;
                $node->tag = 'text';
                $node->attributes = [];
                $node->info[self::INFO_END] = 0;
                $node->info[self::INFO_TEXT] = substr($this->html, $tagStartPosition, $this->position - $tagStartPosition - 1);
                $this->position -= 2;
                $this->advance();
                $this->linkNodes($node, false);

                return true;
            }

            if ($attrName !== '/' && $attrName !== '') {
                $spacing[1] = $this->copySkip(self::WHITESPACE_CHARS);
                $attrName = $this->restoreNoise($attrName);

                if ($this->configuration->isLowercase()) {
                    $attrName = strtolower($attrName);
                }

                if ($this->currentChar === '=') {
                    $this->advance();
                    $this->parseAttribute($node, $attrName, $spacing);
                } else {
                    $node->info[self::INFO_QUOTE][] = self::QUOTE_NO;
                    $node->attributes[$attrName] = true;
                    if ($this->currentChar !== '>') {
                        $this->currentChar = $this->html[--$this->position];
                    }
                }

                $node->info[self::INFO_SPACE][] = $spacing;
                $spacing = [$this->copySkip(self::WHITESPACE_CHARS), '', ''];
            } else {
                break;
            }
        } while ($this->currentChar !== '>' && $this->currentChar !== '/');

        $this->linkNodes($node, true);
        $node->info[self::INFO_ENDSPACE] = $spacing[0];

        if ($this->copyUntilChar('>') === '/') {
            $node->info[self::INFO_ENDSPACE] .= '/';
            $node->info[self::INFO_END] = 0;
        } else {
            if (! in_array(strtolower($node->tag), self::$selfClosingTags, true)) {
                $this->currentParent = $node;
            }
        }

        $this->advance();

        if ($node->tag === 'br') {
            $node->info[self::INFO_INNER] = $this->configuration->getDefaultBrText();
        }

        return true;
    }

    protected function parseAttribute(Node $node, string $name, array &$spacing): void
    {
        $isDuplicate = isset($node->attributes[$name]);

        if (! $isDuplicate) {
            $spacing[2] = $this->copySkip(self::WHITESPACE_CHARS);
        }

        switch ($this->currentChar) {
            case '"':
                $quoteType = self::QUOTE_DOUBLE;
                $this->advance();
                $value = $this->copyUntilChar('"');
                $this->advance();

                break;
            case "'":
                $quoteType = self::QUOTE_SINGLE;
                $this->advance();
                $value = $this->copyUntilChar("'");
                $this->advance();

                break;
            default:
                $quoteType = self::QUOTE_NO;
                $value = $this->copyUntil(' >');
        }

        $value = $this->restoreNoise($value);

        if ($name === 'class') {
            $value = trim($value);
        }

        if (! $isDuplicate) {
            $node->info[self::INFO_QUOTE][] = $quoteType;
            $node->attributes[$name] = $value;
        }
    }

    protected function linkNodes(Node $node, bool $isChild): void
    {
        $node->parent = $this->currentParent;
        $this->currentParent->nodes[] = $node;
        if ($isChild) {
            $this->currentParent->children[] = $node;
        }
    }

    protected function createOrphanEndTagNode(string $tag): bool
    {
        $node = new Node($this);
        ++$this->cursor;
        $node->info[self::INFO_TEXT] = '</' . $tag . '>';
        $this->linkNodes($node, false);
        $this->advance();

        return true;
    }

    protected function skip(string $chars): void
    {
        $this->position += strspn($this->html, $chars, $this->position);
        $this->currentChar = ($this->position < $this->size) ? $this->html[$this->position] : null;
    }

    protected function advance(): void
    {
        $this->currentChar = (++$this->position < $this->size) ? $this->html[$this->position] : null;
    }

    protected function copySkip(string $chars): string
    {
        $startPos = $this->position;
        $length = strspn($this->html, $chars, $startPos);
        $this->position += $length;
        $this->currentChar = ($this->position < $this->size) ? $this->html[$this->position] : null;

        return ! $length ? '' : substr($this->html, $startPos, $length);
    }

    protected function copyUntil(string $chars): string
    {
        $startPos = $this->position;
        $length = strcspn($this->html, $chars, $startPos);
        $this->position += $length;
        $this->currentChar = ($this->position < $this->size) ? $this->html[$this->position] : null;

        return $length > 0 ? substr($this->html, $startPos, $length) : '';
    }

    protected function copyUntilChar(string $char): string
    {
        if ($this->currentChar === null) {
            return '';
        }

        $foundPos = strpos($this->html, $char, $this->position);
        if ($foundPos === false) {
            $result = substr($this->html, $this->position, $this->size - $this->position);
            $this->currentChar = null;
            $this->position = $this->size;

            return $result;
        }

        if ($foundPos === $this->position) {
            return '';
        }

        $startPos = $this->position;
        $this->currentChar = $this->html[$foundPos];
        $this->position = $foundPos;

        return substr($this->html, $startPos, $foundPos - $startPos);
    }

    protected function removeNoise(string $pattern, bool $removeTag = false): void
    {
        $count = preg_match_all($pattern, $this->html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        for ($i = $count - 1; $i > -1; --$i) {
            $key = '___noise___' . sprintf('% 5d', count($this->noiseMap) + 1000);
            $idx = $removeTag ? 0 : 1;
            $this->noiseMap[$key] = $matches[$i][$idx][0];
            $this->html = substr_replace($this->html, $key, (int) $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }

        $this->size = strlen($this->html);
        if ($this->size > 0) {
            $this->currentChar = $this->html[0];
        }
    }
}
