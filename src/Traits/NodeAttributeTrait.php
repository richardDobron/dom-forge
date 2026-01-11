<?php

declare(strict_types=1);

namespace dobron\DomForge\Traits;

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
        $value = $this->__get($name);
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
        $this->__unset($name);
    }
}
