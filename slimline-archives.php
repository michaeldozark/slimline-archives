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

	/**
	 * Check if we are in the admin area
	 *
	 * @link https://developer.wordpress.org/reference/functions/is_admin/
	 *       Documentation of the `is_admin` function
	 * @link https://developer.wordpress.org/reference/functions/wp_doing_ajax/
	 *       Documentation of the `wp_doing_ajax` function
	 */
	if ( is_admin() && ! wp_doing_ajax() ) {

		/**
		 * Edit rewrite arguments for affected post types
		 *
		 * We're using `admin_enqueue_scripts` since it is the first hook we can
		 * count on firing AFTER an option update. If we fire before an option update
		 * we can't restore the post type's default slug without writing additinal
		 * handling.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
		 *       Documentation of the `admin_enqueue_scripts` action hook
		 */
		add_action( 'admin_enqueue_scripts',    'slimline_archives_post_type_slugs',       0 );

		/**
		 * Register our custom option through the Settings API
		 *
		 * @link https://developer.wordpress.org/reference/hooks/admin_init/
		 *       Documentation of the `admin_init` action hook
		 */
		add_action( 'admin_init', 'slimline_archives_register_setting', 10 );

		/**
		 * Add our settings and fields on the Reading options page
		 *
		 * @link https://developer.wordpress.org/reference/hooks/load-pagenow/
		 *       Documentation of the `load-{$pagenow}` action hook
		 */
		add_action( 'load-options-reading.php', 'slimline_archives_add_settings_section',  5 );
		add_action( 'load-options-reading.php', 'slimline_archives_add_settings_fields',  10 );

	} else { // if ( is_admin() && ! wp_doing_ajax() )

		/**
		 * Generally post types should be registered during the `init` action, so we
		 * will modify the `WP_Post_Type::rewrite` property of affected post types
		 * at a very late priority on the same hook.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/init/
		 *       Documentation of the `init` action hook
		 */
		add_action( 'init', 'slimline_archives_post_type_slugs', 1000 );

	} // if ( is_admin() && ! wp_doing_ajax() )

	/**
	 * Flush and regenerate our rewrite rules automatically after updating our plugin
	 * settings.
	 *
	 * Note this will only work correctly if the option is updated BEFORE the rewrite
	 * arguments for the post types are edited. This means before `admin_enqueue_scripts`
	 * priority 0 in the admin area (handled by default when saving on the Reading
	 * Settings page) or before `init` priority 1000 on the frontend.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/update_option_option/
	 *       Documentation of the `update_option_{$option}` action hook
	 */
	add_action( 'update_option_slimline_archives', 'slimline_archives_flush_rewrite_rules', 10 );

	/**
	 * Add handling for Yoast SEO
	 */
	add_filter( 'wpseo_metadesc', 'slimline_archives_wpseo_metadesc', 1000, 1 );
	add_filter( 'wpseo_title',    'slimline_archives_wpseo_title',    1000, 1 );
}

/**
 * Handles flushing and regenerating rewrite rules after a settings change
 *
 * @since 0.1.0
 */
function slimline_archives_flush_rewrite_rules() {

	/**
	 * Post types have already been registered with default settings before the
	 * option is updated. We need to modify the `WP_Post_Type::rewrite` property
	 * for each affected post type so the refreshed rewrite rules will reflect our
	 * changes.
	 */
	slimline_archives_post_type_slugs();

	/**
	 * Flush existing rewrite rules and regenerate
	 *
	 * @link https://developer.wordpress.org/reference/functions/flush_rewrite_rules/
	 *       Documentation of the `flush_rewrite_rules` function
	 */
	flush_rewrite_rules();

	/**
	 * We already ran this so no need to do it again
	 */
	remove_action( 'admin_enqueue_scripts', 'slimline_archives_post_type_slugs',    0 );
	remove_action( 'init',                  'slimline_archives_post_type_slugs', 1000 );

}

/**
 * Register option so we can save it from the admin page
 *
 * @link  https://developer.wordpress.org/plugins/settings/using-settings-api/#add-a-setting
 *        Documentation of how to add a setting using the Settings API
 * @since 0.1.0
 */
function slimline_archives_register_setting() {

	/**
	 * Register the setting for the Reading settings group
	 *
	 * @link https://developer.wordpress.org/reference/functions/register_setting/
	 *       Documentation of the `register_setting` function
	 */
	register_setting( 'reading', 'slimline_archives', [ 'sanitize_callback' => 'slimline_archives_sanitize_array' ] );
}

/**
 * Custom settings sanitization
 *
 * @link
 */
function slimline_archives_sanitize_array( $options = array() ) {

	return array_map( 'absint', $options );
}

function slimline_archives_add_settings_section() {

	add_settings_section(
		'slimline_archive_settings',
		__( 'Pages for Custom Post Archives', 'slimline-archives' ),
		null,
		'reading'
	);
}

function slimline_archives_add_settings_fields() {

	$pages = slimline_archives_get_pages();

	$post_types = slimline_archives_get_post_types();

	$settings = slimline_archives_get_settings();

	foreach ( $post_types as $post_type_name => $post_type_object ) {

		add_settings_field(
			"slimline_archives_{$post_type_name}",
			$post_type_object->label,
			'slimline_archives_page_select',
			'reading',
			'slimline_archive_settings',
			[
				'class'     => "slimline-archives-field slimline-archives-{$post_type_name}-field",
				'label_for' => "slimline-archives-{$post_type_name}",
				'pages'     => $pages,
				'post_type' => $post_type_object,
				'selected'  => ( isset( $settings[$post_type_name] ) && $settings[$post_type_name] ? $settings[$post_type_name] : 0 ),
			]
		);

	} // foreach ( $post_types as $post_type )

}

function slimline_archives_get_pages() {

	$page_args = [
		'order'          => 'ASC',
		'orderby'        => 'post_title',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'posts_per_page' => -1,
	];

	if ( function_exists( 'wc_get_page_id' ) ) {

		$woocommerce_pages = array(
			wc_get_page_id( 'cart' ),
			wc_get_page_id( 'checkout' ),
			wc_get_page_id( 'myaccount' ),
			wc_get_page_id( 'shop' ),
			wc_get_page_id( 'terms' ),
		);

		$page_args['post__not_in'] = array_filter( $woocommerce_pages );

	} // if ( function_exists( 'wc_get_page_id' ) )

	$page_args = apply_filters( 'slimline_archives_page_args', $page_args );

	$pages = get_posts( $page_args );

	foreach ( $pages as $page ) {
		$pages_array[$page->ID] = $page->post_title;
	} // foreach ( $pages as $page )

	return apply_filters( 'slimline_archives_pages', $pages_array )	;
}

function slimline_archives_get_post_types() {

	$post_types_args = [ 'public' => true, '_builtin' => false ];

	if ( ! apply_filters( 'slimline_archives_force_archive', false ) ) {
		$post_type_args['has_archive'] = true;
	} // if ( ! apply_filters( 'slimline_archives_force_archive', false ) )

	$post_types_args = apply_filters( 'slimline_archives_post_types_args', $post_type_args );

	$post_types = get_post_types( $post_types_args, 'objects' );

	$excluded_post_types = array_flip( slimline_archives_get_excluded_post_types() );

	return apply_filters( 'slimline_archives_post_types', array_diff_key( $post_types, $excluded_post_types ) );
}

function slimline_archives_get_excluded_post_types() {

	$post_types = [];

	/**
	 * Skip WooCommerce products
	 *
	 * WooCommerce has custom handling for product archives and we don't want to
	 * interfere with that.
	 *
	 * @link http://php.net/manual/en/function.function-exists.php
	 *       Documentation of the PHP `function_exists` function
	 */
	if ( function_exists( 'WC' ) ) {
		$post_types[] = 'product';
	} // if ( function_exists( 'WC' ) )

	return apply_filters( 'slimline_archives_excluded_post_types', $post_types );
}

function slimline_archives_page_select( $args ) {

	$pages    = $args['pages'];
	$selected = $args['selected'];

	echo "<select id='slimline-archives-{$args['post_type']->name}' name='slimline_archives[{$args['post_type']->name}]'>";
	echo '<option value="0">', esc_html__( '— Select —', 'slimline-archives' ), '</option>';
	foreach ( $pages as $page_id => $page_title ) {
		echo '<option ', selected( $page_id, $selected, false ), ' value="', $page_id, '">', esc_html( $page_title ), '</option>';
	} // foreach ( $pages as $page_id => $page_title )
	echo '</select>';

}

function slimline_archives_post_type_slugs() {

	global $wp_post_types;

	$slimline_archives_settings = slimline_archives_get_settings();

	$home_url = home_url('/');

	foreach ( $wp_post_types as $post_type => &$post_type_object ) {

		if ( isset( $slimline_archives_settings[$post_type] ) ) {

			$slug = str_replace( $home_url, '', untrailingslashit( get_permalink( $slimline_archives_settings[$post_type] ) ) );

			if ( $slug ) {

				$post_type_object->has_archive = true;

				$post_type_object->remove_rewrite_rules();

				$post_type_object->rewrite = slimline_archives_post_type_rewrite_args( $post_type_object, $slug );

				$post_type_object->add_rewrite_rules();

			} // if ( $slug )

		} // if ( isset( $slimline_archives_settings[$post_type] ) )

	} // foreach ( $wp_post_types as $post_type => $post_type_object )

}

function slimline_archives_get_settings() {

	return get_option( 'slimline_archives', array() );
}

function slimline_archives_post_type_archive_title( $title, $post_type ) {

	$settings = slimline_archives_get_settings();

	if ( isset( $settings[$post_type] ) ) {
		$title = get_the_title( $settings[$post_type] );
	} // if ( isset( $settings[$post_type] ) )

	return $title;
}

function slimline_archives_archive_description( $description ) {

	if ( is_post_type_archive() ) {

		$settings = slimline_archives_get_settings();

		$post_type = get_query_var( 'post_type' );

		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		} // if ( is_array( $post_type ) )

		if ( isset( $settings[$post_type] ) ) {

			$archive_page = get_post( $settings[$post_type] );

			$description = apply_filters( 'slimline_archives_archive_description', $archive_page->post_content );

			$description = str_replace( ']]>', ']]&gt;', $description );

		} // if ( isset( $settings[$post_type] ) )

	} // if ( is_post_type_archive() )

	return $description;
}

function slimline_archives_post_type_rewrite_args( $post_type_object, $slug ) {

	$rewrite = ( is_array( $post_type_object->rewrite ) ? $post_type_object->rewrite : [] );

	$rewrite['slug'] = $slug;

	if ( ! isset( $rewrite['with_front'] ) ) {
		$rewrite['with_front'] = true;
	}
	if ( ! isset( $rewrite['pages'] ) ) {
		$rewrite['pages'] = true;
	}
	if ( ! isset( $rewrite['feeds'] ) ) {
		$rewrite['feeds'] = true;
	}
	if ( ! isset( $rewrite['ep_mask'] ) ) {
		$rewrite['ep_mask'] = EP_PERMALINK;
	}

	return $rewrite;
}

function slimline_archives_wpseo_metadesc( $metadesc = '' ) {

	if ( is_post_type_archive() ) {

		$post_archive_metadesc = slimline_archives_get_wpseo_metadesc();

		if ( $post_archive_metadesc ) {
			$metadesc = $post_archive_metadesc;
		} // if ( $post_archive_metadesc )

	} // if ( is_post_type_archive() )

	return $metadesc;
}

function slimline_archives_wpseo_title( $title = '' ) {

	if ( is_post_type_archive() ) {

		$post_archive_title = slimline_archives_get_wpseo_title();

		if ( $post_archive_title ) {
			$title = $post_archive_title;
		} // if ( $post_archive_title )

	} // if ( is_post_type_archive() )

	return $title;
}

/**
 * Get Yoast SEO metadesc for the post type archive page
 *
 * @param  string $post_type Name of the post type to get a metadescription for
 * @return string $metadesc  Description if archive page found and description (or
 *                           description template) is set. Empty string if not.
 * @since  0.1.0
 */
function slimline_archives_get_wpseo_metadesc( $post_type = null ) {

	$metadesc = '';

	$post_type = slimline_archives_get_archive_post_type( $post_type );

	$archive_page = slimline_archives_get_archive_page( $post_type );

	if ( $archive_page ) {

		$metadesc = WPSEO_Meta::get_value( 'metadesc', $archive_page->ID );

		/**
		 * If we don't have a meta description yet, retrieve the template for the
		 * post type. This will let us auto-generate the description based on the
		 * page.
		 */
		if ( ! $metadesc ) {

			$options = WPSEO_Options::get_options( array( 'wpseo' ) );

			if ( isset( $options["metadesc-{$archive_page->post_type}"] ) ) {

				$metadesc = $options["metadesc-{$archive_page->post_type}"];

			} // if ( isset( $options["metadesc-{$post_type}"] ) )

		} // if ( ! $metadesc )

	} // if ( $archive_page )

	return wpseo_replace_vars( $metadesc, $archive_page );
}

function slimline_archives_get_archive_post_type( $post_type = null ) {

	if ( ! $post_type ) {

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
		} // if ( is_post_type_archive() )

		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		} // if ( is_array( $post_type ) )

	} // if ( ! $post_type )

	return $post_type;
}

function slimline_archives_get_wpseo_title( $post_type = null ) {

	$archive_page = slimline_archives_get_archive_page( $post_type );

	if ( $archive_page ) {
		return WPSEO_Frontend::get_instance()->get_content_title( $archive_page );
	} // if ( $archive_page )

	return '';
}

/**
 * Get the post object of the page assigned as the archive for a given post type
 *
 * @param  string       $post_type Name of the post type to search
 * @return WP_Post|bool            Post object of page if found, FALSE if not
 * @since  0.1.0
 */
function slimline_archives_get_archive_page( $post_type = null ) {

	/**
	 * Get the ID for the archive page
	 */
	$page_id = slimline_archives_get_archive_page_id( $post_type );

	/**
	 * If we have a page set as the post type archive, return its post object
	 *
	 * @link https://developer.wordpress.org/reference/functions/get_post/
	 *       Documentation of the `get_post` function
	 */
	if ( $page_id ) {
		return get_post( $page_id );
	} // if ( $page_id )

	return false;
}

/**
 * Get the ID for the page assigned as the archive for a given post type
 *
 * @param  string   $post_type Name of the post type to search
 * @return int|bool            ID of page if found, FALSE if not
 * @since  0.1.0
 */
function slimline_archives_get_archive_page_id( $post_type = null ) {

	/**
	 * Get current post type if none supplied
	 */
	$post_type = slimline_archives_get_archive_post_type( $post_type );

	if ( $post_type ) {

		$settings = slimline_archives_get_settings();

		/**
		 * If we have a page set as the post type archive, return its ID
		 *
		 * @link http://php.net/manual/en/function.isset.php
		 *       Documentation of the PHP `isset` function
		 * @link https://developer.wordpress.org/reference/functions/absint/
		 *       Documentation of the `absint` function
		 */
		if ( isset( $settings[$post_type] ) ) {
			return absint( $settings[$post_type] );
		} // if ( isset( $settings[$post_type] ) )

	} // if ( $post_type )

	return false;
}