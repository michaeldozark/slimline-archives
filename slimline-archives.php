<?php
/**
 * Plugin Name: Slimline Post Type Archives
 * Plugin URI: http://www.michaeldozark.com/slimline/archives/
 * Description: Adds the ability to designate a page as the archive for a custom post type.
 * Author: Michael Dozark
 * Author URI: http://www.michaeldozark.com/
 * Version: 0.1.0
 * License: GPL2
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2.0, as published by the Free Software Foundation.  You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package    Slimline / Post Type Archives
 * @version    0.1.0
 * @author     Michael Dozark <michael@michaeldozark.com>
 * @copyright  Copyright (c) 2017, Michael Dozark
 * @link       http://www.michaeldozark.com/wordpress/slimline/archives/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit; // exit if accessed directly

/**
 * Call initialization function.
 *
 * @link https://developer.wordpress.org/reference/hooks/plugins_loaded/
 *       Documentation of `plugins_loaded` hook
 */
add_action( 'plugins_loaded', 'slimline_archives' );

/**
 * Initialize plugin
 *
 * @link  https://github.com/slimline/tinymce/wiki/slimline_archives()
 * @since 0.1.0
 */
function slimline_archives() {

	add_action( 'admin_menu', 'slimline_archives_register_setting' );

	add_action( 'load-options-reading.php', 'slimline_archives_add_settings_section', 5 );
	add_action( 'load-options-reading.php', 'slimline_archives_add_settings_fields', 10 );
}

function slimline_archives_register_setting() {

	register_setting( 'reading', 'slimline_archives', [ 'sanitize_callback' => 'slimline_archives_sanitize_option' ] );
}

function slimline_archives_sanitize_option( $option = array() ) {

	return array_map( 'absint', $option );
}

function slimline_archives_add_settings_section() {

	add_settings_section(
		'slimline_archive_settings',
		__( 'Custom Post Type Archive Pages', 'slimline-archives' ),
		null,
		'reading'
	);
}

function slimline_archives_add_settings_fields() {

	$pages = get_posts([
		'order'          => 'ASC',
		'orderby'        => 'post_title',
		'post_type'      => 'page',
		'posts_per_page' => -1,
	]);

	foreach ( $pages as $page ) {

		$pages_array[$page->ID] = $page->post_title;

	} // foreach ( $pages as $page )

	/**
	 *
	 * @link https://developer.wordpress.org/reference/classes/wp_post_type/
	 *       Documentation of the `WP_Post_Type` class
	 */
	$post_types = get_post_types([
		'public'   => true,
		'_builtin' => false,
	]);

	foreach ( $post_types as $post_type ) {

		$post_type_name = $post_type->name;

		/**
		 * Skip WooCommerce products
		 *
		 * WooCommerce has custom handling for product archives and we don't want to
		 * interfere with that.
		 *
		 * @link http://php.net/manual/en/function.function-exists.php
		 *       Documentation of the PHP `function_exists` function
		 */
		if ( 'product' === $post_type_name && function_exists( 'WC' ) ) {
			continue;
		} // if ( 'product' === $post_type_name && function_exists( 'WC' ) )

		add_settings_field(
			"slimline_archives_{$post_type_name}",
			$post_type->label,
			'slimline_archives_page_select',
			'reading',
			'slimline_archives',
			[
				'class'     => "slimline-archives-field slimline-archives-{$post_type_name}-field",
				'label_for' => $post_type->label,
				'pages'     => $pages_array,
				'post_type' => $post_type,
				'selected'  => ( isset( $options[$post_type_name] ) && $options[$post_type_name] ? $options[$post_type_name] : 0 ),
			]
		);

	} // foreach ( $post_types as $post_type )

}

function slimline_archives_page_select( $args ) {

	$pages    = $args['pages'];
	$selected = $args['selected'];

	printf( '<select id="slimline-archives-%1$s" name="slimline_archives[%1$s]">', $args['post_type']->name );
	printf( '<option value="0">%1$s</option>', esc_html__( '— Select —', 'slimline-archives' ) );
	foreach ( $pages as $page_id => $page_title ) {
		printf( '<option %3$s value="%1$s">%2$s</option>', $page_id, esc_html( $page_title ), selected( $page_id, $selected, false ) );
	} // foreach ( $pages as $page_id => $page_title )
	echo '</select>';

}