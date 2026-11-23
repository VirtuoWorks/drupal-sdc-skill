<?php

declare(strict_types=1);

namespace Drupal\Core\Template;

/**
 * Provides the attribute operations used by the generated Twig starter.
 */
final class Attribute {

  public function __construct(private array $attributes) {
  }

  /**
   * Adds the component class to the attributes passed by the test.
   */
  public function addClass(string $class): self {
    $this->attributes['class'] = $class;
    return $this;
  }

  /**
   * Renders HTML attributes with escaped values.
   */
  public function __toString(): string {
    $markup = '';
    foreach ($this->attributes as $key => $value) {
      $markup .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    }
    return $markup;
  }

}
