<?php
/*
Plugin Name: FV Top Level Categories
Plugin URI: http://foliovision.com/seo-tools/wordpress/plugins/fv-top-level-categories
Description: Removes the prefix from the URL for a category. For instance, if your old category link was <code>/category/catname</code> it will now be <code>/catname</code>
Version: 1.8.3
Author: Foliovision
Author URI: http://foliovision.com/  
Text Domain: fv_tlc
Domain Path: /languages/
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
add_action('init', 'fv_top_level_categories_permastruct',999999);
function fv_top_level_categories_permastruct() {
	global $wp_rewrite;
	$wp_rewrite->extra_permastructs['category'][0] = '%category%';
	
	$bFound = false;
	$aRules = get_option('rewrite_rules');
	if( $aRules && count($aRules) > 0 ) {
		foreach( $aRules AS $key => $value ) {
			if( $key == 'fv-top-level-cat-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ' ) {
				$bFound = true;
				break;
			}
		}
	}	
	
	if( !$bFound || get_option('fv_top_level_categories_rewrite_rules_flush') == 'true') {
		flush_rewrite_rules();
		delete_option('fv_top_level_categories_rewrite_rules_flush');
	}	
}

// Add our custom category rewrite rules
add_filter('category_rewrite_rules', 'fv_top_level_categories_rewrite_rules');
function fv_top_level_categories_rewrite_rules($category_rewrite) {
	//var_dump($category_rewrite); // For Debugging
	
  global $sitepress;
  if( isset($sitepress) && $sitepress ) {
    $sitepress->switch_lang('all');
  }
	///  First we need to get full URLs of our pages
	$pages = get_pages( 'number=0' );
	$pages_urls = array();
  foreach( $pages AS $pages_item ) {
    $pages_urls[] = trim( str_replace( get_bloginfo( 'url' ), '', get_permalink( $pages_item->ID ) ), '/' );
  }
  ///
	global $wp_rewrite;
		
	$category_rewrite=array();
  
  $categories=get_categories(array('hide_empty'=>false));
  
  if( isset($sitepress) && $sitepress ) {
    $sitepress->switch_lang(ICL_LANGUAGE_CODE);
  }
  
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
		$category_rewrite['('.$category_nicename.')/'. $wp_rewrite->pagination_base .'/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$category_rewrite['('.$category_nicename.')/?$'] = 'index.php?category_name=$matches[1]';
	}
	// Redirect support from Old Category Base
	$old_category_base = get_option('category_base') ? get_option('category_base') : 'category';
	$old_category_base = trim($old_category_base, '/');
	$category_rewrite[$old_category_base.'/(.*)$'] = 'index.php?category_redirect=$matches[1]';
	
	$category_rewrite['fv-top-level-cat-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ'] = 'index.php?fv-top-level-cat-tweaks-detector-235hnguh9hq46j0909iasn0zzdfsAJ=1';
	
	//var_dump($category_rewrite); // For Debugging
	return $category_rewrite;
}

//Redirect to TL parent categ, if "Only use top-level categories in URLs." is on
add_filter('template_redirect', 'fv_top_level_categories_tlc_redirect', 999, 2);
function fv_top_level_categories_tlc_redirect( $link ) {
  if( FV_Top_Level_Cats::is_top_level_only() && is_single() ) {
    global $wp_query;
    $requested_url  = is_ssl() ? 'https://' : 'http://';
		$requested_url .= $_SERVER['HTTP_HOST'];
		$requested_url .= $_SERVER['REQUEST_URI'];
    
    $real_permalink = get_permalink($wp_query->queried_object_id);
    
    if( $real_permalink && FALSE === stripos($requested_url, $real_permalink) ) {
      $bMached = preg_match('~/([^/:]+/?)$~',$real_permalink, $end_of_permalink);
      if( $bMached && preg_match('~'.$end_of_permalink[1].'(.+)$~', $requested_url, $end_of_url) )
        wp_redirect( $real_permalink . $end_of_url[1], 301 );
      else
        wp_redirect( $real_permalink, 301 );
      die();    
    }
  }
  
  return $link;
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
  if( !FV_Top_Level_Cats::is_category_permalinks() || !FV_Top_Level_Cats::is_category_restriction() ) {
    return $cat;  
  }
  
  $aArgs = func_get_args();

  $aAllowedCats = FV_Top_Level_Cats::get_allowed_cats();
  if( !count($aAllowedCats) ) {
    return $cat;
  }
  
  //  check if the main category is allowed
  if( in_array( $cat->term_id, $aAllowedCats ) ) {
    return $cat;
  }

  //  check if any of the other categories is allowed!
  $isOk = false; 
  foreach( $aArgs[1] AS $objCat ) {
    if( in_array( $objCat->term_id, $aAllowedCats ) ) {
      $isOk = true;
      $cat = $objCat;
    }
  }
  
  //  check if any of the parent categories is allowed
  if( !$isOk ) {
    foreach( $aArgs[1] AS $objCat ) {      
      while( $objCat->parent != 0 ) {
        $objCat = get_term_by( 'id', $objCat->parent, 'category' );
      }      
      if( in_array( $objCat->term_id, $aAllowedCats ) ) {
        $isOk = true;
        $cat = $objCat;
      }
    }    
  }
  
  return $cat;
}
add_filter( 'post_link_category', 'fv_top_level_cats_post_link_category_restrict', 200, 3 );




function fv_top_level_category_filter( $aCategories ) {
  if( class_exists('FV_Top_Level_Cats') && method_exists('FV_Top_Level_Cats','get_allowed_cats') ) {
    
    $aAllowedCats = FV_Top_Level_Cats::get_allowed_cats();
    if( !count($aAllowedCats) ) {
      return $aCategories;
    }
    
    //  check if the main category is allowed
    foreach( $aCategories AS $objCat ) {
      if( in_array( $objCat->term_id, $aAllowedCats ) ) {
        return array($objCat);
      }
    }
    
    foreach( $aCategories AS $objCat ) {      
      while( $objCat->parent != 0 ) {
        $objCat = get_term_by( 'id', $objCat->parent, 'category' );
      }      
      if( in_array( $objCat->term_id, $aAllowedCats ) ) {
        return array($objCat);
      }
    }       
  }
  return $aCategories;
}





function fv_top_level_category( $separator = '', $parents = '',  $post_id  = false ) {
  add_filter( 'get_the_categories', 'fv_top_level_category_filter' );
  the_category( $separator, $parents, $post_id );
  remove_filter( 'get_the_categories', 'fv_top_level_category_filter' );
}
add_action( 'fv_top_level_category', 'fv_top_level_category', 10, 3 );




class FV_Top_Level_Cats {

  var $enabled;
  var $default_form_code;
  var $default_form_css;

  
  
  
  public function __construct() {
    add_action( 'admin_menu', array($this, 'admin_menu') );
    add_action('init',array($this,'load_languages'));
  }
  
  
  
  
  function admin_menu() {
    add_options_page( __('FV Top Level Categories','fv_tlc'), __('FV Top Level Categories','fv_tlc'), 'manage_options', 'fv_top_level_cats', array($this, 'options_panel') );
  }
  
  
  
  
  public static function get_allowed_cats() {
    $options = get_option( 'fv_top_level_cats' );
    if( isset($options['category-allow']) ) {
      return $options['category-allow'];
    } else {
      return false;
    }    
  }
  
  
  
  
  public static function is_category_permalinks() {
    $sPermalinks = get_option( 'permalink_structure' );
    if( stripos($sPermalinks, '%category%/') !== false ) {
      return true;
    } else {
      return false;
    }
  }
  
  
  
    
  function load_languages(){	
	// Localization
        load_plugin_textdomain('fv_tlc', false, dirname(plugin_basename(__FILE__)) . "/languages");	
  }
  
  
  
  
  public static function is_top_level_only() {
    $options = get_option( 'fv_top_level_cats' );
    if( isset($options['top-level-only']) && $options['top-level-only'] ) {
      return true;
    } else {
      return false;
    }
  }
  
  
  
  
  public static function is_category_restriction() {
    $options = get_option( 'fv_top_level_cats' );
    if( isset($options['category-allow-enabled']) && $options['category-allow-enabled'] ) {
      return true;
    } else {
      return false;
    }
  }  
  
  
  
	
  function options_panel() {

    if (!empty($_POST)) :
    
      check_admin_referer('fv_top_level_cats');
      
      if( isset($_POST['fv_top_level_cats_submit'] ) ) :
        
        if(isset($_POST['fv_top_level_cats'])) {
          $options = get_option( 'fv_top_level_cats', array() );
        }
        
        if(isset($_POST['post_category'])) {
          $options['category-allow'] = $_POST['post_category'];
        }

        if(isset($_POST['top-level-only'])) {
          $options['top-level-only'] = ( $_POST['top-level-only'] ) ? true : false;
        }

        if(isset($_POST['category-allow-enabled'])){
          $options['category-allow-enabled'] = ( $_POST['category-allow-enabled'] ) ? true : false;
        }

        if(isset($options)) {
          update_option( 'fv_top_level_cats', $options );
        }
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
    <h2><?php _e('FV Top Level Categories','fv_tlc'); ?></h2>
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
          <?php _e('Adjust categories in your post URLs','fv_tlc') ?>
          </h3>
          <div class="inside">
            <table class="form-table">
              <tr>
                <td>
                  <label for="top-level-only">

                    <input type="checkbox" name="top-level-only" id="top-level-only" value="1" <?php if( isset($options['top-level-only'] )) { if( $options['top-level-only'] ) echo 'checked="checked"'; }?> />
                    <?php _e('Only use top-level categories in URLs.','fv_tlc') ; ?>
                  </label>
                </td>
              </tr>                
              <tr>
                <td>
                  <label for="category-allow-enabled">
                    <input type="checkbox" name="category-allow-enabled" id="category-allow-enabled" value="1" <?php if(isset($options['category-allow-enabled'])) { if( $options['category-allow-enabled'] ) echo 'checked="checked"'; }?> />
                    <?php _e('Only allow following categories in URLs:','fv_tlc' );?>
                  </label>                  
                  <blockquote>
                    <ul id="category-allow"> <?php  
                        if( isset($options['category-allow']) ) {
                            $descendants_and_self = $options['category-allow'];
                        }else{
                            $descendants_and_self = 0; //wp default value
                        }

                        global $sitepress;
                        if( isset($sitepress) && $sitepress ) {
                          $sitepress->switch_lang('all');
                        }
                        
                        wp_category_checklist( 0, 0,  $descendants_and_self, false, null, false );
                        
                        if( isset($sitepress) && $sitepress ) {
                          $sitepress->switch_lang(ICL_LANGUAGE_CODE);
                        }

                      ?>
                    </ul>
                  </blockquote>
                </td>
              </tr>                                       
            </table>
            <p>
              <input type="submit" name="fv_top_level_cats_submit" class="button-primary" value="<?php _e('Save Changes','fv_tlc'); ?>" />
            </p>
          </div>
        </div>
        <p><?php _e('Are you having any problems or questions? Use our <a target="_blank" href="http://foliovision.com/support/fv-top-level-categories/">support forums</a>.','fv_tlc'); ?></p>
      </div>
         
    </form>
  <?php else : ?>
    <p><?php _e('Since you are not using %category% in your post permalinks, there is nothing to adjust.','fv_tlc'); ?></p>
  <?php endif; ?>

</div>


<?php
  }
  
  
}

$FV_Top_Level_Cats = new FV_Top_Level_Cats;

// Add settings link on plugin page
function fv_top_level_categories_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=fv_top_level_cats">' . __('Settings','fv_tlc') . '</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'fv_top_level_categories_settings_link' );
