=== FV Top Level Categories ===
Contributors: FolioVision
Donate link: http://foliovision.com/seo-tools/wordpress/plugins/fv-top-level-categories
Tags: categories, permalink
Requires at least: 3.2.1
Tested up to: 3.4.1
Stable tag: trunk

This is a fix of Top Level Categories plugin for Wordpress 3.1. and above.

== Description ==

This is a fix of Top Level Category plugin for Wordpress 3.1. and further versions. It's purpose is to provide the same behavior as the original plugin, but in new Wordpress versions.

The Top Level Categories plugin allows you to remove the prefix before the URL to your category page. For example, instead of http://foliovision.com/category/work, you can use http://foliovision.com/work for the address of "work" category. WordPress doesn't allow you to have a blank prefix for categories (they insert `category/` before the name), this plugin works around that restriction.

This plugin works also if you have a permalink structure like %postname% or %category%/%postname% -- this wasn't possible in the original version. However, this feature might not work properly for child categories at this point. Test carefully!

[Support](http://foliovision.com/support/fv-top-level-categories/)

== Installation ==

1. Copy the `top-level-cats.php` file into your `wp-content/plugins` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. That's it! :)

== Known Issues / Bugs ==

1. Issue with paging and feed URLs when using `%postname` or `%category%/%postname%` permalink structure should be fixed.

== Frequently Asked Questions ==

= How do I automatically redirect people from the old category permalink? =

We recommend that you use the [Redirection](http://wordpress.org/extend/plugins/redirection/) plugin and add your old an new category links, or use a Regex redirection rule. Make sure you change Tools -> Redirection -> Options -> URL Monitoring to "Don't monitor", as there is a [bug](http://wordpress.org/support/topic/plugin-redirection-my-homepage-is-being-redirected-to-a-page-need-some-help) in that feature (also in latest current version 2.2.5) - not related to FV Top Level Categories.

= I'm having issues with child categories when I'm using /%category%/%postname% permalink structure =

Make sure your categories have unique slugs - watch out for pages with the same slugs. Normally Wordpress uses the category prefix to distinguish page from a category, but with this plugin you need to make sure the slugs are unique, otherwise some pages might turn up instead of categories.

== Uninstall ==

1. Deactivate the plugin
1. That's it! :)

== Changelog ==

= 1.4 =
* fix for Wordpress 3.4.1 - category prefix was part of the generated URLs

= 1.3 =
* for for flushing of rewrite rules on plugin activation in WP 3.3

= 1.2 =
* fix for WP 3.3
* fix for /%categor%/%post-name% permalink structure

= 1.1.3 =
* fix for deeper nested pages

= 1.1.2 =
* fix for /category/child-category redirecting to /child-category page

= 1.1.1 =
* fix for deeper nested categories

= 1.1 =
* fix for WP 3.1

= 1.0.1 =
* original version
