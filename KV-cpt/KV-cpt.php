<?php
/*
Plugin Name: Custom Post Types
Plugin URI: http://www.honeystreet.com
Description: 
Version: 1.0
Author: Krissie VandeNoord, Honeystreet Design Studio
Author URI: http://www.honeystreet.com/

This plugin is released under the GPLv2 license. The images packaged with this plugin are the property of
their respective owners, and do not, necessarily, inherit the GPLv2 license.
*/

/**
 * Create Standards,Events Post Type
 */
function honeystreet_standards_init() {
    $args = array(
      'public' => true,
      'label'  => 'Standard',
      'supports' => array( 'title','comments','revisions','page-attributes' ),
      'menu_icon' => 'dashicons-media-document',
      'has_archive' => true,
      'publicly_queryable' => true,
    );
    register_post_type( 'standard', $args );
    $args = array(
      'public' => true,
      'label'  => 'Meeting',
      'supports' => array( 'title','comments','revisions','page-attributes','editor' ),
      'menu_icon' => 'dashicons-calendar-alt',
      'has_archive' => true,
      'publicly_queryable' => true,
    );
    register_post_type( 'meetings', $args );
    
}
add_action( 'init', 'honeystreet_standards_init' );

/**
 * Create Sport Taxonomy
 */
add_action( 'init', 'create_sport_tax' );

function create_sport_tax() {
	register_taxonomy(
		'sport',
		'standard',
		array(
			'label' => __( 'Sport' ),
			'rewrite' => array( 'slug' => 'sport' ),
			'hierarchical' => true,
		)
	);
	register_taxonomy_for_object_type( 'sport', 'standard' );
}
/**
 * Create Status Taxonomy
 */
add_action( 'init', 'create_status_tax' );

function create_status_tax() {
	register_taxonomy(
		'status',
		'standard',
		array(
			'label' => __( 'Status' ),
			'rewrite' => array( 'slug' => 'status' ),
			'hierarchical' => true,
		)
	);
	register_taxonomy_for_object_type( 'status', 'standard' );
}
/**
 * Create Topics Taxonomy
 */
add_action( 'init', 'create_topic_tax' );

function create_topic_tax() {
	register_taxonomy(
		'topic',
		'standard',
		array(
			'labels' => array( 'name' => 'Topics', 'singular_name' => 'Topic','all_items' => 'All Topics','separate_items_with_commas' => 'Separate topics with commas','choose_from_most_used' => 'Choose from the most used topics','not_found' => 'No topics found' ),
			'rewrite' => array( 'slug' => 'topics' ),
		)
	);
	register_taxonomy_for_object_type( 'topic', 'standard' );
}

/**
 * Change 'Posts' to 'News' for the purpose of usability
 */
function change_post_menu_label() {
	global $menu;
	global $submenu;
	$menu[5][0] = 'News';
	$submenu['edit.php'][5][0] = 'News';
	$submenu['edit.php'][10][0] = 'Add News Article';
	$submenu['edit.php'][16][0] = 'News Tags';
	echo '';
}
function change_post_object_label() {
	global $wp_post_types;
	$labels = &$wp_post_types['post']->labels;
	$labels->name = 'News';
	$labels->singular_name = 'News Article';
	$labels->add_new = 'Add News Article';
	$labels->add_new_item = 'Add News Article';
	$labels->edit_item = 'Edit News Article';
	$labels->new_item = 'News Article';
	$labels->view_item = 'View News Articles';
	$labels->search_items = 'Search News Articles';
	$labels->not_found = 'No News found';
	$labels->not_found_in_trash = 'No News found in Trash';
}
add_action( 'init', 'change_post_object_label' );
add_action( 'admin_menu', 'change_post_menu_label' );

?>