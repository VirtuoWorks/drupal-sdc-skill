<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

/**
 * Renders a standalone PHPUnit test for a starter component.
 */
final class ComponentUnitTest {
  public const DIRECTORY = 'tests/src/Unit';

  /**
   * The generated test class name without its namespace.
   */
  private readonly string $className;

  public function __construct(private readonly ComponentSpec $spec, private readonly string $componentDirectory) {
    $this->className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $spec->component))) . 'SdcTest';
  }

  /**
   * Returns the filename for the generated PHPUnit test.
   */
  public function filename(): string {
    return $this->className . '.php';
  }

  /**
   * Renders the PHPUnit test source for the declared component.
   */
  public function render(): string {
    $template = <<<'TEST'
<?php

declare(strict_types=1);

namespace Drupal\Tests\@PROVIDER@\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * Tests the @COMPONENT@ component contract and Twig starter.
 *
 * @group @PROVIDER@
 * @coversNothing
 */
#[CoversNothing]
#[Group('@PROVIDER@')]
final class @CLASS@ extends UnitTestCase {

  private const DIRECTORY = '@DIRECTORY@';
  private const TEMPLATE = '@COMPONENT@.twig';
  private const PROPS = @PROPS@;
  private const REQUIRED = @REQUIRED@;
  private const SLOTS = @SLOTS@;

  public function testMetadataContract(): void {
    $metadata = Yaml::parseFile(dirname(__DIR__, 3) . '/' . self::DIRECTORY . '/@COMPONENT@.component.yml');

    self::assertSame('object', $metadata['props']['type'] ?? NULL);
    self::assertIsArray($metadata['props']['properties'] ?? NULL);
    foreach (self::PROPS as $prop => $type) {
      self::assertSame($type, $metadata['props']['properties'][$prop]['type'] ?? NULL);
    }
    foreach (self::REQUIRED as $prop) {
      self::assertContains($prop, $metadata['props']['required'] ?? []);
    }
    foreach (self::SLOTS as $slot) {
      self::assertIsArray($metadata['slots'][$slot] ?? NULL);
    }
  }

  public function testTwigStarter(): void {
    $loader = new FilesystemLoader(dirname(__DIR__, 3) . '/' . self::DIRECTORY);
    $twig = new Environment($loader, ['strict_variables' => TRUE]);
    $twig->getRuntime(EscaperRuntime::class)->addSafeClass(Attribute::class, ['html']);

    // Supply representative props here when the template starts using them.
    $context = ['attributes' => new Attribute(['id' => 'caller'])];
    $html = $twig->render(self::TEMPLATE, $context);
    self::assertStringContainsString('id="caller"', $html);
    self::assertStringContainsString('class="@COMPONENT@"', $html);

    foreach (self::SLOTS as $slot) {
      $html = $twig->render(self::TEMPLATE, $context + [$slot => "<b>$slot</b>"]);
      self::assertStringContainsString("&lt;b&gt;$slot&lt;/b&gt;", $html);
      self::assertStringNotContainsString("<b>$slot</b>", $html);
    }

    if (self::SLOTS !== []) {
      $slot = self::SLOTS[0];
      $caller = $twig->createTemplate("{% embed '" . self::TEMPLATE . "' %}{% block $slot %}<b>Override</b>{% endblock %}{% endembed %}");
      self::assertStringContainsString('<b>Override</b>', $caller->render($context));
    }
  }

}

TEST;

    return strtr($template, [
      '@PROVIDER@' => $this->spec->provider,
      '@COMPONENT@' => $this->spec->component,
      '@CLASS@' => $this->className,
      '@DIRECTORY@' => $this->componentDirectory,
      '@PROPS@' => self::exportArray($this->spec->props),
      '@REQUIRED@' => self::exportArray($this->spec->required),
      '@SLOTS@' => self::exportArray($this->spec->slots),
    ]);
  }

  /**
   * Serializes prop or slot declarations as a PHP array literal.
   *
   * @param array<array-key, string> $values
   *   The map or list to include in the test source.
   */
  private static function exportArray(array $values): string {
    $entries = [];
    foreach ($values as $key => $value) {
      $entries[] = (is_string($key) ? var_export($key, TRUE) . ' => ' : '') . var_export($value, TRUE);
    }
    return '[' . implode(', ', $entries) . ']';
  }

}
