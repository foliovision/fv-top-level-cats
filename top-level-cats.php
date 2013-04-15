<?php
/*
Plugin Name: FV Top Level Categories
Plugin URI: http://foliovision.com/seo-tools/wordpress/plugins/fv-top-level-categories
Description: Removes the prefix from the URL for a category. For instance, if your old category link was <code>/category/catname</code> it will now be <code>/catname</code>
Version: 1.4
Author: Foliovision
Author URI: http://foliovision.com/  
*/

register_activation_hook(__FILE__,'fv_top_level_categories_refresh_rules');

add_action('created_category','fv_top_level_categories_refresh_rules');
add_action('edited_category','fv_top_level_categories_refresh_rules');
add_action('delete_category','fv_top_level_categories_refresh_rules');

function fv_top_level_categories_refresh_rules() {
	add_option('fv_top_level_categories_rewrite_rules_flush', 'true');
}
register_deactivation_hook(__FILE__,'fv_top_level_categories_deactivate');

function fv_top_level_categories_deactivate() {
	remove_filter('category_rewrite_rules', 'fv_top_level_categories_refresh_rules'); // We don't want to insert our custom rules again
	delete_option('fv_top_level_categories_rewrite_rules_flush');
}

// Remove category base
add_action('init', 'fv_top_level_categories_permastruct');
function fv_top_level_categories_permastruct() {
	global $wp_rewrite;
	$wp_rewrite->extra_permastructs['category'][0] = '%category%';
	
	if (get_option('fv_top_level_categories_rewrite_rules_flush') == 'true') {
		flush_rewrite_rules();
		delete_option('fv_top_level_categories_rewrite_rules_flush');
	}	
}

// Add our custom category rewrite rules
add_filter('category_rewrite_rules', 'fv_top_level_categories_rewrite_rules');
function fv_top_level_categories_rewrite_rules($category_rewrite) {
	//var_dump($category_rewrite); // For Debugging
	
	///  First we need to get full URLs of our pages
	$pages = get_pages( 'number=0' );
	$pages_urls = array();
  foreach( $pages AS $pages_item ) {
    $pages_urls[] = trim( str_replace( get_bloginfo( 'url' ), '', get_permalink( $pages_item->ID ) ), '/' );
  }
  ///
	
	$category_rewrite=array();
	$categories=get_categories(array('hide_empty'=>false));
	foreach($categories as $category) {
		$category_nicename = $category->slug;
		if ( $category->parent == $category->cat_ID ) // recursive recursion
			$category->parent = 0;
		elseif ($category->parent != 0 )
			$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
		
		/// Let's check if any of the category full URLs matches any of the pages
		if( in_array( $category_nicename, $pages_urls ) ) {
		  continue;
		}
		///
		
		
		$category_rewrite['('.$category_nicename.')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$category_rewrite['('.$category_nicename.')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$category_rewrite['('.$category_nicename.')/?$'] = 'index.php?category_name=$matches[1]';
	}
	// Redirect support from Old Category Base
	global $wp_rewrite;
	$old_category_base = get_option('category_base') ? get_option('category_base') : 'category';
	$old_category_base = trim($old_category_base, '/');
	$category_rewrite[$old_category_base.'/(.*)$'] = 'index.php?category_redirect=$matches[1]';
	
	//var_dump($category_rewrite); // For Debugging
	return $category_rewrite;
}

// Add 'category_redirect' query variable
add_filter('query_vars', 'fv_top_level_categories_query_vars');
function fv_top_level_categories_query_vars($public_query_vars) {
	$public_query_vars[] = 'category_redirect';
	return $public_query_vars;
}

// Redirect if 'category_redirect' is set
add_filter('request', 'fv_top_level_categories_request');
function fv_top_level_categories_request($query_vars) {
	//print_r($query_vars); // For Debugging
	if(isset($query_vars['category_redirect'])) {
		$catlink = trailingslashit(get_option( 'home' )) . user_trailingslashit( $query_vars['category_redirect'], 'category' );
		status_header(301);
		header("Location: $catlink");
		exit();
	}
	return $query_vars;
}

add_filter('category_link', 'top_level_cats_remove_cat_base');
function top_level_cats_remove_cat_base($link) {
	$category_base = get_option('category_base');

	// WP uses "category/" as the default
	if ($category_base == '')
		$category_base = 'category';

	// Remove initial slash, if there is one (we remove the trailing slash in the regex replacement and don't want to end up short a slash)
	if (substr($category_base, 0, 1) == '/')
		$category_base = substr($category_base, 1);

	$category_base .= '/';

	return preg_replace('|' . $category_base . '|', '', $link, 1);
}

?>