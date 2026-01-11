<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

use dobron\DomForge\Node;

trait DomFinderTrait
{
    /**
     * @return Node[]|Node|null
     */
    public function find(string $selector, ?int $idx = null, bool $lowercase = false)
    {
        return $this->root ? $this->root->find($selector, $idx, $lowercase) : [];
    }

    public function findOne(string $selector, bool $lowercase = false): ?Node
    {
        return $this->root ? $this->root->findOne($selector, $lowercase) : null;
    }

    public function getElementById(string $id): ?Node
    {
        return $this->findOne("#$id");
    }

    /**
     * @return Node[]|Node|null
     */
    public function getElementsById(string $id, ?int $idx = null)
    {
        return $this->find("#$id", $idx);
    }

    public function getElementByTagName(string $name): ?Node
    {
        return $this->findOne($name);
    }

    /**
     * @return Node[]|Node|null
     */
    public function getElementsByTagName(string $name, ?int $idx = null)
    {
        return $this->find($name, $idx);
    }
}
