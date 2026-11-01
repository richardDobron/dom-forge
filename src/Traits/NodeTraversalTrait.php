<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

use dobron\DomForge\Node;

trait NodeTraversalTrait
{
    /** @var int|null Cached position in the parent's children, verified on every use */
    protected $childIndex = null;

    public function parent(?Node $parent = null): ?Node
    {
        if ($parent !== null) {
            $this->parent = $parent;
            $this->parent->nodes[] = $this;
            $this->parent->children[] = $this;
        }

        return $this->parent;
    }

    /**
     * @return Node[]|Node|null
     */
    public function children(int $idx = -1)
    {
        if ($idx === -1) {
            return $this->children;
        }

        return $this->children[$idx] ?? null;
    }

    public function firstChild(): ?Node
    {
        return $this->children[0] ?? null;
    }

    public function lastChild(): ?Node
    {
        $count = count($this->children);

        return $count > 0 ? $this->children[$count - 1] : null;
    }

    public function nextSibling(): ?Node
    {
        $index = $this->indexInParent();
        if ($index === null) {
            return null;
        }

        return $this->parent->children[$index + 1] ?? null;
    }

    public function previousSibling(): ?Node
    {
        $index = $this->indexInParent();
        if ($index === null || $index === 0) {
            return null;
        }

        return $this->parent->children[$index - 1] ?? null;
    }

    protected function indexInParent(): ?int
    {
        if (! $this->parent) {
            return null;
        }

        $children = $this->parent->children;
        if ($this->childIndex !== null && ($children[$this->childIndex] ?? null) === $this) {
            return $this->childIndex;
        }

        $found = null;
        foreach ($children as $index => $child) {
            $child->childIndex = $index;
            if ($found === null && $child === $this) {
                $found = $index;
            }
        }

        return $found;
    }

    public function appendChild(Node $child): Node
    {
        $child->parent = $this;
        $this->nodes[] = $child;
        $this->children[] = $child;

        return $child;
    }

    public function removeChild(Node $child): ?Node
    {
        $nodeIndex = array_search($child, $this->nodes, true);
        if ($nodeIndex !== false) {
            array_splice($this->nodes, $nodeIndex, 1);
        }

        $childIndex = array_search($child, $this->children, true);
        if ($childIndex !== false) {
            array_splice($this->children, $childIndex, 1);
            $child->parent = null;

            return $child;
        }

        return null;
    }

    public function insertBefore(Node $newNode, Node $referenceNode): Node
    {
        $childIndex = array_search($referenceNode, $this->children, true);
        if ($childIndex === false) {
            return $this->appendChild($newNode);
        }

        $newNode->parent = $this;
        array_splice($this->children, $childIndex, 0, [$newNode]);
        array_splice($this->nodes, $childIndex, 0, [$newNode]);

        return $newNode;
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }
}
