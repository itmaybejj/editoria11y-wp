=== Editoria11y Accessibility Checker ===
Contributors: itmaybejj
Tags: accessibility, accessibility automated testing, accessibility checker
Requires at least: 5.6
Tested up to: 6.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accessibility "spellcheck," written to be intuitive and useful for non-technical content creators.

== Description ==

Editoria11y ("editorial accessibility ally") is a quality assurance tool built around three key needs not met by crawlers and developer-focused scanning tools:

1. It checks automatically. Authors do not need be taught to (and remember to!) press a button or visit a dashboard.
2. It checks content in context on the page, not just within the post editor, allowing it to detect issues in themes and widgets that only appear after publishing.
3. It focuses exclusively on **content** issues: assisting authors at fixing the things that are their responsibility, without confusing or annoying them with alerts for code or theme issues. Editoria11y is meant to **supplement**, not replace, testing with comprehensive tools and real assistive devices.

**Editoria11y is not an [overlay](https://overlayfactsheet.com/)!** It does not modify your site in any way. 

## The authoring experience

* When authors are viewing pages, Editoria11y's toggle indicates if any issues are present (no issues, some manual checks needed, some definite issues found).
* When Editoria11y finds something new, an alert is placed on elements with issues, with tooltips that explain the problem and what actions are needed to resolve it. If the item might be a false positive, buttons are available to ignore the alert on this page, either for the current user ("Hide alert") or for all users ("Mark as Checked and OK"). 
* The main panel allows authors to show and hide tooltips, step through the issues on the page, restore previously dismissed alerts, visualize text alternatives for images on the page ("alts"), and view the document's heading outline.

Note that all this runs locally within your site; this plugin is a wrapper for the free and open-source [Editoria11y library](https://github.com/itmaybejj/editoria11y).
 
## The tests

* Text alternatives
    * Images with no alt text
    * Images with a filename as alt text
    * Images with very long alt text
    * Alt text that contains redundant text like “image of” or “photo of”
    * Images in links with alt text that appears to be describing the image instead of the link destination
    * Embedded visualizations that usually require a text alternative
* Meaningful links
    * Links with no text
    * Links titled with a filename
    * Links only titled with generic text: “click here,” “learn more,” “download,” etc.
    * Links that open in a new window without warning
* Document outline and structure
    * Skipped heading levels
    * Empty headings
    * Very long headings
    * Suspiciously short blockquotes that may actually be headings
    * All-bold paragraphs with no punctuation that may actually be headings
    * Suspicious formatting that should probably be converted to a list (asterisks and incrementing numbers/letters prefixes)
    * Tables without headers
    * Empty table header cells
    * Tables with document headers ("Header 3") instead of table headers
* General quality assurance
    * LARGE QUANTITIES OF CAPS LOCK TEXT
    * Links to PDFs and other documents, reminding the user to test the download for accessibility or provide an alternate, accessible format
    * Video embeds, reminding the user to add closed captions
    * Audio embeds, reminding the user to provide a transcript
    * Social media embeds, reminding the user to provide alt attributes

## The admin experience

* Filterable reports let you explore recent issues, which pages have the most issues, which issues are most common, and which issues have been dismissed. These populate and update as content is viewed and updated.
* Various settings are available to constrain checks to specific parts of the page and tweak the sensitivity of several tests.

## Compared to other checkers
### Sa11y
Editoria11y is most similar to [Sa11y](https://wordpress.org/plugins/sa11y/) -- in fact, Editoria11y began as a Sa11y fork, and they are developed in parallel, so new features in one usually appears in the other within a few months. 

Both are inline checkers aimed at content authors. Try both; the look and feel is a bit different.

Feature-wise, key philosophical distinctions are...
* Editoria11y synchronizes its information with your WordPress database, meaning:
    * Sitewide reporting can be reviewed on a dashboard.
    * Alerts "marked as OK" are dismissed for all authors.
* Sa11y imports several additional test libraries, adding legibility scoring and color contrast tests. 
* Sa11y's settings can be adjusted by the end-user. Editoria11y is centrally managed by the site admin.

### Crawling and auditing tools
Both Editoria11y and Sa11y differ from manual accessibility testing tools and site-wide crawlers in that they:
* Are generally simpler to use. Results are always highlighted inline: "This heading skipped a level and probably should be an H2 rather than an H4" is a very different experience from "Incorrect heading order somewhere on this page. Affected code `<h4>Example</h4>`. Go find it yourself."
* Eschew obfuscation and techno-legal jargon. They explain what the issue is in plain language, with a simple explanation of how to fix it. "This image needs alternative text" requires much less training to understand than "WCAG 1.1.1 Level A: Non-text Content."
* Exclude code-level alerts. Code testing is critically important, but non-technical authors do not know what to do with alerts for "broken ARIA references" or "tab order modified."

This is not to denigrate developer-focused tools; as accessibility developers, we both recommend them and rely on them on a daily basis. It is to say: on an average site, automatic inline checkers meet a different need.

## Credit

Editoria11y is maintained by Princeton University's [Web Development Services](https://wds.princeton.edu/) team:
* [John Jameson](https://github.com/itmaybejj)
* [Jason Partyka](https://github.com/jasonpartyka)
* [Brian Osborne](https://github.com/bkosborne)

Editoria11y began as a fork of the Toronto Metropolitan University's [Sa11y Accessibility Checker](https://sa11y.netlify.app/), and our teams regularly pass new code and ideas back and forth.


== Installation ==

Editoria11y's default configuration should work fine out of the box on most themes.

If you want to customize the checker, visit the plugin settings page and:

* Select a color scheme that looks nice for your site.
* Limit the checker to author-editable parts of the page (e.g, `main, #footer-widget`) if it is throwing alerts on things only you can modify.
* If you notice false positives, use the "Skip over these elements" setting to suppress them.
* Tell us how it went! This plugin and its base library are both under active development. Ideally send bug reports and feature requests through the [GitHub issue queue](https://github.com/itmaybejj/editoria11y-wp/issues).

If you are a developer, note that the library dispatches JavaScript events at key moments (scan finishes, panel opens, tooltip opens or shuts...), allowing you to attach custom functionality. JavaScript on sites running Editoria11y can use these events to [automatically open accordion widgets](https://github.com/itmaybejj/editoria11y/blob/main/README.md#dealing-with-alerts-on-hidden-or-size-constrained-content) if they contain hidden alerts, to disable "sticky" site menus when the panel opens, or even to sync the count and type of alerts found to third-party analytics platforms.

== Screenshots ==
1. Todo

== Changelog ==

= 1.0.0 =
* Initial release.