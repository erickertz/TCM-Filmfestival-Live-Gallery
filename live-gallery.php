<?php
/**
 * Plugin Name: 2014 TCM Filmfestival Live Gallery
 * Plugin URI: http://filmfestival.tcm.com
 * Description: Live gallery plugin for the 2014 TCM Filmfestival website.
 * Version: 1.0
 * Author: erictr1ck
 * Author URI: http://1trickpony.com
 * License: none
 */
 
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('max_execution_time',0);
 
$uploadDirBaseDir = dirname(realpath(__file__)).DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'original';
$now = time();

function live_gallery_activate() {

}
register_activation_hook( __FILE__,'live_gallery_activate');

function live_gallery_add_query_vars_filter($vars){
  $vars[] = "liveGalleryCategory";
  $vars[] = "liveGalleryId";
  return $vars;
}
add_filter('query_vars','live_gallery_add_query_vars_filter');

function live_gallery_create_post_type() {
	register_post_type('livegallery',
		array(
			'labels' => array(
				'name' 			=> __('Live Galleries'),
				'singular_name' => __('Live Gallery')
			),
			'public' 		=> false,
			'show_ui' 		=> true,
			'show_in_menu' 	=> true,
			'menu_position' => 5,
			'has_archive' 	=> true,
			'hierarchical' 	=> false,
			'supports'		=> array(
				'title',
				'editor',
				'thumbnail',
			)
		)
	);
	$labels = array(
		'name'                       => _x('Live Gallery Categories','taxonomy general name'),
		'singular_name'              => _x('Live Gallery Category','taxonomy singular name'),
		'search_items'               => __('Search Live Gallery Categories'),
		'popular_items'              => __('Popular Live Gallery Categories'),
		'all_items'                  => __('All Live Gallery Categories'),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __('Edit Live Gallery Category'),
		'update_item'                => __('Update Live Gallery Category'),
		'add_new_item'               => __('Add New Live Gallery Category'),
		'new_item_name'              => __('New Live Gallery Category Name'),
		'separate_items_with_commas' => __('Separate Live Gallery Categories with commas'),
		'add_or_remove_items'        => __('Add or remove Live Gallery Categories'),
		'choose_from_most_used'      => __('Choose from the most used wLive Gallery Categories'),
		'not_found'                  => __('No Live Gallery Categories found.'),
		'menu_name'                  => __('Live Gallery Categories'),
	);
	$args = array(
		'hierarchical'      	=> true,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array('slug' => 'live-gallery-categories'),
	);
	register_taxonomy('livegallerycategory','livegallery',$args);
	$termExists = term_exists('uncategorized','livegallerycategory');
	if(empty($termExists)){
		wp_insert_term('uncategorized','livegallerycategory',array('slug' => 'uncategorized'));	
	}
	add_image_size('live-gallery-1',460,573,true);
	add_image_size('live-gallery-2',860,573,true);
}
add_action('init','live_gallery_create_post_type');

function live_gallery_create_livegallerycategory($categoryID){
	global $uploadDirBaseDir;
	$term =  get_term_by('id',$categoryID,'livegallerycategory');
	$termSlug = $term->slug;
	$termName = $term->name;
	$newDir = $uploadDirBaseDir.DIRECTORY_SEPARATOR.$categoryID.'-'.$termSlug;
	if(!mkdir($newDir,0777,true)){
		die('Failed to create upload directory '.$newDir.' for '.$termName);
	}
}
// use this function to create subdirectories for each term / category
//add_action('create_livegallerycategory','live_gallery_create_livegallerycategory');

function live_gallery_delete_livegallerycategory($categoryID){
	global $uploadDirBaseDir;
	$newDir = $uploadDirBaseDir.DIRECTORY_SEPARATOR.$categoryID.'-';
	foreach(glob($newDir.'*',GLOB_ONLYDIR) as $dir) {
		if(is_dir($dir)){
			system('rm -rf '.escapeshellarg($dir));	
			break;
		}
	}
}
// use this function to delete subdirectories for each term / category
//add_action('delete_livegallerycategory','live_gallery_delete_livegallerycategory');

//[live-gallery-cron]
function live_gallery_cron($atts){
	global $uploadDirBaseDir,$now;
	$i = 0;
	$iF = 0;
	$return = "";
	if($handle = opendir($uploadDirBaseDir)) {
		while(false !== ($entry = readdir($handle))){
			$fullFile = $uploadDirBaseDir.DIRECTORY_SEPARATOR.$entry;
			if((is_file($fullFile)) && (substr($entry,0,1) != '.')){
				if((filemtime($fullFile) + (60 * 1)) < ($now)) {
					$fp = @fopen($fullFile, "r+");
					@flock($fp,LOCK_EX);
					$metaData = live_gallery_extract_metadata($fullFile);
					$categoryName = strtolower($metaData["xmp"]["photoshop"]["Category"]);
					$term = get_term_by('slug',sanitize_title($categoryName),'livegallerycategory');
					if(!empty($term)){
						$termID = $term->term_id;
					} else {
						$term = get_term_by('name','uncategorized','livegallerycategory');
						$termID = $term->term_id;
					}
					$insertPost = live_gallery_insert_post($termID,$metaData,$fullFile);
					if($insertPost){
						if(file_exists($fullFile)){
							unlink($fullFile);
						}
						$return .= 'SUCCESS: '.$fullFile;
						$i++;
					} else {
						$return .= 'FAIL: '.$fullFile;
						$iF++;
					}
					$return .= '<br/>';
					
				}
			}
		}
		closedir($handle);
	}
	$return .= $i.' files successfully processed<br/>';
	$return .= $iF.' files failed<br/>';
	return $return;
}

// use this function if using subdirectories for each term / category
function live_gallery_cron_subdirectories($atts){
	global $uploadDirBaseDir;
	$i = 0;
	$return = "";
	if($handle = opendir($uploadDirBaseDir)) {
		while(false !== ($entry = readdir($handle))){
			$fullDir = $uploadDirBaseDir.DIRECTORY_SEPARATOR.$entry;
			if((is_dir($fullDir)) && (substr($entry,0,1) != '.')){
				$termID = preg_replace( '/[^0-9]/','',$entry);
				if($handle2 = opendir($fullDir)) {
					while(false !== ($entry2 = readdir($handle2))){
						$fullFile = $uploadDirBaseDir.DIRECTORY_SEPARATOR.$entry.DIRECTORY_SEPARATOR.$entry2;
						if((is_file($fullFile)) && (substr($entry2,0,1) != '.')){
							$i++;
							$metaData = live_gallery_extract_metadata($fullFile);
							$insertPost = live_gallery_insert_post($termID,$metaData,$fullFile);
							if($insertPost){
								unlink($fullFile);
								$return .= 'SUCCESS: '.$fullFile;
							} else {
								$return .= 'FAIL: '.$fullFile;
							}
							$return .= '<br/>';
						}
					}
				}
			}
		}
		closedir($handle);
	}
	$return .= $i.' files processed';
	return $return;
}

function live_gallery_extract_metadata($file){
	require_once('lib/getID3-1.9.7/getid3/getid3.php');
	$getID3 = new getID3;
	$id3Data = $getID3->analyze($file);
	if($id3Data){
		return $id3Data;
	}
	return false;
}

function live_gallery_insert_post($termID,$metaData,$file){
	global $now;
	$title = $metaData["xmp"]["dc"]["title"][0].': '.$metaData["xmp"]["dc"]["description"][0];
	$description = $metaData["xmp"]["dc"]["description"][0];
	$creator = $metaData["xmp"]["dc"]["creator"][0];
	$categoryName = strtolower($metaData["xmp"]["photoshop"]["Category"]);
	$createDate = strtotime($metaData["xmp"]["photoshop"]["DateCreated"]);
	if($createDate > $now){
		$createDate = '';
	}
	$post = array(
		'post_content'   => $description,
		'post_name'      => sanitize_title($title),
		'post_title'     => preg_replace('/\.[^.]+$/','',$title),
		'post_status'    => 'publish',
		'post_type'      => 'livegallery',
	);
	if(!empty($createDate)){
		$post['post_date'] = date("Y-m-d H:i:s",$createDate);
	}
	$insertPost = wp_insert_post($post,false);
	$wp_set_object_terms = wp_set_object_terms($insertPost,intval($termID),'livegallerycategory');
	if($insertPost){
		$imageOrientation = "live-gallery-2";
		if(list($width,$height,$type,$attr) = getimagesize($file)){
			if($height > $width){
				$imageOrientation = "live-gallery-1";
			}	
		}
		live_gallery_insert_attachment($insertPost,$file);
		add_post_meta($insertPost,'image-orientation',$imageOrientation);
		return $insertPost;
	}
	return true;
}

function live_gallery_insert_attachment($postID,$file){
	$wp_upload_dir = wp_upload_dir();
	$newFile = $wp_upload_dir['path'].DIRECTORY_SEPARATOR.basename($file);
	//chmod($wp_upload_dir['path'],0777);
	rename($file,$newFile);
	$filetype = wp_check_filetype(basename($newFile),null);
	$attachment = array(
		'guid'           => $wp_upload_dir['url'].'/'.basename($newFile), 
		'post_mime_type' => $filetype['type'],
		'post_title'     => preg_replace('/\.[^.]+$/','',basename($newFile)),
		'post_content'   => '',
		'post_status'    => 'inherit'
	);
	$attach_id = wp_insert_attachment($attachment,$newFile,$postID);
	require_once(ABSPATH.'wp-admin/includes/image.php' );
	$attach_data = wp_generate_attachment_metadata($attach_id,$newFile);
	wp_update_attachment_metadata($attach_id,$attach_data);
	set_post_thumbnail($postID,$attach_id);
	return true;
}
add_shortcode('live-gallery-cron','live_gallery_cron');
