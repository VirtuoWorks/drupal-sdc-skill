# Authoring and scaffolding

## Discovery and compatibility

Place a component beneath the owning theme or module's `components/` directory. Subdirectories can organize related components. Drupal resolves a component by `provider:component-name`, where the provider is the theme or module machine name. Use the same component name for the metadata and Twig files. Choose a new name only after checking the provider's existing IDs.

| Target core | What to check |
| --- | --- |
| 10.1–10.2 | SDC is experimental. Confirm the core SDC module is enabled and use the library names for that version. |
| 10.3 and later | SDC is in the core render system. The generated library uses `core/components.PROVIDER--NAME`. |
| 11.2 and later | Declare native `variants` in component metadata. Pass `variant` in Twig or `#variant` in PHP. |
| 11.3 and later | The PHP component render element accepts `#attributes`. |

For a wider compatibility range, use a typed appearance prop such as `appearance` when native variants are unavailable. Use the installed core schema for newer metadata, such as slot constraints. Check the target core source before using newer features such as SDC form elements in 11.4. Verify integrations such as Canvas, UI Patterns, and Storybook against their installed versions when they are part of the request. [SDC core integration](https://www.drupal.org/node/3410260) · [Native variants](https://www.drupal.org/node/3517062) · [Component usage](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component) · [Core render element](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21ComponentElement.php/class/ComponentElement/11.x) · [SDC form elements](https://www.drupal.org/node/3585881)

## Define the contract

- Declare prop types in `props` as JSON Schema. List required props and constrain values with ranges or enums where callers need a finite choice. Describe expected inputs in the metadata. If a default affects rendering, implement and test the fallback.
- Use slots for markup, Drupal render arrays, and caller-selected child components. Use a Twig variable with `include()` for a caller-supplied value. Use a Twig block with `embed` when the caller supplies block content. If PHP renders the component, expose the corresponding Twig block for each slot that the caller fills through `#slots`.
- Declare a prop schema in every module component. Use schemas in themes as well, even when optional. A theme can require them with `enforce_prop_schemas: true` in its `.info.yml`. For a component without props, use an object schema with `properties: {}`.
- Keep the template usable in multiple contexts. Use semantic HTML, keep heading levels appropriate to the caller, and use native controls when possible. Retain Drupal's `attributes` object on the component root. Use unique IDs when a control must refer to another element. Leave Twig autoescaping enabled.

Drupal's documentation distinguishes authoring rules from runtime validation. Use the target environment's component validation configuration and check invalid inputs in development. Use the schema that ships with the target core version for editor support. [Component creation](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/creating-a-single-directory-component) · [Props and slots](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/what-are-props-and-slots-in-drupal-sdc-theming) · [Annotated metadata](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/annotated-example-componentyml)

## Scaffold a card

The example uses `my_theme:card` and works with the APIs available in Drupal 10.3. Create `components/card/card.component.yml`.

```yaml
name: Card
description: A titled content card.
props:
  type: object
  required:
    - title
  properties:
    title:
      type: string
      title: Title
      description: Plain text for the card heading.
    heading_level:
      type: integer
      title: Heading level
      enum: [2, 3, 4, 5, 6]
slots:
  body:
    title: Body
    description: Renderable content beneath the heading.
```

Create `components/card/card.twig`. The heading fallback handles callers that omit the level and constrains the tag even when development validation is disabled.

```twig
{% set heading_level = heading_level|default(2) %}
{% set heading_level = heading_level in [2, 3, 4, 5, 6] ? heading_level : 2 %}
<article{{ attributes.addClass('card') }}>
  <h{{ heading_level }} class="card__title">{{ title }}</h{{ heading_level }}>
  {% block body %}{{ body|default('') }}{% endblock %}
</article>
```

Call it from a conventional Drupal Twig template. Pass values needed inside an isolated `embed` through `with`.

```twig
{% embed 'my_theme:card' with {
  title: card_title,
  heading_level: 3,
  field_content: content.field_summary
} only %}
  {% block body %}{{ field_content }}{% endblock %}
{% endembed %}
```

Prepare `card_title` as a plain string. For a heading that contains renderable markup, give the heading its own slot. You can also call the example with `include('my_theme:card', {title: card_title, body: card_body}, with_context = false)` because the slot block renders the `body` variable by default.

From PHP, use the component render element. Here `$card_title` is a string and `$card_body` is a Drupal render array.

```php
return [
  '#type' => 'component',
  '#component' => 'my_theme:card',
  '#props' => ['title' => $card_title, 'heading_level' => 3],
  '#slots' => ['body' => $card_body],
];
```

## Styles and behavior

Write `components/card/card.css` directly, or use `components/card/card.scss` as its source. This example works as either CSS or SCSS.

```css
.card {
  padding: var(--card-padding, 1rem);
}

.card__title {
  margin-block-start: 0;
}
```

Prefer the project's existing Sass build. For a standalone Dart Sass build, run the following from the provider directory and include the generated CSS in the deployment.

```sh
sass --no-source-map components/card/card.scss components/card/card.css
```

Use selectors scoped to the component and keep shared tokens in the existing design system. If a component also imports Sass partials, check the generated CSS for repeated resets or rules. Check relative asset URLs against the generated file's location. [Component asset discovery](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/creating-a-single-directory-component) · [Dart Sass CLI](https://sass-lang.com/documentation/cli/dart-sass/)

Drupal attaches same-named CSS and JS when the component renders. If extra files or dependencies are needed, add them under the component metadata's `libraryOverrides`. For JS that initializes after AJAX or BigPipe inserts markup, attach a Drupal behavior scoped to its context and use `once()` to avoid binding twice.

```yaml
libraryOverrides:
  dependencies:
    - core/drupal
    - core/once
```

Use `replaces` in a theme's component metadata for a compatible component replacement. Confirm that both sides have schemas and that the replacement satisfies core's contract checks. For changes limited to upstream assets, use `libraries-override` or `libraries-extend` on the generated library as appropriate. [SDC FAQ](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/frequently-asked-questions) · [JavaScript API](https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview)

The [Akabia overview](https://akabia.fr/blog/drupal-11-single-directory-components-sdc-avantages) explains how colocated components support a design system. Use Drupal’s component creation guide for file naming and setup.
