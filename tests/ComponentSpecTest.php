<?php

declare(strict_types=1);

use DrupalSdcSkill\ComponentSpec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../scripts/ComponentSpec.php';

/**
 * Checks rejected component declarations and their diagnostics.
 */
final class ComponentSpecTest extends TestCase {

  private const ID = 'my_theme:teaser-card';

  /**
   * Checks that the named constructor rejects invalid declarations.
   *
   * The static constructor is the API under test.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  #[DataProvider('invalidSpecs')]
  public function testInvalidSpec(string $componentId, string $spec, string $message): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    ComponentSpec::fromJson($componentId, $spec);
  }

  /**
   * Supplies invalid IDs and declarations with expected diagnostics.
   */
  public static function invalidSpecs(): iterable {
    yield 'unsafe ID' => ['my_theme:../x', '{}', 'provider:component ID'];
    yield 'trailing ID separator' => ['my_theme:card-', '{}', 'provider:component ID'];
    yield 'malformed JSON' => [self::ID, '{', 'Invalid JSON declaration'];
    yield 'wrong root' => [self::ID, '[]', 'must be an object'];
    yield 'unknown field' => [self::ID, '{"types":[]}', "Unknown declaration field 'types'"];
    yield 'blank display name' => [self::ID, '{"name":" "}', 'single-line string'];
    yield 'control character' => [self::ID, '{"description":"line\nline"}', 'single-line string'];
    yield 'traversal' => [self::ID, '{"subdirectory":"foo/../bar"}', 'relative folder names'];
    yield 'props as list' => [self::ID, '{"props":[]}', 'props must be an object'];
    yield 'invalid prop type' => [self::ID, '{"props":{"discount":"float"}}', 'Unsupported JSON Schema type'];
    yield 'missing required prop' => [self::ID, '{"required":["title"]}', "Required prop 'title'"];
    yield 'duplicate contract names' => [
      self::ID,
      '{"props":{"body":"string"},"slots":["body"]}',
      'both a prop and a slot',
    ];
    yield 'unknown asset' => [self::ID, '{"assets":["svg"]}', 'only css, scss, or js'];
    yield 'null list' => [self::ID, '{"slots":null}', 'must be a list of strings'];
    yield 'wrong list element' => [self::ID, '{"slots":[false]}', 'must be a list of strings'];
    yield 'duplicate list values' => [self::ID, '{"assets":["js","js"]}', 'must contain unique values'];
    yield 'unsafe Twig identifier' => [self::ID, '{"slots":["invalid-slot"]}', 'Invalid prop or slot name'];
    yield 'reserved name' => [self::ID, '{"slots":["attributes"]}', 'Invalid prop or slot name'];
  }

}
