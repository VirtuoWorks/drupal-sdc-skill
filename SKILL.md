---
name: drupal-sdc-skill
description: Scaffold, migrate, and audit Drupal Single-Directory Components (SDCs). Use for new components, typed props and slots, legacy Twig migrations, duplicate detection, and component asset or compatibility reviews.
---

# Drupal Single-Directory Components

Find the Drupal web root, owning theme or module, and supported core versions. Check existing components, caller conventions, and the asset build. Reuse a component when its contract fits.

For a new component on Drupal 10.3+, run `php <skill-dir>/scripts/scaffold.php --help` and use the [scaffolder](scripts/scaffold.php) with PHP 8.2+ and `--with-unit-test`. Pass a compact JSON declaration and use the returned file paths to implement markup, behavior, and feature assertions. Use the authoring reference when PHP is unavailable or the project needs a different contract.

Read only the reference relevant to the task.

- [Authoring and scaffolding](references/authoring.md) covers a worked example, caller integration, test selection, assets, and version gates.
- [Legacy theme migration](references/migration.md) covers extraction, adapters, cacheability, and removal of old code.
- [Audits and architecture](references/auditing.md) covers inventory, duplicate verification, performance, and evidence for findings.

## Component boundaries

Keep presentation in the SDC. Prepare entity data, translations, access decisions, and cache dependencies at the call site. Pass typed values as props and renderable content as slots. Preserve caller attributes and attachments. Use the target core's schema and the project's asset pipeline.

## Verification

Every created or updated SDC must have unit tests that cover its features and an end-to-end test that verifies its rendered output through a Drupal page. Add or update assertions in the project's existing suites. Use the [testing guidance](references/authoring.md#test-the-component) to choose page assertions and verify accessibility, assets, and cache behavior.

Run both test layers, metadata validation, and the project's build and lint commands on the lowest supported core version available. Report affected callers, test commands and outcomes, and any check blocked by the environment.

During an audit, keep the project read-only unless the user requests changes. Report confirmed findings separately from candidates that need runtime or consumer evidence.
