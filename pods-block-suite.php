<?php
/**
 * Plugin Name:       PODS Block Suite
 * Description:       A comprehensive suite of Gutenberg blocks for PODS (Item Container, Related Item Container, and Smart Field).
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Håkan
 * Text Domain:       pods-block-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's custom blocks.
 */
function pods_block_suite_init() {
	register_block_type( __DIR__ . '/build/item-container' );
	register_block_type( __DIR__ . '/build/related-item-container' );
	register_block_type( __DIR__ . '/build/item-list-container' );
	register_block_type( __DIR__ . '/build/smart-field' );
}
add_action( 'init', 'pods_block_suite_init' );
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
 * Builds the JSON schema of all registered PODS and fields (including WordPress standard fields).
 *
 * @return WP_REST_Response
 */
function pods_block_suite_get_schema() {
	if ( ! function_exists( 'pods_api' ) ) {
		return rest_ensure_response( array() );
	}

	$api  = pods_api();
	$pods = $api->load_pods( array( 'fields' => true ) );

	// WordPress standard fields (mirrors the field list in the PODS template editor)
	$wp_standard_fields = array(
		array(
			'name'  => 'post_thumbnail',
			'label' => __( 'Featured Image (post_thumbnail)', 'pods-block-suite' ),
			'type'  => 'media',
			'group' => 'wp',
		),
		array(
			'name'  => 'post_title',
			'label' => __( 'Post Title (post_title)', 'pods-block-suite' ),
			'type'  => 'text',
			'group' => 'wp',
		),
		array(
			'name'  => 'post_content',
			'label' => __( 'Post Content (post_content)', 'pods-block-suite' ),
			'type'  => 'wysiwyg',
			'group' => 'wp',
		),
		array(
			'name'  => 'post_excerpt',
			'label' => __( 'Post Excerpt (post_excerpt)', 'pods-block-suite' ),
			'type'  => 'paragraph',
			'group' => 'wp',
		),
		array(
			'name'  => 'post_date',
			'label' => __( 'Post Date (post_date)', 'pods-block-suite' ),
			'type'  => 'date',
			'group' => 'wp',
		),
		array(
			'name'  => 'ID',
			'label' => __( 'Post ID (ID)', 'pods-block-suite' ),
			'type'  => 'number',
			'group' => 'wp',
		),
		array(
			'name'  => 'post_author',
			'label' => __( 'Author (post_author)', 'pods-block-suite' ),
			'type'  => 'text',
			'group' => 'wp',
		),
		array(
			'name'  => 'permalink',
			'label' => __( 'Permalink (permalink)', 'pods-block-suite' ),
			'type'  => 'website',
			'group' => 'wp',
		),
	);

	$schema = array();

	if ( is_array( $pods ) ) {
		foreach ( $pods as $pod_data ) {
			$pod_name  = is_object( $pod_data ) ? $pod_data->get_name() : ( $pod_data['name'] ?? '' );
			$pod_label = is_object( $pod_data ) ? $pod_data->get_label() : ( $pod_data['label'] ?? $pod_name );

			if ( empty( $pod_name ) ) {
				continue;
			}

			$raw_fields = is_object( $pod_data ) ? $pod_data->get_fields() : ( $pod_data['fields'] ?? array() );
			$pod_fields = array();

			if ( is_array( $raw_fields ) ) {
				foreach ( $raw_fields as $field ) {
					$f_name  = is_object( $field ) ? $field->get_name() : ( $field['name'] ?? '' );
					$f_label = is_object( $field ) ? $field->get_label() : ( $field['label'] ?? $f_name );
					$f_type  = is_object( $field ) ? $field->get_type() : ( $field['type'] ?? 'text' );

					// If the field is a relationship ('pick'), retrieve the target POD slug
					$pick_val = '';
					if ( 'pick' === $f_type ) {
						$pick_val = is_object( $field ) ? $field->get_arg( 'pick_val', '' ) : ( $field['pick_val'] ?? '' );
					}

					if ( ! empty( $f_name ) ) {
						$pod_fields[] = array(
							'name'       => $f_name,
							'label'      => sprintf( '%s (%s)', $f_label, $f_name ),
							'type'       => $f_type,
							'relatedPod' => $pick_val,
							'group'      => 'pods',
						);
					}
				}
			}

			$schema[ $pod_name ] = array(
				'name'           => $pod_name,
				'label'          => sprintf( '%s (%s)', $pod_label, $pod_name ),
				'fields'         => $pod_fields,
				'standardFields' => $wp_standard_fields,
			);
		}
	}

	return rest_ensure_response( $schema );
}