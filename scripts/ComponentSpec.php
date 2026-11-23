<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

/**
 * Holds the validated declaration for a component starter.
 */
final class ComponentSpec {
  private const FIELDS = ['name', 'description', 'props', 'required', 'slots', 'subdirectory', 'assets'];
  private const TYPES = ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'];
  private const ASSETS = ['css', 'scss', 'js'];
  private const RESERVED_NAMES = [
    'and', 'as', 'attributes', 'componentmetadata', 'false',
    'in', 'is', 'loop', 'matches', 'none', 'not', 'null', 'or', 'slots', 'true',
    'variant', 'xor',
  ];

  private function __construct(
    public readonly string $provider,
    public readonly string $component,
    public readonly string $name,
    public readonly ?string $description,
    public readonly array $props,
    public readonly array $required,
    public readonly array $slots,
    public readonly array $subdirectories,
    public readonly array $assets,
  ) {
  }

  /**
   * Builds a component specification from its ID and JSON declaration.
   */
  public static function fromJson(string $id, string $json): self {
    if (!preg_match('/\A([a-z](?:[a-z0-9_]*[a-z0-9])?):([a-z](?:[a-z0-9_-]*[a-z0-9])?)\z/', $id, $matches)) {
      throw new \InvalidArgumentException('Expected a lowercase provider:component ID, for example my_theme:teaser-card.');
    }
    [, $provider, $component] = $matches;

    try {
      $declaration = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('Invalid JSON declaration: ' . $exception->getMessage(), 0, $exception);
    }

    if (!$declaration instanceof \stdClass) {
      throw new \InvalidArgumentException('The JSON declaration must be an object.');
    }

    foreach (array_diff(array_keys(get_object_vars($declaration)), self::FIELDS) as $field) {
      throw new \InvalidArgumentException("Unknown declaration field '$field'.");
    }

    $name = self::text($declaration, 'name') ?? ucwords(str_replace(['-', '_'], ' ', $component));
    $description = self::text($declaration, 'description');
    $subdirectory = self::text($declaration, 'subdirectory');
    if ($subdirectory !== NULL && !preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', $subdirectory)) {
      throw new \InvalidArgumentException('subdirectory must contain relative folder names separated by /.');
    }

    $props = property_exists($declaration, 'props') ? $declaration->props : new \stdClass();
    if (!$props instanceof \stdClass) {
      throw new \InvalidArgumentException('props must be an object mapping names to JSON Schema types.');
    }

    $props = get_object_vars($props);
    foreach ($props as $prop => $type) {
      self::validateInputName((string) $prop);
      if (!in_array($type, self::TYPES, TRUE)) {
        throw new \InvalidArgumentException("Unsupported JSON Schema type for prop '$prop'.");
      }
    }

    $required = self::stringList($declaration, 'required');
    foreach ($required as $prop) {
      if (!array_key_exists($prop, $props)) {
        throw new \InvalidArgumentException("Required prop '$prop' is missing from props.");
      }
    }

    $slots = self::stringList($declaration, 'slots');
    foreach ($slots as $slot) {
      self::validateInputName($slot);
      if (array_key_exists($slot, $props)) {
        throw new \InvalidArgumentException("'$slot' cannot be both a prop and a slot.");
      }
    }

    $assets = self::stringList($declaration, 'assets');
    if (array_diff($assets, self::ASSETS) !== []) {
      throw new \InvalidArgumentException('assets must contain only css, scss, or js.');
    }

    return new self(
          $provider,
          $component,
          $name,
          $description,
          $props,
          $required,
          $slots,
          $subdirectory === NULL ? [] : explode('/', $subdirectory),
          $assets,
      );
  }

  /**
   * Reads an optional single-line text field.
   */
  private static function text(\stdClass $declaration, string $field): ?string {
    if (!property_exists($declaration, $field)) {
      return NULL;
    }

    $value = $declaration->$field;
    if (!is_string($value) || trim($value) === '' || preg_match('/[\x00-\x1f\x{007f}-\x{009f}\x{2028}\x{2029}]/u', $value)) {
      throw new \InvalidArgumentException("$field must be a nonempty, single-line string.");
    }

    return $value;
  }

  /**
   * Reads an optional list of unique strings.
   */
  private static function stringList(\stdClass $declaration, string $field): array {
    $value = property_exists($declaration, $field) ? $declaration->$field : [];
    if (!is_array($value)) {
      throw new \InvalidArgumentException("$field must be a list of strings.");
    }

    foreach ($value as $entry) {
      if (!is_string($entry)) {
        throw new \InvalidArgumentException("$field must be a list of strings.");
      }
    }

    if (count($value) !== count(array_unique($value))) {
      throw new \InvalidArgumentException("$field must contain unique values.");
    }
    return $value;
  }

  /**
   * Checks that a prop or slot name is a usable Twig identifier.
   */
  private static function validateInputName(string $name): void {
    if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $name) || in_array($name, self::RESERVED_NAMES, TRUE)) {
      throw new \InvalidArgumentException("Invalid prop or slot name '$name'. Use a lowercase Twig identifier that is not reserved.");
    }
  }

}
