fv-top-level-cats
=================

This is a fix of Top Level Categories plugin for Wordpress 3.1. and above.
=======
Removes the prefix from the URL for a category. For instance, if your old category link was &lt;code>/category/catname&lt;/code> it will now be &lt;code>/catname&lt;/code>

## Testing

* Create a category called "computers"

  1. Create second category called "apple"

  2. Make "apple" a child of "computers"

  3. Create a post called "Macbook Pro"

  4. Assign it to "apple" category

  5. Category URL should be http://example.com/computers/apple/

  6. If you navigate to http://example.com/category/computers/apple/ you should be redirected to http://example.com/computers/apple/

* Activate wp-admin -> Settings -> FV Top Level Categories -> Only use top-level categories in URLs.

  1. The post URL should now be changed from http://localhost/wordpress/computers/apple/macbook-pro/ to http://localhost/wordpress/computers/macbook-pro/

* Activate wp-admin -> Settings -> FV Top Level Categories -> Only allow following categories in URLs

  1. Add a "Review" category to your "Macbook Pro" post.

  2. In wp-admin -> Settings -> FV Top Level Categories -> Only allow following categories in URLs pick the "Review" category, but skip "Computers"

  2. The post URL should now be changed from http://localhost/wordpress/computers/macbook-pro/ to http://localhost/wordpress/review/macbook-pro/