<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

use dobron\DomForge\DomForge;

trait NodeAttributeTrait
{
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return string|bool|null
     */
    public function getAttribute(string $name)
    {
        $value = $this->readProperty($name);
        if ($value === true) {
            return true;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(string $name, $value): void
    {
        $this->__set($name, $value);
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function removeAttribute(string $name): void
    {
        if (array_key_exists($name, $this->attributes)) {
            $index = array_search($name, array_keys($this->attributes), true);

            unset($this->attributes[$name]);

            if ($index !== false && isset($this->info[DomForge::INFO_SPACE][$index])) {
                array_splice($this->info[DomForge::INFO_SPACE], $index, 1);
            }

            if ($index !== false && isset($this->info[DomForge::INFO_QUOTE][$index])) {
                array_splice($this->info[DomForge::INFO_QUOTE], $index, 1);
            }
        }
    }
}
