=== Oxyplug Prefetch & Prerender ===
Contributors: oxyplug
Tags: prerender, prefetch, core web vitals, speculationrules
Requires at least: 5.3
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Faster loading next pages by prerendering/prefetching all links a user hovers or addresses you prefer. It improves UX and Core Web Vitals score.

== Description ==
* Supported only on Chromium-based browsers since version 121+
* In the post/product category pages, first X items will be prefetched before the user clicks. The default value for X is **4** items and can be adjusted via the plugin settings.
* If a user hovers over a link, the destination page will be prefetched before click. Please note that this option is disabled by default and can be enabled via the plugin settings.
* It is also possible to prefetch some custom links on posts/products by entering preferred URLs.
* Similar actions can be applied with prerender.

== Installation ==
Install and Activate it.
For better result, customize the settings as you wish.

== Screenshots ==
1. You have the option to determine the number of pages to be prefetched automatically. (immediate)
2. Prefetch links on mouse hover. (moderate)
3. You have the option to determine the number of pages to be prerendered automatically. (immediate)
4. Prerender links on mouse hover. (moderate)
5. Specify URLs to be prerendered when on specific pages.
6. Specify URLs to be prefetched when on specific pages.
7. Excluding with specifying hrefs or css selectors if needed.
8. The speculationrules that contain the list of the urls to be prefetched and prerendered immediately.
9. The speculationrules that contain all of the urls to be prefetched and prerendered moderately excluding the the ones that are the values for "not" keys.

== Changelog ==
= 2.1.2 =
*Change init hook to plugins_loaded for load_plugin_textdomain*
*Tested up to 6.7*

= 2.1.1 =
*Revamp to speculation rules for prerender and prefetch instead of link tag*
*Fix minor bugs*
*Tested up to 6.5.2*

= 2.0.1 =
*Fix Prerender/Prefetch on hover*
*Tested up to 6.4.2*

= 2.0.0 =
*Prerender added, similar to prefetch*

= 1.3.0 & 1.4.0 =
*The menu moved to Tools menu as a submenu*
*Some unnecessary Headsup! removed*
*Tested up to 6.1*

= 1.2.0 =
*Some strings that were static got dynamic to be translated*
*French translation added*

= 1.1.0 =
*Prefetch a static url when in a page*
*Fix `save_post` hook error*

= 1.0.1 =
*Add default value for hover delay*
*Add `Settings` link in plugin list*

= 1.0.0 =
*Release Date - 18 January 2022*
