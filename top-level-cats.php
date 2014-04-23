<?php
/*
Plugin Name: FV Top Level Categories
Plugin URI: http://foliovision.com/seo-tools/wordpress/plugins/fv-top-level-categories
Description: Removes the prefix from the URL for a category. For instance, if your old category link was <code>/category/catname</code> it will now be <code>/catname</code>
Version: 1.4.9
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




function fv_top_level_cats_post_link_category_top_level_only( $cat ) {
  if( !FV_Top_Level_Cats::is_category_permalinks() ) {
    return $cat;  
  } 
 
  while( FV_Top_Level_Cats::is_top_level_only() && $cat->parent != 0 ) {
    $cat = get_term_by( 'id', $cat->parent, 'category' );
  }
  
  return $cat;
}
add_filter( 'post_link_category', 'fv_top_level_cats_post_link_category_top_level_only', 201, 3 );




function fv_top_level_cats_post_link_category_restrict( $cat ) {
  if( !FV_Top_Level_Cats::is_category_permalinks() ) {
    return $cat;  
  }
  
  $aArgs = func_get_args();

  $aAllowedCats = FV_Top_Level_Cats::get_allowed_cats();
 
  if( !count($aAllowedCats) ) {
    return $cat;
  }

  $isOk = false; 
  foreach( $aArgs[1] AS $objCat ) {
    if( in_array( $objCat->term_id, $aAllowedCats ) ) {
      $isOk = true;
      $cat = $objCat;
    }
  }
  
  return $cat;
}
add_filter( 'post_link_category', 'fv_top_level_cats_post_link_category_restrict', 200, 3 );




class FV_Top_Level_Cats {

  var $enabled;
  var $default_form_code;
  var $default_form_css;

  
  
  
  public function __construct() {
    add_action( 'admin_menu', array($this, 'admin_menu') );
  }
  

  
  
  function admin_menu() {
    add_options_page( 'FV Top Level Categories', 'FV Top Level Categories', 'manage_options', 'fv_top_level_cats', array($this, 'options_panel') );
  }
  
  
  
  
  function get_allowed_cats() {
    $options = get_option( 'fv_top_level_cats' );
    if( isset($options['category-allow']) ) {
      return $options['category-allow'];
    } else {
      return false;
    }    
  }
  
  
  
  
  function is_category_permalinks() {
    $sPermalinks = get_option( 'permalink_structure' );
    if( stripos($sPermalinks, '%category%/') !== false ) {
      return true;
    } else {
      return false;
    }
  }
  
  
  
  
  function is_top_level_only() {
    $options = get_option( 'fv_top_level_cats' );
    if( isset($options['top-level-only']) && $options['top-level-only'] ) {
      return true;
    } else {
      return false;
    }
  }
  
  
  
	
  function options_panel() {

    if (!empty($_POST)) :
    
      check_admin_referer('fv_top_level_cats');
      
      if( isset($_POST['fv_top_level_cats_submit'] ) ) :
        $options = get_option( 'fv_top_level_cats', array() );

        $options['category-allow'] = $_POST['post_category'];
        $options['top-level-only'] = ( $_POST['top-level-only'] ) ? true : false;
      
        update_option( 'fv_top_level_cats', $options );
?>
    <div id="message" class="updated fade">
      <p>
        <strong>
          Settings saved
        </strong>
      </p>
    </div>
<?php
      endif; // fv_top_level_cats_submit
                  
    endif;
    
    $options = get_option( 'fv_top_level_cats' );
?>

<div class="wrap">
  <div style="position: absolute; right: 20px; margin-top: 5px">
  <a href="http://foliovision.com/wordpress/plugins/fv-top-level-categories" target="_blank" title="Documentation"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
  </div>
  <div>
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>FV Top Level Categories</h2>
  </div>
  
  <?php if( $this->is_category_permalinks() ) : ?>
    <style>
      #category-allow ul.children { margin-left: 20px; }
    </style>
    <form method="post" action="">
      <?php wp_nonce_field('fv_top_level_cats') ?>
      <div id="poststuff" class="ui-sortable">
        <div class="postbox">
          <h3>
          <?php _e('Adjust categories in your post URLs') ?>
          </h3>
          <div class="inside">
            <table class="form-table">
              <tr>
                <td>
                  <p>Only allow following categories in URLs:</p>
                  <ul id="category-allow"><?php wp_category_checklist( 0, 0, $options['category-allow'], false, null, false ); ?></ul>
                </td>
              </tr>               
              <tr>
                <td>
                  <label for="top-level-only">
                    <input type="checkbox" name="top-level-only" id="top-level-only" value="1" <?php if( $options['top-level-only'] ) echo 'checked="checked"'; ?> />
                    Only use top-level catogories in URLs.
                  </label>
                </td>
              </tr>                          
            </table>
            <p>
              <input type="submit" name="fv_top_level_cats_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>
          </div>
        </div>
        <p><?php echo __('Are you having any problems or questions? Use our <a target="_blank" href="http://foliovision.com/support/fv-feedburner-replacement/">support forums</a>.'); ?></p>
      </div>
         
    </form>
  <?php else : ?>
    <p>Since you are not using %category% in your post permalinks, there is nothing to adjust.</p>
  <?php endif; ?>

</div>


<?php
  }
  
  
}


$FV_Top_Level_Cats = new FV_Top_Level_Cats;