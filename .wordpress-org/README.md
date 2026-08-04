# WordPress.org directory assets

Art shown on the plugin's WordPress.org listing page. **Not shipped inside the
plugin** — it lives in the SVN `assets/` directory, not `trunk/`.

The `Deploy to WordPress.org` step in `../.github/workflows/release.yml` sets
`ASSETS_DIR: .wordpress-org`, so anything placed here is published to SVN `assets/`
automatically on the next `v*` tag. This directory is excluded from the plugin zip.

## Required files

Drop them in this directory using these exact names.

| Filename               | Dimensions | Status | Notes                                    |
| ---------------------- | ---------- | ------ | ---------------------------------------- |
| `icon-128x128.png`     | 128 × 128  | done   | Search-results and plugin-card icon.      |
| `icon-256x256.png`     | 256 × 256  | done   | Retina icon.                              |
| `banner-772x250.png`   | 772 × 250  | done   | Header on the plugin page.                |
| `banner-1544x500.png`  | 1544 × 500 | done   | Retina banner.                            |
| `screenshot-1.png`     | any        | TODO   | Settings screen after connecting, listing classes. |
| `screenshot-2.png`     | any        | TODO   | Choosing a class and layout when inserting the block. |
| `screenshot-3.png`     | any        | TODO   | Live booking form previewed in the block editor. |
| `screenshot-4.png`     | any        | TODO   | Block sidebar controls for layout, size, styling. |
| `screenshot-5.png`     | any        | TODO   | A published booking form on the front end. |

`.jpg` is accepted in place of `.png`. An `icon.svg` may be supplied instead of the
two PNG icons.

## How the current icons and banners were made

Source art: `../docs/brand/icon.png` (744 × 744 RGBA, transparent rounded corners).
It lives under `docs/` deliberately — `docs` is excluded by `.distignore` **and** is
absent from `wp-scripts plugin-zip`'s allowlist, so the source never ships inside the
plugin. Do not move it to `public/`: that path IS in the allowlist and would be
bundled into the distributed zip.

Brand palette read directly out of that file:

| Colour    | Share | Role                  |
| --------- | ----- | --------------------- |
| `#003428` | 73.5% | Deep green background |
| `#ffffff` | 13.1% | Upper form            |
| `#b1ee31` | 13.1% | Lime lower form       |

Note this differs from the teal `#0D9488` palette used in the plugin's own CSS
(`src/shared/design-system.css`). The directory art follows the icon, not the UI.

The four files were rendered in headless Chromium at a 1× base with a 2× device-scale
pass, so each retina file is a pixel-exact double of its base rather than an
independent export that could drift.

To regenerate, re-run the generator with the source art in place. If you replace
`icon.png` with different artwork, re-read the palette rather than assuming these hexes.

## Screenshot captions come from readme.txt

The `== Screenshots ==` list in `../readme.txt` supplies the captions, matched **by
position**: the first list item captions `screenshot-1.png`, the second captions
`screenshot-2.png`, and so on. The table above already mirrors that list — if you
reorder or reword one, change both files together or the captions will be wrong.

## Constraints

- Keep banners text-light and avoid putting anything critical near the edges: they are
  cropped hard on narrow viewports, and WordPress.org has rendered the plugin title
  over or immediately against the banner in past directory designs. The current banner
  keeps its wordmark centre-left with clear margins for this reason.
- Screenshots should show real UI. Mockups and marketing collateral get flagged in review.
- Do not include a Book Surfer logo you do not have the rights to publish under GPL-
  compatible terms; directory assets are not required to be GPL, but they must be
  yours to distribute.
