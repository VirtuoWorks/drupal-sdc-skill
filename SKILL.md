---
name: drupal-sdc-skill
description: Scaffold, migrate, and audit Drupal Single-Directory Components (SDCs). Use for new components, typed props and slots, legacy Twig migrations, duplicate detection, and component asset or compatibility reviews.
---

# Drupal Single-Directory Components

Find the Drupal web root, owning theme or module, and supported core versions. Check existing components, caller conventions, and the asset build. Reuse a component when its contract fits.

For a new component on Drupal 10.3+, run `php <skill-dir>/scripts/scaffold.php --help` and use the [scaffolder](scripts/scaffold.php) with PHP 8.2+. Pass a compact JSON declaration and use the returned file paths to add markup and behavior. Use the authoring reference when PHP is unavailable or the project needs a different contract.

Read only the reference relevant to the task.

- [Authoring and scaffolding](references/authoring.md) covers a worked example, caller integration, assets, and version gates.
- [Legacy theme migration](references/migration.md) covers extraction, adapters, cacheability, and removal of old code.
- [Audits and architecture](references/auditing.md) covers inventory, duplicate verification, performance, and evidence for findings.

## Component boundaries

Keep presentation in the SDC. Prepare entity data, translations, access decisions, and cache dependencies at the call site. Pass typed values as props and renderable content as slots. Preserve caller attributes and attachments. Use the target core's schema and the project's asset pipeline.

## Verification

Run the project's build and lint commands. Validate metadata and render the component through real callers on the lowest supported core version available. Check empty inputs, multiple instances, and relevant variants, assets, accessibility, and cache behavior.

During an audit, keep the project read-only unless the user requests changes. Report confirmed findings separately from candidates that need runtime or consumer evidence. After changes, name the affected callers, the checks performed, and any checks that required an unavailable environment.
