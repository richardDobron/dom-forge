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
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $name, $value)
    {
        $this->__set($name, $value);
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @param string $name
     * @return void
     */
    public function removeAttribute(string $name)
    {
        $this->__unset($name);
    }
}
