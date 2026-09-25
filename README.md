# CodeLine Services Cards

**Version:** 1.5.5

A WordPress plugin for managing and displaying CodeLine service cards. It provides:

- a horizontal Services slider (`[codeline_services_cards]`),
- a full Services overview page (`[codeline_services_page]`),
- a detail layout for individual Service Pages,
- a lightweight animated canvas background behind the Services slider.

The slider's settings are **completely independent from the CodeLine Cases plugin**. Services uses its own option, settings group, admin page, CSS classes and JavaScript. Changing one plugin's slider settings never affects the other.

---

## Features

- **Service Page** post type (`cl_service_page`) with a **Categorieen** (categories) taxonomy (`cl_service_category`).
- One Service Page per category. It is created automatically when a category is saved.
- An admin table (**Service Cards**) for editing each card's image, title, description, order and visibility inline.
- A Services slider:
  - arrow navigation on desktop,
  - touch swipe on tablet and mobile,
  - optional desktop-only autoplay controlled by an administrator.
- **Services Slider Style** settings for card ratio, card width, gaps, image border radius and autoplay.
- A Services overview page with an intro, labels, a "Lees meer" (read more) button and up to two linked Cases per service.
- A Service Page detail layout: the featured image, the intro, detail rows and linked Cases.
- Entrance reveal and gentle floating animation for the cards. Both are disabled for visitors who prefer reduced motion.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer

## Installation

1. Upload the `codeline-services-cards` folder to `wp-content/plugins/`, or upload the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **CodeLine Services Cards** under **Plugins**.
3. Add the shortcodes to a page (for example, with an Elementor Shortcode widget).

## Updating

- Keep the plugin folder name `codeline-services-cards`. A different folder name installs as a separate plugin instead of updating this one.
- Saved settings and content are stored in the database and are kept across updates.
- Frontend CSS and JavaScript are versioned with the plugin version, so browsers fetch the new files after an update.
- Permalinks are flushed automatically once after each version change.

## Shortcodes

There is no Gutenberg block. Content is placed with shortcodes.

### `[codeline_services_cards]`

Renders the Services slider. It shows every published, visible Service Page, ordered by **Card Volgorde** (lowest first).

| Attribute | Default | Description |
|---|---|---|
| `limit` | `-1` | Maximum number of cards. `-1` shows all (capped at 200). |
| `category` | empty | Only show Service Pages in this category slug. |
| `desktop`, `tablet`, `mobile` | `4`, `2`, `1` | Kept for backward compatibility. Card width now comes from **Services Slider Style**. |
| `desktop_peek`, `tablet_peek`, `mobile_peek` | `72`, `56`, `56` | Kept for backward compatibility. They no longer affect the layout. |

### `[codeline_services_page]`

Renders the full Services overview: every category that has **Gebruik op Services pagina** enabled, ordered by **Volgorde**.

| Attribute | Default | Description |
|---|---|---|
| `taxonomy` | `cl_service_category` | Taxonomy used for the service list. |
| `limit` | `-1` | Maximum number of services. |
| `case_post_type` | `product_review` | Post type for linked Cases. If it does not exist, a matching Cases post type is detected automatically. |
| `case_taxonomy` | `product_review_category` | Taxonomy used to find Cases when none are selected manually. |
| `case_count` | `2` | Cases per service (1–8). |
| `case_link_mode` | `single` | `single` links to each Case; `archive` links to the category archive. |
| `case_target_url` | empty | Custom archive URL used with `case_link_mode="archive"`. |
| `case_query_key` | `category` | Query parameter added to `case_target_url`. |

### Preserved identifiers

These identifiers are unchanged from earlier versions:

- Shortcodes: `codeline_services_cards`, `codeline_services_page`
- Post type `cl_service_page` (rewrite slug `service-page`) and taxonomy `cl_service_category`
- Post meta:
  - `_cl_service_image_id`
  - `_cl_service_card_title`
  - `_cl_service_card_desc`
  - `_cl_service_card_order`
  - `_cl_service_visible`
  - `_cl_service_detail_items`
  - `_cl_service_detail_case_ids`
- Term meta:
  - `_cl_service_enabled`
  - `_cl_service_title`
  - `_cl_service_text`
  - `_cl_service_labels`
  - `_cl_service_order`
  - `_cl_service_case_1`
  - `_cl_service_case_2`
  - `_cl_service_page_id`
- AJAX action `clsc_update_card` (nonce action `clsc_card_update`)
- Frontend CSS root class `.clsc` (slider) and `.clsp` (Services page)

## Admin menu

Everything is under **Service Page** in the WordPress admin menu:

| Menu item | Capability | Purpose |
|---|---|---|
| Service Page (list) | `edit_posts` | The Service Pages themselves. They cannot be added manually; they are created from categories. |
| Categorieen | `manage_categories` | Service categories: title, text, labels, order, Services-page toggle and linked Cases. |
| Service Cards | `edit_posts` | Inline editing of each card's image, title, description, order and visibility. |
| Services Intro | `manage_options` | Title and text shown at the top of `[codeline_services_page]`. |
| Service Stijl | `manage_options` | Show or hide the "Lees meer" button and set its colours. |
| Services Slider Style | `manage_options` | Slider settings (see below). |

Notes:

- A new Service Page is **hidden** from the slider until **Toon deze card op de website** is enabled.
- **Deleting a category also permanently deletes its linked Service Page.**

## Services Slider Style settings

**Location:** Service Page → Services Slider Style.

The page uses the WordPress Settings API. Saving requires the `manage_options` capability and a valid nonce.

Every value is sanitized on save. An empty, non-numeric or out-of-range value falls back to its default, and decimals are rounded. Until the page is saved for the first time, the defaults below are used; nothing is written to the database automatically.

| Setting | Range | Default |
|---|---|---|
| Enable automatic movement on desktop | on / off | **off** |
| Card aspect ratio: width | 1–100 units | 9 |
| Card aspect ratio: height | 1–100 units | 16 |
| Card width: desktop (1200px and up) | 120–480 px | 210 |
| Card width: tablet (768–1199px) | 100–400 px | 195 |
| Card width: mobile (below 768px) | 80–320 px | 180 |
| Gap between cards: desktop | 0–80 px | 20 |
| Gap between cards: tablet | 0–80 px | 16 |
| Gap between cards: mobile | 0–80 px | 12 |
| Border radius (image corners) | 0–48 px | 10 |

The defaults match the homepage CodeLine Cases slider's appearance at the time of this release. After that, the two plugins' settings are independent.

Images always fill the configured ratio using `object-fit: cover`, centred, and are clipped to the configured corner radius.

## Slider behaviour

### Responsive breakpoints

| Range | Name | Navigation |
|---|---|---|
| 1200px and up | Desktop / laptop | Previous / Next arrows; optional autoplay |
| 768px – 1199px | Tablet | Touch swipe |
| Below 768px | Mobile | Touch swipe |

These are the same breakpoints the CodeLine Cases homepage slider uses.

### Desktop (1200px and up)

- The slider moves **only** with the Previous / Next arrow buttons. Each click moves one card, and rapid clicks add up.
- Mouse dragging and the mouse wheel / trackpad do not move the slider. Vertical page scrolling over the slider works normally.
- Arrow styling:
  - 40px white circles with a black arrow icon,
  - black with a white icon on hover,
  - no shadow.
- The arrows are vertically centred on the card images, and stay centred when the ratio or card width changes.
- An arrow is dimmed and disabled at the start or end of the slider. Both arrows are hidden when all cards fit on screen.

### Tablet and mobile (below 1200px)

- The arrows are hidden.
- Cards are moved by native touch swipe, snapping to each card. Vertical page scrolling is not blocked.
- Autoplay never runs, regardless of the admin setting.
- The slider does not cause horizontal page scrolling.

### Optional desktop autoplay

Autoplay is off by default and can only be enabled by an administrator in **Services Slider Style**. There is no play / pause control for visitors.

When enabled, the slider glides continuously at 40px per second. At each end it pauses for 1.5 seconds, then reverses direction.

Autoplay only runs when **all** of the following are true:

- the viewport is 1200px or wider,
- the visitor has not enabled "reduce motion" in their operating system,
- the browser tab is visible,
- the mouse is not over the slider and keyboard focus is not inside it,
- no arrow animation is in progress.

Each animation step is capped, so returning to a background tab does not make the slider jump. The arrows keep working while autoplay is enabled.

### Accessibility and reduced motion

- The arrows are real `<button>` elements labelled "Vorige service" and "Volgende service".
- The slider track is focusable and can be moved with the Left / Right arrow keys.
- Links, arrows and the track show a visible focus outline.
- With `prefers-reduced-motion: reduce`:
  - autoplay is disabled,
  - the card reveal and floating animations are disabled,
  - arrow navigation moves instantly instead of animating,
  - the canvas background does not animate.

### Animated background

`[codeline_services_cards]` automatically adds a lightweight canvas background to the section that contains it. The background can also be added to any Elementor section by giving it the CSS class `codeline-services-background`.

The background pauses while it is off screen or the browser tab is hidden.

If the section also contains an Elementor heading (and a text widget after it, before the cards), the plugin only adds a class so they show in white on the dark background. They are never moved out of their Elementor widgets, so the widget and container spacing, alignment and responsive controls keep working. If the section has no heading, none is shown; headings from other sections are never used.

## Architecture

```
codeline-services-cards/
├── codeline-services-cards.php                       Plugin header, constants, hooks
├── README.md
├── includes/
│   ├── trait-codeline-services-cards-core.php        Post type, taxonomy, migrations, helpers
│   ├── trait-codeline-services-cards-admin.php       Admin pages, meta boxes, category fields, AJAX
│   ├── trait-codeline-services-cards-frontend.php    Shortcodes, templates, asset loading
│   └── trait-codeline-services-cards-slider-settings.php  Services Slider Style settings
└── assets/
    ├── services-cards.css / services-cards.js        Slider and Services page styles / slider behaviour
    ├── services-enhancements.css                     Card image glow and hover effects
    ├── services-background.css / services-background.js  Animated canvas background
    └── admin-cards-styles.css / admin-cards.js        Service Cards admin table
```

`codeline-services-cards.php` is the only plugin bootstrap. It defines the class `CodeLine_Services_Cards`, which is composed of the four traits above.

The slider uses the plugin's own lightweight script. It does not load Swiper or any other slider library.

Frontend assets are loaded only on pages that contain the shortcodes and on Service Page detail pages. The shortcodes also enqueue their own assets when rendered from a template or Elementor Global Template.

## Stored data

**Options:**

| Option | Contents |
|---|---|
| `clsc_slider_style` | Services Slider Style settings |
| `clsc_service_page_intro` | Services Intro title and text |
| `clsc_service_style` | Service Stijl settings ("Lees meer" button) |
| `clsc_display_settings` | Legacy card-count defaults; still read, no longer editable in the admin |
| `clsc_rewrite_flushed_version` | Plugin version for which permalinks were last flushed |
| `clsc_data_migration_version` | One-time category ↔ Service Page link migration marker |

**Posts and terms:** the post meta and term meta listed under *Preserved identifiers* are stored on Service Pages and Service categories.

## Deactivation and uninstall

- The plugin has no deactivation routine and no uninstall routine.
- Deactivating or deleting it leaves all options, Service Pages, categories and meta in the database.
- The shortcodes stop rendering while the plugin is inactive.
- Reactivating the plugin restores everything as it was.

## Development and validation

Run from the plugin folder:

```bash
# PHP syntax
for f in codeline-services-cards.php includes/*.php; do php -l "$f"; done

# JavaScript syntax
for f in assets/*.js; do node --check "$f"; done
```

After changing frontend CSS or JavaScript, bump the version in **both** places in `codeline-services-cards.php`:

- the `Version:` header,
- the `VERSION` constant.

The frontend asset URLs use this version for cache busting.

### Test coverage for 1.5.4

**Verified locally:**

- PHP and JavaScript syntax.
- Settings sanitization and fallbacks, using a stubbed test script.
- In Chrome, on a local test page:
  - arrow navigation and the disabled states,
  - arrow styling and centring at several ratios and card widths,
  - wheel and mouse drag not moving the slider,
  - the breakpoint behaviour,
  - absence of horizontal page overflow,
  - the autoplay pause / resume and hidden-tab behaviour.

**Not yet verified:**

- saving settings in a real WordPress admin,
- real touch-device swiping,
- reduced-motion behaviour,
- autoplay resuming after resizing a window back to desktop width.

## Packaging a ZIP

The ZIP must contain exactly one top-level folder, `codeline-services-cards/`, with this content:

```
codeline-services-cards/
├── codeline-services-cards.php
├── README.md
├── includes/   (the four trait-*.php files)
└── assets/     (the seven .css/.js files)
```

Do **not** include:

- `.git`, `.claude` or other editor/tool folders,
- old ZIP files, backups or test files,
- any additional copy of `codeline-services-cards.php`.

## Deployment checklist

1. Confirm the version in the header and in `VERSION` matches the release.
2. Run the validation commands above.
3. Build the ZIP with the single top-level folder `codeline-services-cards/`.
4. Back up the site (database and the existing plugin folder).
5. Upload and replace the existing plugin. Confirm WordPress reports an update of the same plugin, not a new one.
6. Check **Service Page → Services Slider Style**. Save once if you want to store explicit values.
7. On the frontend, check desktop (arrows), tablet and mobile (swipe) views and the Services page.
8. Clear any page cache or CDN cache if old CSS/JS is still served.

## Troubleshooting

- **The slider looks unstyled or outdated.**
  - Clear the page cache or CDN cache.
  - Confirm `services-cards.css?ver=1.5.5` is loading.
- **Arrows do not appear on desktop.**
  - Arrows only show at 1200px and wider.
  - They are hidden when all cards fit on screen.
- **Autoplay does not start.**
  - Autoplay only runs on desktop when the setting is enabled.
  - It does not run while the mouse is over the slider, while it has keyboard focus, or when the visitor prefers reduced motion.
- **A card does not appear.**
  - Enable **Toon deze card op de website** in the Service Page's card settings.
  - Check that the Service Page is published.
- **A setting reverts after saving.** The value was outside its allowed range and fell back to the default (see the settings table).
- **Service Page URLs return 404.** Visit **Settings → Permalinks** and click **Save** to flush permalinks.
- **The Media picker or inline card editing fails.** Check the browser console and the `admin-ajax.php` request in DevTools on **Service Page → Service Cards**.
