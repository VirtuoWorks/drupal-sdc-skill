# Audits and architecture

## Inventory the theme and its callers

Identify the active theme, base themes, custom modules, Drupal version range, and asset build. Start with files the project owns. Include upstream components when they are a dependency or replacement target.

- List each `.component.yml` with its provider ID, props, slots, variants, and generated or explicit asset library. Flag missing Twig files, name mismatches, unintended ID collisions, and assets absent from deployment.
- Search Twig for component IDs and `include`, `embed`, or `extends` calls. Search PHP for `#component` and generated library IDs. Check preprocess hooks, theme suggestions, library definitions, and exported config for display or builder integrations. Include legacy templates and partials in a duplicate hunt.
- Resolve dynamic IDs and configuration references with local runtime evidence when available. Record unresolved callers as a limit on removal or consolidation.

## Verify duplicate candidates

Group candidates by UI role and DOM structure. Ignore superficial whitespace and formatting on the first comparison. Compare meaningful elements, links or buttons, heading and labeling behavior, props and slots, variants, JS hooks, and computed styles. Read at least one caller for each candidate before proposing a merge. For interactive candidates, compare their focus, keyboard, and state changes.

Choose the change that preserves each caller's contract:

| Evidence | Proposed change |
| --- | --- |
| Same semantics, inputs, behavior, and asset requirements | Pick the existing component with the clearer contract and migrate callers to it. Add an adapter if its public contract differs. |
| Same semantics with a finite appearance difference | Use a native variant where supported or a typed appearance prop for older core. |
| Same container with different nested content | Share the container and expose a slot for the caller's content. |
| Similar visuals with different roles or behavior | Keep separate components. Consider sharing design tokens or a smaller primitive where the shared behavior is real. |

Prioritize shared components with repeated maintenance or credible drift. When a component has several unrelated props or branches, trace those combinations to callers. Extract a smaller component only where its contract is useful independently. Test the candidate with missing optional content and repeated instances.

## Check assets and cache behavior

Check whether the same styles or scripts enter the page through an SDC library and a global bundle. Follow library dependencies, overrides, and extensions before recommending removal. Inspect pages that use the component and pages that do not. Use an isolated copy if the production build writes files. Compare transfer size, request count, cache reuse, and load order with the production aggregation settings. Evaluate `core/components.all` only with representative measurements.

Trace cache contexts, tags, max-age, and attachments from caller render arrays through slots. Test access or language changes when output varies. Check behavior attachment after AJAX or BigPipe and with multiple instances. Use the target core version when checking generated libraries and schema validation. [SDC FAQ](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/frequently-asked-questions) · [Render cacheability](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)

## Report findings

Order findings by user impact. Give each finding a severity, confidence level, file and line references, affected callers, observed behavior, and a concrete change with a verification step. For a duplicate, name both components and explain the shared contract or the difference that prevents reuse.

Call out gaps such as unavailable runtime output, missing compiled assets, or dynamic consumers that could not be resolved. Use static evidence for discovery and code findings. Reserve claims about rendering, payload size, or cache behavior for observed runtime results.
