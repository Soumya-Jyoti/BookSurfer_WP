=== Book Surfer ===
Contributors: booksurfer
Tags: booking, appointments, yoga, dance studio, scheduling
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add live Book Surfer booking forms and class schedules to any page with a block or shortcode.

== Description ==

Book Surfer connects your WordPress site to your existing Book Surfer studio and lets
you drop a live booking form or class schedule onto any page.

Connect once, then pick a class from your studio and place it with the **Book Surfer**
block — or with the `[booksurfer]` shortcode if you use the Classic editor, Elementor,
Divi, or a page builder.

This plugin is a thin client. Your classes, bookings and payments are managed on
booksurfer.in — the plugin does not duplicate your dashboard inside WordPress. It
embeds your live forms and links you back out to manage everything else.

= Features =

* **Gutenberg block** with a class picker and a live preview in the editor.
* **`[booksurfer]` shortcode** for the Classic editor and page builders.
* **Three embed types** — a single class booking form, an all-classes form, or a class schedule.
* **Four layouts** for booking forms (Classic, Modern, Minimal, Compact) plus sidebar/dropdown styles for the all-classes form.
* **Styling controls** — brand colour, text colour, background, font and corner radius, applied to the live form.
* **Secure by design** — your Book Surfer tokens are stored encrypted on your server and never exposed to the browser.
* **Fast front end** — the plugin enqueues zero JavaScript and zero CSS on your public pages.

= External service =

This plugin connects to **Book Surfer**, a third-party booking service operated by
Book Surfer, and requires a Book Surfer account. The plugin is free and GPL-licensed;
the Book Surfer service itself is a separate, proprietary product.

The plugin communicates with Book Surfer in these ways:

* **When you connect:** your site opens `https://booksurfer.in/connect/wordpress` in a
  popup so you can approve access. Your site URL is sent so you can see which site is
  requesting access. Your site then exchanges a one-time code with
  `https://api.booksurfer.in` to obtain access tokens, which are stored on your server.
* **In the admin and block editor:** your site requests your class list from
  `https://api.booksurfer.in/api/widget/classes` so you can choose what to embed.
* **On your public pages:** the booking form is displayed in an iframe served from
  `https://api.booksurfer.in`. Your visitors' browsers load that iframe directly from
  Book Surfer, and any booking or payment data your visitors enter is submitted to
  Book Surfer, not to your site.

No visitor data is sent to Book Surfer by the plugin itself.

* Book Surfer website: https://booksurfer.in
* Terms of service: https://booksurfer.in/terms
* Privacy policy: https://booksurfer.in/privacy

= Source code =

The JavaScript in `build/` is compiled output. The complete, unminified TypeScript
and JavaScript sources, together with the build configuration used to produce it
(`webpack.config.js`, `package.json`), are available at:

https://github.com/Soumya-Jyoti/BookSurfer_WP

Build the plugin from source with `npm install && npm run build`.

== Installation ==

1. Install and activate the plugin.
2. Go to **Book Surfer** in the admin menu.
3. Click **Connect with Book Surfer** and approve access in the popup.
4. Add the **Book Surfer** block to any page, or copy a `[booksurfer]` shortcode from the settings screen.

== Frequently Asked Questions ==

= Do I need a Book Surfer account? =

Yes. The plugin embeds forms from your existing Book Surfer studio. It does not create
or manage classes itself.

= Where are my access tokens stored? =

On your own server, in the WordPress options table, with the long-lived token encrypted
using a key derived from your site's `AUTH_KEY` and `AUTH_SALT`. Tokens are never sent
to the browser and never appear in any page source, script, or API response.

= Does the connection work for every admin on the site? =

Yes. The connection belongs to the site, not to an individual user, so any administrator
can manage it and any editor can add booking forms to a page.

= What happens to my published forms if I disconnect? =

They keep working. The booking form is a public Book Surfer link, so pages you have
already published continue to render. Disconnecting only stops you browsing classes
and adding new forms from the admin.

= Does the plugin slow down my site? =

No. It adds no JavaScript and no CSS to your public pages. The booking form loads
lazily in an iframe.

= Can I use it with Elementor, Divi, or the Classic editor? =

Yes — use the `[booksurfer]` shortcode. You can copy a ready-made shortcode for any
class from the Book Surfer settings screen.

= Does it work on multisite? =

Yes. Each site in the network holds its own separate connection.

= Can I change the form's colours and fonts? =

Yes. The block sidebar exposes brand colour, text colour, background, font and corner
radius, and they apply to the live form.

== Screenshots ==

1. The settings screen after connecting, listing your studio's classes.
2. Choosing a class and layout when inserting the Book Surfer block.
3. The live booking form previewed in the block editor.
4. The block sidebar controls for layout, size and styling.
5. A published booking form on the front end.

== Changelog ==

= 1.0.0 =
* Initial release.
* Gutenberg block with class picker, live editor preview, and layout/size/styling controls.
* `[booksurfer]` shortcode for the Classic editor and page builders.
* Single-class booking forms, all-classes forms, and class schedules.
* Secure server-side connection with encrypted token storage.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
