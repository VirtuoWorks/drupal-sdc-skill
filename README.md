# Drupal SDC skill

An agent skill for building and reviewing Drupal Single-Directory Components. It covers component contracts, CSS and SCSS builds, legacy theme migration, duplicate detection, and the Drupal version differences that affect implementation.

## Install

From the root of your Drupal project, clone the skill into Codex's project skills directory. The SSH URL requires GitHub access to the repository.

```sh
mkdir -p .agents/skills
git clone git@github.com:VirtuoWorks/drupal-sdc-skill.git .agents/skills/drupal-sdc-skill
```

For a user-wide install, use the home directory instead.

```sh
mkdir -p ~/.agents/skills
git clone git@github.com:VirtuoWorks/drupal-sdc-skill.git ~/.agents/skills/drupal-sdc-skill
```

Keep the entire directory so the skill can load its references. Start Codex from the Drupal project. For other Agent Skills clients, use the skills directory that the client documents.

## Use

- `$drupal-sdc-skill Scaffold a teaser card in my theme for Drupal 10.3 using our Sass build.`
- `$drupal-sdc-skill Migrate the article teaser to an SDC and preserve field rendering, caching, and behavior.`
- `$drupal-sdc-skill Audit the custom theme for duplicate components and redundant asset attachments.`

Read [SKILL.md](SKILL.md) for the entry point. The references cover [authoring and scaffolding](references/authoring.md), [legacy migration](references/migration.md), and [audits and architecture](references/auditing.md).

## Contribute

Keep `SKILL.md` focused on deciding what to do and which reference to read. Put detailed steps and examples in the relevant reference. Cite Drupal documentation or core source for version-sensitive guidance. When changing an example, test it against the oldest Drupal version it claims to support when that runtime is available. Describe the checks and any version limits in the pull request.

Use focused [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/). For example, use `fix: preserve cache metadata in migration example` for a correction to the skill's behavior.

## License

[MIT](LICENSE)
