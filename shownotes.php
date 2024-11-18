<?php


/**
 * @package Shownotes
 * @version 0.5.7
 */

/*
Plugin Name: Shownotes
Plugin URI: http://shownot.es/wp-plugin/
Description: Convert OSF-Shownotes to HTML for your Podcast
Author: Simon Waldherr
Version: 0.5.7
Author URI: http://waldherr.eu
License: MIT License
*/

include_once 'snsettings.php';
include_once 'OSFphp/osf.php';
include_once 'micromarkdown/micromarkdown.php';
$shownotes_options = get_option('shownotes_options');
$shownotes_cache = array();

function shownotesshortcode_add_styles() {
  global $shownotes_options;
  if (!is_feed()) {
    if (!isset($shownotes_options['css_id'])) {
      return false;
    }
    if ($shownotes_options['css_id'] == '0') {
      return false;
    }
    $css_styles = array(
      '',
      'style_one',
      'style_two',
      'style_three',
      'style_four',
      'style_five'
    );
    wp_enqueue_style('shownotesstyle', plugins_url('static/' . $css_styles[$shownotes_options['css_id']] . '.css', __FILE__), array(), '0.5.7');
  }
}

add_action('wp_print_styles', 'shownotesshortcode_add_styles');

function add_shownotes_textarea($post) {
  global $shownotes_options;
  $post_id = @get_the_ID();
  if ($post_id == '') {
    return;
  }
  if (isset($post_id)) {
    $shownotes = get_post_meta($post_id, '_shownotes', true);
    if (@get_post_meta($post_id, '_shownotesname', true) != '') {
      $shownotesname = get_post_meta($post_id, '_shownotesname', true);
    } else {
      $shownotesname = '';
    }

    if ($shownotes == '') {
      $shownotes = get_post_meta($post_id, 'shownotes', true);
    }
  } else {
    $shownotes = '';
  }
  $baseurlstring = '';
  $import_podcastname = false;
  if (isset($shownotes_options['import_podcastname'])) {
    if (trim($shownotes_options['import_podcastname']) != '') {
      $import_podcastname = trim($shownotes_options['import_podcastname']);
    }
  }
  if ($import_podcastname == false) {
    $baseurlstring = '<input type="text" id="importId" name="shownotesname" class="form-input-tip" size="16" autocomplete="off" value=""> <input id="loadShownotes" type="button" class="button" onclick="importShownotes(document.getElementById(\'shownotes\'), document.getElementById(\'importId\').value, \''.plugins_url('' , __FILE__ ).'/api.php?proxytype=pad&proxyvalue=$$$\')" value="Import">';
  } else {
    $baseurlstring = '<select id="importId" name="shownotesname" size="1"></select> <input id="loadShownotes" type="button" class="button" onclick="importShownotes(document.getElementById(\'shownotes\'), document.getElementById(\'importId\').value, \''.plugins_url('' , __FILE__ ).'/api.php?proxytype=pad&proxyvalue=$$$\')" value="Import"><script>getPadList(document.getElementById(\'importId\'), \''.plugins_url('' , __FILE__ ).'\',\'' . $import_podcastname . '\')</script>';
  }
  echo '<div id="add_shownotes" class="shownotesdiv"><script>var shownotesname = \'' . $shownotesname . '\';</script><p>You can use the following shortcodes in the textarea above: <code>[shownotes]</code>, <code>[shownotes mode=&quot;block&quot;]</code>, <code>[shownotes mode=&quot;shownoter&quot;]</code>, <code>[shownotes mode=&quot;podcaster&quot;]</code>, ...</p><p>In the textarea below you can enter your <a href="http://shownotes.github.io/OSF-in-a-Nutshell/">OSF Show Notes</a>.</p><p id="snstatus"></p><p><textarea id="shownotes" name="shownotes" style="height:280px" class="large-text" onKeyUp="analyzeShownotes();">' . $shownotes . '</textarea></p> <p>ShowPad Import: ' . $baseurlstring . ' <br/><!--<br/> Preview:
<input type="submit" class="button" onclick="previewPopup(document.getElementById(\'shownotes\'), \'html\', false, \''.plugins_url('' , __FILE__ ).'\'); return false;" value="HTML">
<input type="submit" class="button" onclick="previewPopup(document.getElementById(\'shownotes\'), \'chapter\', false, \''.plugins_url('' , __FILE__ ).'\'); return false;" value="Chapter"> <input type="submit" class="button" onclick="previewPopup(document.getElementById(\'shownotes\'), \'audacity\', true, \''.plugins_url('' , __FILE__ ).'\'); return false;" value="Audacity"> <input type="submit" class="button" onclick="previewPopup(document.getElementById(\'shownotes\'), \'reaper\', true, \''.plugins_url('' , __FILE__ ).'\'); return false;" value="Reaper"> &#124; Download:
<input type="submit" class="button" onclick="previewPopup(document.getElementById(\'shownotes\'), \'chapter\', true, \''.plugins_url('' , __FILE__ ).'\'); return false;" value="Chapter"> --></p></div>';
}

function save_shownotes() {
  $post_id = @get_the_ID();
  if ($post_id == '') {
    return;
  }
  $old = get_post_meta($post_id, '_shownotes', true);
  if (isset($_POST['shownotes'])) {
    $new = $_POST['shownotes'];
    $name = @$_POST['shownotesname'];
  } else {
    return;
  }
  $shownotes = $old;
  if ($new && $new != $old) {
    update_post_meta($post_id, '_shownotes', $new);
    update_post_meta($post_id, '_shownotesname', $name);
    delete_post_meta($post_id, 'shownotes');
    $shownotes = $new;
  } elseif ('' == $new && $old) {
    delete_post_meta($post_id, '_shownotes', $old);
  }
}

add_action('add_meta_boxes', function() {
  $screens = array(
    'post',
    'page',
    'podcast'
  );
  foreach ($screens as $screen) {
    add_meta_box('shownotesdiv-', __('Shownotes', 'podlove'), 'add_shownotes_textarea', $screen, 'advanced', 'default');
  }
});

add_action('save_post', 'save_shownotes');

function osf_shownotes_shortcode($atts, $content = '') {
  global $shownotes_options;
  global $shownotes_cache;
  $export = '';
  $post_id = get_the_ID();
  $shownotes = get_post_meta($post_id, '_shownotes', true);
  if ($shownotes == '') {
    $shownotes = get_post_meta($post_id, 'shownotes', true);
  }
  if (isset($shownotes_options['main_tags_mode'])) {
    $tags_mode = trim($shownotes_options['main_tags_mode']);
  } else {
    $tags_mode = 'include';
  }
  if (isset($shownotes_options['main_tags'])) {
    $default_tags = trim($shownotes_options['main_tags']);
  } else {
    $default_tags = '';
  }
  if (isset($shownotes_options['main_tags'])) {
    $feed_tags = trim($shownotes_options['main_tags']);
  } else {
    $feed_tags = '';
  }
  extract(shortcode_atts(array(
    'template'  => $shownotes_options['main_mode'],
    'mode'      => $shownotes_options['main_mode'],
    'tags_mode' => $tags_mode,
    'tags'      => $default_tags,
    'feedtags'  => $feed_tags
  ), $atts));
  if (($content !== '') || ($shownotes)) {
    if (isset($shownotes_options['affiliate_amazon']) && $shownotes_options['affiliate_amazon'] != '') {
      $amazon = $shownotes_options['affiliate_amazon'];
    } else {
      $amazon = '';
      if (rand(0,10) >= 8) {
        //support the development <3
        $amazon = 'shownot.es-21';
      }
    }
    if (isset($shownotes_options['affiliate_thomann']) && $shownotes_options['affiliate_thomann'] != '') {
      $thomann = $shownotes_options['affiliate_thomann'];
    } else {
      $thomann = '';
    }
    if (isset($shownotes_options['affiliate_tradedoubler']) && $shownotes_options['affiliate_tradedoubler'] != '') {
      $tradedoubler = $shownotes_options['affiliate_tradedoubler'];
    } else {
      $tradedoubler = '';
    }
    $fullmode = 'false';
    if (is_feed()) {
      $tags = $feedtags;
    }
    if ($tags == '') {
      $fullmode = 'true';
      $fullint = 2;
      $tags = explode(' ', 'chapter section spoiler topic embed video audio image shopping glossary source app title quote link podcast news');
    } else {
      $fullint = 1;
      $tags = explode(' ', $tags);
    }
    if (!isset($shownotes_options['main_untagged'])) {
      $fullint = 2;
      $fullmode = 'true';
    } else {
      $fullint = 1;
      $fullmode = 'false';
    }
    $data = array(
      'amazon'       => $amazon,
      'thomann'      => $thomann,
      'tradedoubler' => $tradedoubler,
      'fullmode'     => $fullmode,
      'tagsmode'     => $tags_mode,
      'tags'         => $tags
    );
    //undo fucking wordpress shortcode cripple shit
    if ($content !== '') {
      $shownotesString = htmlspecialchars_decode(str_replace('<br />', '', str_replace('<p>', '', str_replace('</p>', '', $content))));
    } else {
      $shownotesString = "\n" . $shownotes . "\n";
    }
    //parse shortcode as osf string to html
    if ($template !== $shownotes_options['main_mode']) {
      $mode = $template;
    }

    if ($mode == 'block') {
      $mode = 'block style';
    }
    if ($mode == 'list') {
      $mode = 'list style';
    }
    if ($mode == 'osf') {
      $mode = 'clean osf';
    }

    if (isset($shownotes_cache[$post_id])) {
      $shownotesArray = $shownotes_cache[$post_id];
    } else {
      $shownotesArray = osf_parser($shownotesString, $data);
    }

    if (($mode == 'block style') || ($mode == 'button style')) {
      if (isset($shownotesArray['export']) && is_array($shownotesArray['export'])) {
          $export = osf_export_block($shownotesArray['export'], $fullint, $mode);
      } else {
          $export = ''; // Fallback für fehlende 'export'-Daten
      }
  } elseif ($mode == 'list style') {
      if (isset($shownotesArray['export']) && is_array($shownotesArray['export'])) {
          $export = osf_export_list($shownotesArray['export'], $fullint, $mode);
      } else {
          $export = ''; // Fallback
      }
  } elseif ($mode == 'clean osf') {
      if (isset($shownotesArray['export']) && is_array($shownotesArray['export'])) {
          $export = osf_export_osf($shownotesArray['export'], $fullint, $mode);
      } else {
          $export = ''; // Fallback
      }
  } elseif ($mode == 'glossary') {
      if (isset($shownotesArray['export']) && is_array($shownotesArray['export'])) {
          $export = osf_export_glossary($shownotesArray['export'], $fullint);
      } else {
          $export = ''; // Fallback
      }
  } elseif (($mode == 'shownoter') || ($mode == 'podcaster')) {
      if (isset($shownotesArray['header']) && is_array($shownotesArray['header'])) {
          if ($mode == 'shownoter') {
              $persons = osf_get_persons('shownoter', $shownotesArray['header']);
              $export = isset($persons['html']) ? $persons['html'] : ''; // Absicherung
          } elseif ($mode == 'podcaster') {
              $persons = osf_get_persons('podcaster', $shownotesArray['header']);
              $export = isset($persons['html']) ? $persons['html'] : ''; // Absicherung
          }
      } else {
          $export = ''; // Fallback für fehlende 'header'-Daten
      }
  }
  if (isset($_GET['debug']) && (!is_feed())) {
    $export .= '<textarea>' . json_encode($shownotesArray) . '</textarea><textarea>' . print_r($shownotes_options, true) . htmlspecialchars($shownotesString) . '</textarea>';
  }

  if (!is_feed()) {
    // Prüfen, ob der Schlüssel 'export' existiert, und Standardwert '' verwenden
    $exportData = $shownotesArray['export'] ?? ''; // Setzt '' als Standardwert, wenn 'export' nicht existiert
    $export .= '<div style="display:none;visibility:hidden;" class="mp4chaps">' . trim(osf_export_chapterlist($exportData)) . '</div>';
  }

  return $export;}
}

function md_shownotes_shortcode($atts, $content = '') {
  $post_id   = get_the_ID();
  $shownotes = get_post_meta($post_id, '_shownotes', true);
  if ($shownotes == '') {
    $shownotes = get_post_meta($post_id, 'shownotes', true);
  }
  if ($content !== '') {
    $shownotesString = htmlspecialchars_decode(str_replace('<br />', '', str_replace('<p>', '', str_replace('</p>', '', $content))));
  } else {
    $shownotesString = "\n" . $shownotes . "\n";
  }
  return micromarkdown($shownotesString);
}

if (!isset($shownotes_options['main_osf_shortcode'])) {
  $osf_shortcode = 'shownotes';
} else {
  $osf_shortcode = $shownotes_options['main_osf_shortcode'];
}

if (!isset($shownotes_options['main_md_shortcode'])) {
  $md_shortcode = 'md-shownotes';
} else {
  $md_shortcode = $shownotes_options['main_md_shortcode'];
}

add_shortcode($md_shortcode, 'md_shownotes_shortcode');
add_shortcode($osf_shortcode, 'osf_shownotes_shortcode');
if ($osf_shortcode != 'osf-shownotes') {
  add_shortcode('osf-shownotes', 'osf_shownotes_shortcode');
}

function shownotesshortcode_add_admin_scripts() {
  if (!is_feed()) {
    wp_enqueue_script('majax', plugins_url('static/majaX/majax.js', __FILE__), array(), '0.5.7', false);
    wp_enqueue_script('importPad', plugins_url('static/shownotes_admin.js', __FILE__), array(), '0.5.7', false);
    wp_enqueue_script('tinyosf', plugins_url('static/tinyOSF/tinyosf.js', __FILE__), array(), '0.5.7', false);
    wp_enqueue_script('tinyosf_exportmodules', plugins_url('static/tinyOSF/tinyosf_exportmodules.js', __FILE__), array(), '0.5.7', false);
  }
}

function shownotesshortcode_add_scripts() {
  if (!is_feed()) {
    wp_enqueue_script('importPad', plugins_url('static/shownotes.js', __FILE__), array(), '0.5.7', false);
  }
}


if (is_admin()) {
  add_action('wp_print_scripts', 'shownotesshortcode_add_admin_scripts');
}

add_action('wp_print_scripts', 'shownotesshortcode_add_scripts');

/* 
 * deprecated; see below
 */
function custom_search_query( $query ) {
  $custom_fields = array(
    '_shownotes'
  );
  $searchterm = $query->query_vars['s'];
  $query->query_vars['s'] = '';
  if ($searchterm != '') {
    $meta_query = array('relation' => 'OR');
    foreach($custom_fields as $cf) {
      array_push($meta_query, array(
      'key' => $cf,
      'value' => $searchterm,
      'compare' => 'LIKE'
      ));
    }
    $query->set('meta_query', $meta_query);
  }
}

/* 
 * new search function for the shownotes that doesn't replace the posts query but extends it
 */
function shownotes_search_where($query) {

  // if we are on a search page, modify the generated SQL
  if ( is_search() && !is_admin() ) {

      global $wpdb;
      $custom_fields = array('_shownotes');
      $keywords = explode(' ', get_query_var('s')); // build an array from the search string
      $shownotes_query = "";
      foreach ($custom_fields as $field) {
           foreach ($keywords as $word) {
               $shownotes_query .= "((joined_tables.meta_key = '".$field."')";
               $shownotes_query .= " AND (joined_tables.meta_value  LIKE '%{$word}%')) OR ";
           }
      }
      
      // if the shownotes query is not an empty string, append it to the existing query
      if (!empty($shownotes_query)) {
          // add to where clause
          $query['where'] = str_replace(
                "(".$wpdb->prefix."posts.post_title LIKE '%",
                "({$shownotes_query} ".$wpdb->prefix."posts.post_title LIKE '%",
                $query['where']
              );

          $query['join'] = $query['join'] . " INNER JOIN {$wpdb->postmeta} AS joined_tables ON ({$wpdb->posts}.ID = joined_tables.post_id)";
      }

  }
  return ($query);
}

/* 
 * we need this filter to add a grouping to the SQL string - prevents duplicate result rows
 */
function shownotes_groupby($groupby){
  
  global $wpdb;

  // group by post id to avoid multiple results in the modified search
  $groupby_id = "{$wpdb->posts}.ID";
  
  // if this is not a search or the groupby string already contains our groupby string, just return
  if(!is_search() || strpos($groupby, $groupby_id) !== false) {
    return $groupby;
  } 

  // if groupby is empty, use ours
  if(strlen(trim($groupby)) === 0) {
    return $groupby_id;
  } 

  // groupby wasn't empty, append ours
  return $groupby.", ".$groupby_id;
}


function add_title_custom_field($postid){
  if (isset($_POST['shownotes'])) {
    update_post_meta($postid, '_shownotes', $_POST['shownotes']);
  }
  if (isset($_POST['content'])) {
    update_post_meta($postid, 'post_content', $_POST['content']);
  }
}

if (!isset($shownotes_options['main_defaultsearch'])) {
  //add_filter('posts_clauses', 'shownotes_search_where', 20, 1);
  //add_filter('posts_groupby', 'shownotes_groupby');
}


add_action('save_post', 'add_title_custom_field');

?>
