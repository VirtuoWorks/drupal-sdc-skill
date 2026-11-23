---
name: drupal-sdc-skill
description: Create, migrate, and audit Drupal Single-Directory Components (SDCs). Use for component scaffolding, legacy Twig theme migration, duplicate detection, component architecture, prop and slot schemas, or Drupal component asset and compatibility reviews.
---

# Drupal Single-Directory Components

Inspect the target project before proposing or changing components. Find the Drupal web root, the owning theme or module, the installed core version in Composer, and the lowest version the project supports. Check existing components, build scripts, libraries, and caller conventions. Treat a supplied support range as the compatibility target.

Read the reference that matches the request.

- [Authoring and scaffolding](references/authoring.md) covers component contracts, rendering, CSS and SCSS, JavaScript, overrides, and version gates.
- [Legacy theme migration](references/migration.md) covers extraction, adapters, cacheability, and removal of old code.
- [Audits and architecture](references/auditing.md) covers inventory, duplicate verification, performance, and evidence for findings.

## Component boundaries

Keep presentation in the SDC. Prepare entity data, translations, access decisions, and cache dependencies at the call site. Use schema-typed props for values and slots for renderable content. Pass inputs explicitly and preserve the caller's attributes and attachments. Reuse an existing component when its contract fits.

Use the target core version and its shipped schema to distinguish Drupal requirements from project conventions. Prefer matching CSS and JS filenames for automatic attachment. If the theme uses Sass, build and deploy matching CSS. Use the existing asset pipeline and check whether it already bundles the same styles globally.

## Verification

Use the project's build and lint commands. Validate metadata and render the affected component through its real call sites on the lowest supported core version available. Exercise optional or empty inputs, multiple instances, and relevant variants. Check asset attachment, accessibility, and cache-sensitive output as applicable.

During an audit, keep the project read-only unless the user requests changes. Report confirmed findings separately from candidates that need runtime or consumer evidence. After changes, name the affected callers, the checks performed, and any checks that required an unavailable environment.
