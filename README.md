# Drupal SDC skill

[![Install on skills.sh](https://img.shields.io/badge/skills.sh-install-111111)](https://skills.sh/virtuoworks/drupal-sdc-skill/drupal-sdc-skill)
[![Codacy code quality](https://app.codacy.com/project/badge/Grade/d773846add594697aafe319b70fc4cde)](https://app.codacy.com/gh/VirtuoWorks/drupal-sdc-skill/dashboard)
[![Codacy code coverage](https://app.codacy.com/project/badge/Coverage/d773846add594697aafe319b70fc4cde)](https://app.codacy.com/gh/VirtuoWorks/drupal-sdc-skill/coverage)

An agent skill for building and reviewing Drupal Single-Directory Components. Its PHP scaffolder generates typed component starters for Drupal 10.3+. The skill also covers legacy theme migration, duplicate detection, and Drupal version differences.

## Install

With Node.js 22.20 or later, run the following from your Drupal project root and select your agent when prompted.

```sh
npx skills add VirtuoWorks/drupal-sdc-skill --skill drupal-sdc-skill
```

Add `-g` to make the skill available across your projects.

```sh
npx skills add VirtuoWorks/drupal-sdc-skill --skill drupal-sdc-skill -g
```

Pass `--agent codex` to select Codex directly. See the [skill on skills.sh](https://skills.sh/virtuoworks/drupal-sdc-skill/drupal-sdc-skill) for its listing and install command.

## Use

- `$drupal-sdc-skill Scaffold a teaser card in my theme for Drupal 10.3 using our Sass build.`
- `$drupal-sdc-skill Migrate the article teaser to an SDC and preserve field rendering, caching, and behavior.`
- `$drupal-sdc-skill Audit the custom theme for duplicate components and redundant asset attachments.`

Read [SKILL.md](SKILL.md) for the entry point. The references cover [authoring and scaffolding](references/authoring.md), [legacy migration](references/migration.md), and [audits and architecture](references/auditing.md).

For a project install in Codex, run the scaffolder from your Drupal project with PHP 8.2+.

```sh
php .agents/skills/drupal-sdc-skill/scripts/scaffold.php --help
```

For a global install in Codex, use `~/.agents/skills/drupal-sdc-skill/scripts/scaffold.php` as the script path.

Use `--with-unit-test` to include a PHPUnit test for the generated component's metadata and Twig.

## Contribute

Keep `SKILL.md` focused on deciding what to do and which reference to read. Put detailed steps and examples in the relevant reference. Cite Drupal documentation or core source for version-sensitive guidance. When changing an example, test it against the oldest Drupal version it claims to support when that runtime is available. Describe the checks and any version limits in the pull request.

Run Composer with PHP 8.4.1 or later from the skill checkout to install the development dependencies. The coverage command requires Xdebug and fails below 100% executable PHP line coverage across the scaffolder.

```sh
composer install
composer phpcs
composer test
composer test:coverage
```

Run `composer phpcbf` to fix Drupal coding standards violations automatically, then rerun `composer phpcs` to check for remaining violations.

Use focused [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/). For example, use `fix: preserve cache metadata in migration example` for a correction to the skill's behavior.

## License

[MIT](LICENSE)
