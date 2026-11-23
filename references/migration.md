# Legacy theme migration

## Inventory one rendering path

Start with a repeated pattern that has a stable contract, such as a teaser card used in a listing and a related-content block. Trace it from Drupal's route, view mode, block, or formatter to the Twig suggestion that actually renders. Use Twig debug in a local environment when the active suggestion is unclear.

Record the data source, preprocess hooks, render arrays, attached libraries, and consumed CSS classes or JS selectors. Inspect base-theme and subtheme overrides. Check exported configuration for display plugins, layouts, and component IDs before changing a public contract. Capture a representative output with an empty optional field and with a populated field.

## Extract the presentation

1. Use the [scaffolder](authoring.md#scaffold-a-card) when the migration needs a new SDC. Keep entity access, data preparation, and translation in the caller. Pass structured values as props and visible fields as renderable slots.
2. Keep the old theme hook or Twig suggestion as an adapter. Preserve its contextual links and any surrounding markup the component does not own. Retain attributes used by CSS, JS, or Drupal, and keep existing public component IDs or give their callers a transition path.
3. Keep cache contexts, tags, and max-age from values that influence the output. Preserve `#attached`, including libraries and `drupalSettings`. Leave field render arrays intact where possible so Drupal can render them and bubble metadata. If PHP extracts a scalar from an entity or field, preserve the relevant access result and cacheable dependencies on the parent render array.
4. Move only selectors and behaviors owned by the component. Keep shared rules in the theme and declare dependencies explicitly. Verify any change in CSS order or in library timing, especially when a global library previously initialized an AJAX behavior.

For a teaser, the legacy `node--article--teaser.html.twig` can keep the Drupal wrapper and use the [card example](authoring.md#scaffold-a-card) inside it. Pass the summary render array through the card's body slot. Keep the title as a link in a heading slot if the legacy title is interactive. If a parent template prints the remainder of `content`, exclude fields already rendered in slots so they appear once.

[Cacheability of render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays) · [Bubbleable metadata](https://www.drupal.org/docs/8/api/render-api/bubbleable-metadata)

## Verify and remove

Render both paths with the same data, theme, language, and access context. Compare semantics, visible content, contextual links, classes and data attributes used by callers, and the attached libraries. Repeat with warm caches where cache behavior can differ, and test keyboard interaction and AJAX insertion if the component is interactive.

Migrate additional callers in small groups. Search Twig, PHP, theme metadata, and exported configuration before deleting an old template, selector, library, or component ID. Treat dynamic names and stored configuration as unknown until confirmed. Keep the adapter when downstream consumers still depend on it.
