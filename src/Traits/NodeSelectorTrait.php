<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

use dobron\DomForge\DomForge;
use dobron\DomForge\Node;

trait NodeSelectorTrait
{
    /**
     * @return Node[]|Node|null
     */
    public function find(string $selector, ?int $idx = null, bool $lowercase = false)
    {
        $selectors = $this->parseSelector($selector);
        if (empty($selectors)) {
            return $idx === null ? [] : null;
        }

        $foundKeys = [];

        foreach ($selectors as $selectorGroup) {
            if (empty($selectorGroup) || ! isset($this->info[DomForge::INFO_BEGIN])) {
                continue;
            }

            $head = [$this->info[DomForge::INFO_BEGIN] => 1];
            $combinator = ' ';

            foreach ($selectorGroup as $selectorPart) {
                $matches = [];

                foreach ($head as $nodeIndex => $value) {
                    $node = ($nodeIndex === -1) ? $this->dom->root : $this->dom->nodes[$nodeIndex];
                    $node->seek($selectorPart, $matches, $combinator, $lowercase);
                }

                $head = $matches;
                $combinator = $selectorPart[4] ?? ' ';
            }

            foreach ($head as $nodeIndex => $value) {
                $foundKeys[$nodeIndex] = 1;
            }
        }

        ksort($foundKeys);

        $found = [];
        foreach ($foundKeys as $nodeIndex => $value) {
            $found[] = $this->dom->nodes[$nodeIndex];
        }

        if ($idx === null) {
            return $found;
        }

        if ($idx < 0) {
            $idx = count($found) + $idx;
        }

        return $found[$idx] ?? null;
    }

    public function findOne(string $selector, bool $lowercase = false): ?Node
    {
        return $this->find($selector, 0, $lowercase);
    }

    protected function seek(array $selector, array &$matches, string $combinator, bool $lowercase): void
    {
        [$tag, $id, $classes, $attributeSelectors] = $selector;
        $candidateNodes = $this->getCandidateNodes($combinator);

        foreach ($candidateNodes as $candidateNode) {
            if ($this->matchesSelector($candidateNode, $tag, $id, $classes, $attributeSelectors, $lowercase)) {
                $matches[$candidateNode->info[DomForge::INFO_BEGIN]] = 1;
            }
        }
    }

    /**
     * @return Node[]
     */
    protected function getCandidateNodes(string $combinator): array
    {
        switch ($combinator) {
            case ' ': // Descendant selector
                $endIndex = $this->info[DomForge::INFO_END] ?? -1;
                if ($endIndex <= 0) {
                    $endIndex = count($this->dom->nodes);
                }
                $beginIndex = $this->info[DomForge::INFO_BEGIN] ?? 0;

                return array_slice($this->dom->nodes, $beginIndex + 1, $endIndex - $beginIndex - 1);

            case '>': // Direct child selector
                return $this->children;

            case '+': // Adjacent sibling selector
                if ($this->parent) {
                    $index = array_search($this, $this->parent->children, true);
                    if ($index !== false && isset($this->parent->children[$index + 1])) {
                        return [$this->parent->children[$index + 1]];
                    }
                }

                return [];

            case '~': // General sibling selector
                if ($this->parent) {
                    $index = array_search($this, $this->parent->children, true);
                    if ($index !== false) {
                        return array_slice($this->parent->children, $index + 1);
                    }
                }

                return [];

            default:
                return [];
        }
    }

    protected function matchesSelector(
        Node $node,
        string $tag,
        string $id,
        array $classes,
        array $attributeSelectors,
        bool $lowercase
    ): bool {
        if ($node->nodetype !== DomForge::TYPE_ELEMENT) {
            return false;
        }

        $nodeTag = $lowercase ? strtolower($node->tag) : $node->tag;
        $selectorTag = $lowercase ? strtolower($tag) : $tag;

        if ($tag !== '' && $tag !== '*' && $nodeTag !== $selectorTag) {
            return false;
        }

        if ($id !== '' && ($node->attributes['id'] ?? '') !== $id) {
            return false;
        }

        if (! empty($classes)) {
            $nodeClasses = preg_split('/\s+/', $node->attributes['class'] ?? '');
            foreach ($classes as $className) {
                if (! in_array($className, $nodeClasses, true)) {
                    return false;
                }
            }
        }

        if (! empty($attributeSelectors)) {
            foreach ($attributeSelectors as $attrSelector) {
                [$attrName, $attrOperator, $attrValue, $inverted, $caseSensitivity] = $attrSelector;
                $nodeAttrValue = $node->attributes[$attrName] ?? null;

                $singleMatch = $this->matchAttribute($attrOperator, $attrValue, $nodeAttrValue, $caseSensitivity);
                if ($inverted ? $singleMatch : ! $singleMatch) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function matchAttribute(string $operator, string $pattern, $value, string $caseSensitivity): bool
    {
        if ($value === null) {
            return $operator === '';
        }

        if ($caseSensitivity === 'i') {
            $pattern = strtolower($pattern);
            $value = strtolower($value);
        }

        switch ($operator) {
            case '':
                return true;
            case '=':
                return $value === $pattern;
            case '!=':
                return $value !== $pattern;
            case '^=':
                return strpos($value, $pattern) === 0;
            case '$=':
                $patternLen = strlen($pattern);

                return $patternLen === 0 || substr($value, -$patternLen) === $pattern;
            case '*=':
                return strpos($value, $pattern) !== false;
            case '|=':
                return $value === $pattern || strpos($value, $pattern . '-') === 0;
            case '~=':
                return in_array($pattern, preg_split('/\s+/', $value), true);
            default:
                return false;
        }
    }

    protected function parseSelector(string $selectorString): array
    {
        $pattern = "/([\w:\*-]*)(?:\#([\w-]+))?(?:|\.([\w\.-]+))?((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?([\/, >+~]+)/is";

        preg_match_all($pattern, trim($selectorString) . ' ', $rawMatches, PREG_SET_ORDER);

        $selectors = [];
        $currentGroup = [];

        foreach ($rawMatches as $match) {
            $match[0] = trim($match[0]);
            if ($match[0] === '' || $match[0] === '/' || $match[0] === '//') {
                continue;
            }

            if ($this->dom && $this->dom->getConfiguration()->isLowercase()) {
                $match[1] = strtolower($match[1]);
            }

            $match[3] = $match[3] !== '' ? explode('.', $match[3]) : [];

            if (isset($match[4]) && $match[4] !== '') {
                preg_match_all("/\[@?(!?[\w:-]+)(?:([!*^$|~]?=)[\"']?(.*?)[\"']?)?(?:\s+?([iIsS])?)?\]/is", trim($match[4]), $attrMatches, PREG_SET_ORDER);
                $match[4] = [];
                foreach ($attrMatches as $attrMatch) {
                    if (trim($attrMatch[0]) === '') {
                        continue;
                    }
                    $inverted = isset($attrMatch[1][0]) && $attrMatch[1][0] === '!';
                    $match[4][] = [
                        $inverted ? substr($attrMatch[1], 1) : $attrMatch[1],
                        $attrMatch[2] ?? '',
                        $attrMatch[3] ?? '',
                        $inverted,
                        isset($attrMatch[4]) ? strtolower($attrMatch[4]) : '',
                    ];
                }
            } else {
                $match[4] = [];
            }

            if (isset($match[5]) && $match[5] !== '' && trim($match[5]) === '') {
                $match[5] = ' ';
            } else {
                $match[5] = isset($match[5]) ? trim($match[5]) : '';
            }

            $isList = ($match[5] === ',');
            if ($isList) {
                $match[5] = '';
            }

            array_shift($match);
            $currentGroup[] = $match;

            if ($isList) {
                $selectors[] = $currentGroup;
                $currentGroup = [];
            }
        }

        if (! empty($currentGroup)) {
            $selectors[] = $currentGroup;
        }

        return $selectors;
    }
}
