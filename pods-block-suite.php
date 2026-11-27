<?php
/**
 * Plugin Name:       PODS Block Suite
 * Description:       A comprehensive suite of Gutenberg blocks for PODS (Item Container, Related Item Container, Item List Container, and Smart Field).
 * Version:           1.3.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  pods
 * Author:            Håkan Liljegren
 * Text Domain:       pods-block-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/render-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/color-filters.php';

foreach ( array( 'post', 'term', 'comment' ) as $meta_type ) {
	foreach ( array( 'added', 'updated', 'deleted' ) as $action ) {
		add_action( "{$action}_{$meta_type}_meta", 'pods_block_suite_invalidate_color_cache', 10, 3 );
	}
}

/**
 * Checks whether the PODS Framework plugin is installed and active.
 *
 * @return bool True if PODS is active, false otherwise.
 */
function pods_block_suite_is_pods_active() {
	return defined( 'PODS_VERSION' ) || function_exists( 'pods' );
}

/**
 * Displays an admin error notice if PODS Framework is not active.
 */
function pods_block_suite_missing_pods_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$install_url = admin_url( 'plugin-install.php?s=pods&tab=search&type=term' );

	printf(
		'<div class="notice notice-error"><p><strong>%1$s:</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
		esc_html__( 'PODS Block Suite', 'pods-block-suite' ),
		esc_html__( 'This plugin requires the PODS Framework plugin to be installed and activated.', 'pods-block-suite' ),
		esc_url( $install_url ),
		esc_html__( 'Install or activate PODS &rarr;', 'pods-block-suite' )
	);
}

/**
 * Initializes the plugin only when PODS Framework is active.
 */
function pods_block_suite_bootstrap() {
	if ( ! pods_block_suite_is_pods_active() ) {
		add_action( 'admin_notices', 'pods_block_suite_missing_pods_notice' );
		return;
	}

	add_action( 'init', 'pods_block_suite_init' );
	add_action( 'rest_api_init', 'pods_block_suite_register_rest_routes' );
}
add_action( 'plugins_loaded', 'pods_block_suite_bootstrap' );

/**
 * Registers the plugin's custom blocks.
 */
function pods_block_suite_init() {
	register_block_type( __DIR__ . '/build/item-container' );
	register_block_type( __DIR__ . '/build/related-item-container' );
	register_block_type( __DIR__ . '/build/item-list-container' );
	register_block_type( __DIR__ . '/build/smart-field' );
}

/**
 * Registers a REST API endpoint providing all PODS, their fields, and WordPress standard fields to the block editor.
 */
function pods_block_suite_register_rest_routes() {
	register_rest_route(
		'pods-block-suite/v1',
		'/schema',
		array(
			'methods'             => 'GET',
			'callback'            => 'pods_block_suite_get_schema',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'pods_block_suite_register_rest_routes' );

/**
 * Builds a structured JSON schema of all registered PODS, their custom fields,
 * and the WordPress standard fields supported by each post type.
 *
 * @return WP_REST_Response
 */
function pods_block_suite_get_schema() {
	if ( ! function_exists( 'pods_api' ) ) {
		return rest_ensure_response( array() );
	}

	$api      = pods_api();
	$all_pods = $api->load_pods( array( 'names' => true ) );
	$schema   = array();

	if ( empty( $all_pods ) || ! is_array( $all_pods ) ) {
		return rest_ensure_response( $schema );
	}

	foreach ( $all_pods as $pod_name => $pod_label ) {
		$pod_obj = pods( $pod_name );
		if ( ! $pod_obj || ! $pod_obj->valid() ) {
			continue;
		}

		$fields_list = array();
		$raw_fields  = $pod_obj->fields();

		$excluded_filter_slugs = array(
			'post_title',
			'post_content',
			'post_excerpt',
			'comment_status',
			'ping_status',
			'post_password',
			'permalink',
			'to_ping',
			'pinged',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comments',
			'post_thumbnail',
			'post_name',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'filter',
			'ancestors',
		);

		$excluded_filter_types = array(
			'password',
			'code',
			'html',
			'file',
			'media',
			'avatar',
			'oembed',
		);

		if ( is_array( $raw_fields ) ) {
			foreach ( $raw_fields as $field_slug => $field_data ) {
				$f_label = is_object( $field_data ) && method_exists( $field_data, 'get_label' )
					? $field_data->get_label()
					: ( $field_data['label'] ?? $field_slug );

				$f_type = is_object( $field_data ) && method_exists( $field_data, 'get_type' )
					? $field_data->get_type()
					: ( $field_data['type'] ?? 'text' );

				$related_pod = '';
				if ( 'pick' === $f_type ) {
					$related_pod = is_object( $field_data )
						? $field_data->get_arg( 'pick_val', '' )
						: ( $field_data['pick_val'] ?? '' );
				}

				$is_repeatable = is_object( $field_data )
					? (bool) $field_data->get_arg( 'repeatable', false )
					: ! empty( $field_data['repeatable'] );

				$file_format = is_object( $field_data )
					? $field_data->get_arg( 'file_format_type', 'single' )
					: ( $field_data['file_format_type'] ?? 'single' );

				$pick_format = is_object( $field_data )
					? $field_data->get_arg( 'pick_format_type', 'single' )
					: ( $field_data['pick_format_type'] ?? 'single' );

				$is_multiple = $is_repeatable
					|| ( in_array( $f_type, array( 'file', 'media', 'avatar' ), true ) && 'multi' === $file_format )
					|| ( 'pick' === $f_type && 'multi' === $pick_format );

				$is_sortable = ! in_array( $f_type, array( 'file', 'media', 'avatar', 'password' ), true );

				$is_filterable = ! in_array( $field_slug, $excluded_filter_slugs, true )
					&& ! in_array( $f_type, $excluded_filter_types, true );

				$fields_list[] = array(
					'name'       => $field_slug,
					'label'      => $f_label,
					'type'       => $f_type,
					'group'      => 'pods',
					'relatedPod' => $related_pod,
					'sortable'   => $is_sortable,
					'isMultiple' => $is_multiple,
					'filterable' => $is_filterable,
				);
			}
		}

		// Build WordPress standard fields dynamically based on what this post type actually supports
		$wp_standard_fields = array();
		$is_post_type       = post_type_exists( $pod_name );

		if ( ! $is_post_type || post_type_supports( $pod_name, 'thumbnail' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'post_thumbnail',
				'label'    => __( 'Featured Image (post_thumbnail)', 'pods-block-suite' ),
				'type'     => 'media',
				'group'    => 'wp',
				'sortable' => false,
				'filterable' => false,
			);
		}

		if ( ! $is_post_type || post_type_supports( $pod_name, 'title' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'post_title',
				'label'    => __( 'Post Title (post_title)', 'pods-block-suite' ),
				'type'     => 'text',
				'group'    => 'wp',
				'sortable' => true,
			);
		}

		if ( ! $is_post_type || post_type_supports( $pod_name, 'editor' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'post_content',
				'label'    => __( 'Post Content (post_content)', 'pods-block-suite' ),
				'type'     => 'wysiwyg',
				'group'    => 'wp',
				'sortable' => false,
			);
		}

		if ( ! $is_post_type || post_type_supports( $pod_name, 'excerpt' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'post_excerpt',
				'label'    => __( 'Post Excerpt (post_excerpt)', 'pods-block-suite' ),
				'type'     => 'paragraph',
				'group'    => 'wp',
				'sortable' => false,
			);
		}

		// Publish date and Post ID are always present on WordPress posts
		$wp_standard_fields[] = array(
			'name'     => 'post_date',
			'label'    => __( 'Publish Date (post_date)', 'pods-block-suite' ),
			'type'     => 'date',
			'group'    => 'wp',
			'sortable' => true,
		);

		$wp_standard_fields[] = array(
			'name'     => 'ID',
			'label'    => __( 'Post ID (ID)', 'pods-block-suite' ),
			'type'     => 'number',
			'group'    => 'wp',
			'sortable' => true,
		);

		if ( ! $is_post_type || post_type_supports( $pod_name, 'author' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'post_author',
				'label'    => __( 'Author (post_author)', 'pods-block-suite' ),
				'type'     => 'text',
				'group'    => 'wp',
				'sortable' => true,
			);
		}

		if ( $is_post_type && post_type_supports( $pod_name, 'page-attributes' ) ) {
			$wp_standard_fields[] = array(
				'name'     => 'menu_order',
				'label'    => __( 'Menu Order (menu_order)', 'pods-block-suite' ),
				'type'     => 'number',
				'group'    => 'wp',
				'sortable' => true,
			);
		}

		$wp_standard_fields[] = array(
			'name'     => 'permalink',
			'label'    => __( 'Permalink (permalink)', 'pods-block-suite' ),
			'type'     => 'website',
			'group'    => 'wp',
			'sortable' => false,
		);

		$schema[ $pod_name ] = array(
			'name'           => $pod_name,
			'label'          => $pod_label,
			'fields'         => $fields_list,
			'standardFields' => $wp_standard_fields,
		);
	}

	return rest_ensure_response( $schema );
}