<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

use dobron\DomForge\Node;

trait NodeTraversalTrait
{
    /**
     * @return Node|null
     */
    public function parent(Node $parent = null)
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

    /**
     * @return Node|null
     */
    public function firstChild()
    {
        return $this->children[0] ?? null;
    }

    /**
     * @return Node|null
     */
    public function lastChild()
    {
        $count = count($this->children);

        return $count > 0 ? $this->children[$count - 1] : null;
    }

    /**
     * @return Node|null
     */
    public function nextSibling()
    {
        if (! $this->parent) {
            return null;
        }

        $index = array_search($this, $this->parent->children, true);
        if ($index === false) {
            return null;
        }

        return $this->parent->children[$index + 1] ?? null;
    }

    /**
     * @return Node|null
     */
    public function previousSibling()
    {
        if (! $this->parent) {
            return null;
        }

        $index = array_search($this, $this->parent->children, true);
        if ($index === false || $index === 0) {
            return null;
        }

        return $this->parent->children[$index - 1] ?? null;
    }

    public function appendChild(Node $child): Node
    {
        $child->parent = $this;
        $this->nodes[] = $child;
        $this->children[] = $child;

        return $child;
    }

    /**
     * @return Node|null
     */
    public function removeChild(Node $child)
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
