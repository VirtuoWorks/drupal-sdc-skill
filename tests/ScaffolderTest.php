<?php

declare(strict_types=1);

use DrupalSdcSkill\ComponentSpec;
use DrupalSdcSkill\FilesystemFaults;
use DrupalSdcSkill\ScaffoldCommand;
use DrupalSdcSkill\Scaffolder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Markup;

require_once __DIR__ . '/FilesystemFaults.php';
require_once __DIR__ . '/../scripts/ScaffoldCommand.php';

final class ScaffolderTest extends TestCase
{
    private const ID = 'my_theme:teaser-card';

    private string $provider;

    protected function setUp(): void
    {
        $this->provider = sys_get_temp_dir() . '/sdc-test-' . bin2hex(random_bytes(8));
        mkdir($this->provider);
        file_put_contents($this->provider . '/my_theme.info.yml', "name: My theme\ntype: theme\n");
    }

    protected function tearDown(): void
    {
        FilesystemFaults::$operation = null;
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->provider, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->provider);
    }

    public function testMinimalStarter(): void
    {
        $files = (new Scaffolder())->generate($this->provider, ComponentSpec::fromJson(self::ID, '{}'), false);
        self::assertSame(
            ['components/teaser-card/teaser-card.twig', 'components/teaser-card/teaser-card.component.yml'],
            $files,
        );
        $metadata = Yaml::parseFile($this->provider . '/' . $files[1], Yaml::PARSE_OBJECT_FOR_MAP);
        self::assertSame('Teaser Card', $metadata->name);
        self::assertSame('object', $metadata->props->type);
        self::assertEquals(new stdClass(), $metadata->props->properties);
        self::assertDirectoryDoesNotExist($this->provider . '/tests');
    }

    public function testTypedStarterRendersSlotsAndPreservesAttributes(): void
    {
        $label = "Editor's card: #1 🎉";
        [$twig, $scss, $css, $js, $metadata] = (new Scaffolder())->generate(
            $this->provider,
            ComponentSpec::fromJson(self::ID, json_encode([
                'name' => $label,
                'description' => '*Quoted* [description]',
                'props' => ['title' => 'string', 'optional' => 'null', 'item_count' => 'integer'],
                'required' => ['title'],
                'slots' => ['body', 'on'],
                'subdirectory' => 'layouts/cards',
                'assets' => ['scss', 'css', 'js'],
            ], JSON_THROW_ON_ERROR)),
            false,
        );

        $base = $this->provider . '/';
        self::assertSame('components/layouts/cards/teaser-card/teaser-card.twig', $twig);
        $data = Yaml::parseFile($base . $metadata);
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
        self::assertStringContainsString("once('my_theme:teaser-card', '.teaser-card', context)", file_get_contents($base . $js));

        $environment = new Environment(new ArrayLoader([
            'component.twig' => file_get_contents($base . $twig),
            'caller.twig' => "{% embed 'component.twig' %}{% block body %}<strong>Override</strong>{% endblock %}{% endembed %}",
        ]), ['strict_variables' => true]);
        $attributes = new class {
            public function addClass(string $class): Markup
            {
                return new Markup(' id="existing" class="existing ' . $class . '"', 'UTF-8');
            }
        };
        $output = $environment->render('component.twig', ['attributes' => $attributes, 'body' => '<script>example</script>']);
        self::assertStringContainsString('<div id="existing" class="existing teaser-card">', $output);
        self::assertStringContainsString('&lt;script&gt;example&lt;/script&gt;', $output);
        self::assertStringContainsString('<strong>Override</strong>', $environment->render('caller.twig', ['attributes' => $attributes]));
    }

    #[DataProvider('invalidSpecs')]
    public function testInvalidSpec(string $id, string $spec, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        ComponentSpec::fromJson($id, $spec);
    }

    public static function invalidSpecs(): iterable
    {
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
        yield 'duplicate contract names' => [self::ID, '{"props":{"body":"string"},"slots":["body"]}', 'both a prop and a slot'];
        yield 'unknown asset' => [self::ID, '{"assets":["svg"]}', 'only css, scss, or js'];
        yield 'null list' => [self::ID, '{"slots":null}', 'must be a list of strings'];
        yield 'wrong list element' => [self::ID, '{"slots":[false]}', 'must be a list of strings'];
        yield 'duplicate list values' => [self::ID, '{"assets":["js","js"]}', 'must contain unique values'];
        yield 'unsafe Twig identifier' => [self::ID, '{"slots":["invalid-slot"]}', 'Invalid prop or slot name'];
        yield 'reserved name' => [self::ID, '{"slots":["attributes"]}', 'Invalid prop or slot name'];
    }

    public function testCommandDryRunWithStdin(): void
    {
        [$status, $output, $error] = $this->runCommand(
            ['--dry-run', $this->provider, self::ID, '-'],
            '{"subdirectory":"foo","slots":["body"]}',
        );

        self::assertSame(0, $status, $error);
        self::assertSame('', $error);
        self::assertSame([
            'id' => self::ID,
            'files' => ['components/foo/teaser-card/teaser-card.twig', 'components/foo/teaser-card/teaser-card.component.yml'],
            'dry_run' => true,
        ], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testCommandReadsDeclarationFile(): void
    {
        $spec = $this->provider . '/component.json';
        file_put_contents($spec, '{"assets":["css"]}');
        [$status, $output, $error] = $this->runCommand([$this->provider, self::ID, $spec]);

        self::assertSame(0, $status, $error);
        self::assertStringContainsString('teaser-card.css', $output);
        self::assertFileExists($this->provider . '/components/teaser-card/teaser-card.css');
    }

    public function testDryRunListsTestPathInEitherFlagOrderWithoutWriting(): void
    {
        $spec = '{"subdirectory":"layouts/cards","slots":["body"]}';
        foreach ([['--dry-run', '--with-unit-test'], ['--with-unit-test', '--dry-run']] as $flags) {
            [$status, $output, $error] = $this->runCommand([...$flags, $this->provider, self::ID, '-'], $spec);

            self::assertSame(0, $status, $error);
            self::assertSame([
                'id' => self::ID,
                'files' => [
                    'components/layouts/cards/teaser-card/teaser-card.twig',
                    'components/layouts/cards/teaser-card/teaser-card.component.yml',
                    'tests/src/Unit/TeaserCardSdcTest.php',
                ],
                'dry_run' => true,
            ], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
        }
        self::assertDirectoryDoesNotExist($this->provider . '/components');
        self::assertDirectoryDoesNotExist($this->provider . '/tests');
    }

    public function testGeneratedUnitTestsRunAndDetectContractAndTemplateRegressions(): void
    {
        [$status, $output, $error] = $this->runCommand(['--with-unit-test', $this->provider, 'my_theme:plain']);
        self::assertSame(0, $status, $error);
        $plain = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['files'][2];
        self::assertSame('tests/src/Unit/PlainSdcTest.php', $plain);
        [$status, $output] = $this->runGeneratedTest($plain);
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('OK (2 tests,', $output);

        [$status, $output, $error] = $this->runCommand(
            ['--with-unit-test', $this->provider, self::ID, '-'],
            '{"props":{"title":"string","count":"integer"},"required":["title"],"slots":["body","footer"],"subdirectory":"cards/nested"}',
        );
        self::assertSame(0, $status, $error);
        [$twig, $metadata, $test] = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['files'];
        [$status, $output] = $this->runGeneratedTest($test);
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('OK (2 tests,', $output);

        $metadataPath = $this->provider . '/' . $metadata;
        $original = file_get_contents($metadataPath);
        file_put_contents($metadataPath, str_replace("type: 'integer'", "type: 'number'", $original));
        [$status, $output] = $this->runGeneratedTest($test);
        self::assertNotSame(0, $status);
        self::assertStringContainsString('testMetadataContract', $output);
        file_put_contents($metadataPath, $original);

        file_put_contents($this->provider . '/' . $twig, '{% missing_tag %}');
        [$status, $output] = $this->runGeneratedTest($test);
        self::assertNotSame(0, $status);
        self::assertStringContainsString('testTwigStarter', $output);
        self::assertStringContainsString('Twig\Error\SyntaxError', $output);
    }

    public function testRefusesCollidingTestNamesBeforeWritingComponent(): void
    {
        [$status, $output, $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
        self::assertSame(0, $status, $error);
        $test = $this->provider . '/' . json_decode($output, true, 512, JSON_THROW_ON_ERROR)['files'][2];
        $original = file_get_contents($test);

        [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, 'my_theme:teaser_card']);
        self::assertSame(1, $status);
        self::assertStringContainsString("Destination already exists at '$test'", $error);
        self::assertSame($original, file_get_contents($test));
        self::assertDirectoryDoesNotExist($this->provider . '/components/teaser_card');
    }

    public function testRefusesSymlinkAtTestDirectoryOrFile(): void
    {
        $elsewhere = $this->provider . '/elsewhere';
        mkdir($elsewhere);
        $tests = $this->provider . '/tests';
        symlink($elsewhere, $tests);
        [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
        self::assertSame(1, $status);
        self::assertStringContainsString("Refusing to use symlink '$tests'", $error);
        self::assertSame([], glob($elsewhere . '/*'));
        self::assertDirectoryDoesNotExist($this->provider . '/components');

        unlink($tests);
        mkdir($tests . '/src/Unit', 0777, true);
        $test = $tests . '/src/Unit/TeaserCardSdcTest.php';
        symlink($elsewhere . '/missing.php', $test);
        [$status, , $error] = $this->runCommand(['--dry-run', '--with-unit-test', $this->provider, self::ID]);
        self::assertSame(1, $status);
        self::assertStringContainsString("Refusing to use symlink '$test'", $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testTestWriteFailureRollsBackBothDestinations(): void
    {
        mkdir($this->provider . '/tests');
        FilesystemFaults::$operation = 'test-short-write';
        [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot write complete file', $error);
        self::assertStringContainsString('tests/src/Unit/TeaserCardSdcTest.php', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
        self::assertDirectoryDoesNotExist($this->provider . '/tests/src');
        self::assertDirectoryExists($this->provider . '/tests');
    }

    public function testReportsPathsLeftAfterCleanupFailure(): void
    {
        FilesystemFaults::$operation = 'cleanup-failure';
        [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
        $leftover = $this->provider . '/tests/src/Unit/TeaserCardSdcTest.php';

        self::assertSame(1, $status);
        self::assertStringContainsString("Could not clean up: $leftover", $error);
        self::assertFileExists($leftover);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testCommandDiagnostics(): void
    {
        [$status, $output, $error] = $this->runCommand([]);
        self::assertSame(1, $status);
        self::assertSame('', $output);
        self::assertStringContainsString('Usage:', $error);

        [$status, , $error] = $this->runCommand(['--with-unit-test', '--with-unit-test', $this->provider, self::ID]);
        self::assertSame(1, $status);
        self::assertStringContainsString('may only be supplied once', $error);

        [$status, , $error] = $this->runCommand([$this->provider, self::ID, $this->provider . '/missing.json']);
        self::assertSame(1, $status);
        self::assertStringContainsString('local JSON declaration file', $error);

        [$status, , $error] = $this->runCommand([$this->provider, 'other:teaser-card']);
        self::assertSame(1, $status);
        self::assertStringContainsString('other.info.yml', $error);

        $spec = $this->provider . '/component.json';
        file_put_contents($spec, '{}');
        FilesystemFaults::$operation = 'read-spec';
        [$status, , $error] = $this->runCommand([$this->provider, self::ID, $spec]);
        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot read the JSON declaration', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testRetryAndNestedDuplicateLeaveOriginalFilesIntact(): void
    {
        $scaffolder = new Scaffolder();
        $spec = ComponentSpec::fromJson(self::ID, '{"subdirectory":"nested/cards"}');
        $scaffolder->generate($this->provider, $spec, false);

        $twig = $this->provider . '/components/nested/cards/teaser-card/teaser-card.twig';
        file_put_contents($twig, 'edited');
        [$status, , $error] = $this->runCommand([$this->provider, self::ID, '-'], '{"subdirectory":"nested/cards"}');
        self::assertSame(1, $status);
        self::assertStringContainsString('Destination already exists', $error);

        [$status, , $error] = $this->runCommand([$this->provider, self::ID]);
        self::assertSame(1, $status);
        self::assertStringContainsString("Component 'teaser-card' already exists", $error);
        self::assertSame('edited', file_get_contents($twig));
        self::assertDirectoryDoesNotExist($this->provider . '/components/teaser-card');
    }

    public function testRefusesSymlinkInDestination(): void
    {
        mkdir($this->provider . '/elsewhere');
        symlink($this->provider . '/elsewhere', $this->provider . '/components');
        [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Refusing to use symlink', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/elsewhere/teaser-card');
    }

    public function testRollsBackWhenCreatingADirectoryFails(): void
    {
        $spec = json_encode(['subdirectory' => str_repeat('a', 256)], JSON_THROW_ON_ERROR);
        [$status, , $error] = $this->runCommand([$this->provider, self::ID, '-'], $spec);

        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot create directory', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testRollsBackWhenCreatingAFileFails(): void
    {
        $component = str_repeat('a', 245);
        [$status, , $error] = $this->runCommand([$this->provider, "my_theme:$component"]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot create file', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components');
    }

    public function testRollsBackOnShortWriteAndCanRetry(): void
    {
        mkdir($this->provider . '/components');
        file_put_contents($this->provider . '/components/existing.txt', 'existing');
        FilesystemFaults::$operation = 'short-write';
        [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot write complete file', $error);
        self::assertDirectoryDoesNotExist($this->provider . '/components/teaser-card');
        self::assertSame('existing', file_get_contents($this->provider . '/components/existing.txt'));

        FilesystemFaults::$operation = null;
        [$status, , $error] = $this->runCommand([$this->provider, self::ID]);
        self::assertSame(0, $status, $error);
    }

    public function testExitsBeforeWritingWhenLockIsUnavailable(): void
    {
        foreach (['open-lock' => 'Cannot open provider directory', 'lock' => 'Cannot lock provider directory'] as $fault => $message) {
            FilesystemFaults::$operation = $fault;
            [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

            self::assertSame(1, $status);
            self::assertStringContainsString($message, $error);
            self::assertDirectoryDoesNotExist($this->provider . '/components');
        }
    }

    public function testConcurrentCliRunsInDifferentGroupsProduceOneComponent(): void
    {
        $processes = [];
        foreach (['one', 'two'] as $group) {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/../scripts/scaffold.php', '--with-unit-test', $this->provider, self::ID, '-'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode(['subdirectory' => $group], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes[1], $pipes[2]];
        }

        $results = [];
        foreach ($processes as [$process, $out, $err]) {
            $output = stream_get_contents($out);
            $error = stream_get_contents($err);
            fclose($out);
            fclose($err);
            $results[proc_close($process)] = [$output, $error];
        }

        self::assertCount(2, $results);
        self::assertSame('', $results[0][1]);
        self::assertStringContainsString('already exists', $results[1][1]);
        self::assertCount(1, glob($this->provider . '/components/*/teaser-card/*.component.yml'));
        self::assertFileExists($this->provider . '/tests/src/Unit/TeaserCardSdcTest.php');
    }

    private function runCommand(array $args, string $input = ''): array
    {
        $stdin = fopen('php://memory', 'w+');
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        try {
            fwrite($stdin, $input);
            rewind($stdin);
            $status = (new ScaffoldCommand())->run(['scaffold.php', ...$args], $stdin, $stdout, $stderr);
            rewind($stdout);
            rewind($stderr);
            return [$status, stream_get_contents($stdout), stream_get_contents($stderr)];
        } finally {
            fclose($stdin);
            fclose($stdout);
            fclose($stderr);
        }
    }

    private function runGeneratedTest(string $test): array
    {
        $process = proc_open([
            PHP_BINARY,
            __DIR__ . '/../vendor/bin/phpunit',
            '--no-configuration',
            '--bootstrap',
            __DIR__ . '/GeneratedTestBootstrap.php',
            $this->provider . '/' . $test,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $output];
    }
}
