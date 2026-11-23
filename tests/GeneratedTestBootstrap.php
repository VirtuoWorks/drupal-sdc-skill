<?php

declare(strict_types=1);

// Stand-ins for the two Drupal classes used by generated tests. The generated
// tests run with the actual PHPUnit, Twig, and Symfony YAML implementations.
namespace Drupal\Tests {
    abstract class UnitTestCase extends \PHPUnit\Framework\TestCase
    {
    }
}

namespace Drupal\Core\Template {
    final class Attribute
    {
        public function __construct(private array $attributes)
        {
        }

        public function addClass(string $class): self
        {
            $this->attributes['class'] = $class;
            return $this;
        }

        public function __toString(): string
        {
            $markup = '';
            foreach ($this->attributes as $key => $value) {
                $markup .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
            }
            return $markup;
        }
    }
}
