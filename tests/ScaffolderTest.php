<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Markup;

require_once __DIR__ . '/fixtures/ProviderTestCase.php';

/**
 * Checks the generated component metadata, assets, and Twig.
 */
final class ScaffolderTest extends ProviderTestCase {

  /**
   * Checks the files and metadata for a minimal starter.
   */
  public function testMinimalStarter(): void {
    [$status, $output, $error] = $this->runCommand([$this->provider, self::ID]);
    self::assertSame(0, $status, $error);
    $files = json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR)['files'];
    self::assertSame(
      ['components/teaser-card/teaser-card.twig', 'components/teaser-card/teaser-card.component.yml'],
      $files,
    );
    $metadata = (new Parser())->parse(file_get_contents($this->provider . '/' . $files[1]), Yaml::PARSE_OBJECT_FOR_MAP);
    self::assertSame('Teaser Card', $metadata->name);
    self::assertSame('object', $metadata->props->type);
    self::assertEquals(new stdClass(), $metadata->props->properties);
    self::assertDirectoryDoesNotExist($this->provider . '/tests');
  }

  /**
   * Checks rendered slots, escaping, and caller attributes.
   */
  public function testTypedStarterRendersSlotsAndPreservesAttributes(): void {
    $label = "Editor's card: #1 🎉";
    [$status, $output, $error] = $this->runCommand(
      [$this->provider, self::ID, '-'],
      json_encode([
        'name' => $label,
        'description' => '*Quoted* [description]',
        'props' => ['title' => 'string', 'optional' => 'null', 'item_count' => 'integer'],
        'required' => ['title'],
        'slots' => ['body', 'on'],
        'subdirectory' => 'layouts/cards',
        'assets' => ['scss', 'css', 'js'],
      ], JSON_THROW_ON_ERROR),
    );
    self::assertSame(0, $status, $error);
    [$twig, $scss, $css, $javascript, $metadata] = json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR)['files'];

    $base = $this->provider . '/';
    self::assertSame('components/layouts/cards/teaser-card/teaser-card.twig', $twig);
    $data = (new Parser())->parse(file_get_contents($base . $metadata));
    self::assertSame($label, $data['name']);
    self::assertSame('*Quoted* [description]', $data['description']);
    self::assertSame(['title'], $data['props']['required']);
    self::assertSame(['type' => 'null', 'title' => 'Optional'], $data['props']['properties']['optional']);
    self::assertSame(['type' => 'integer', 'title' => 'Item Count'], $data['props']['properties']['item_count']);
    self::assertSame(['title' => 'Body'], $data['slots']['body']);
    self::assertSame(['title' => 'On'], $data['slots']['on']);
    self::assertSame(['core/drupal', 'core/once'], $data['libraryOverrides']['dependencies']);
    self::assertStringContainsString('.teaser-card {', file_get_contents($base . $scss));
    self::assertSame(file_get_contents($base . $scss), file_get_contents($base . $css));
    self::assertStringContainsString("once('my_theme:teaser-card', '.teaser-card', context)", file_get_contents($base . $javascript));

    $environment = new Environment(new ArrayLoader([
      'component.twig' => file_get_contents($base . $twig),
      'caller.twig' => "{% embed 'component.twig' %}{% block body %}<strong>Override</strong>{% endblock %}{% endembed %}",
    ]), ['strict_variables' => TRUE]);
    $attributes = new class {

      /**
       * Returns safe markup with the component class appended.
       */
      public function addClass(string $class): Markup {
        return new Markup(' id="existing" class="existing ' . $class . '"', 'UTF-8');
      }

    };
    $output = $environment->render('component.twig', ['attributes' => $attributes, 'body' => '<script>example</script>']);
    self::assertStringContainsString('<div id="existing" class="existing teaser-card">', $output);
    self::assertStringContainsString('&lt;script&gt;example&lt;/script&gt;', $output);
    self::assertStringContainsString('<strong>Override</strong>', $environment->render('caller.twig', ['attributes' => $attributes]));
  }

}
