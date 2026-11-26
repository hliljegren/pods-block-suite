<?php
/**
 * Shared server-side rendering and sanitization helpers for PODS Block Suite.
 *
 * @package PodsBlockSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes common Flexbox layout and card attributes across container blocks.
 *
 * @param array  $attributes        Raw block attributes.
 * @param string $default_direction Default flex direction ('row' or 'column').
 * @return array{
 *     flex_direction: string,
 *     justify_content: string,
 *     columns: int,
 *     link_to_post: bool
 * }
 */
function pods_block_suite_sanitize_layout_attributes( array $attributes, $default_direction = 'row' ) {
	$allowed_directions = array( 'row', 'column' );
	$raw_direction      = isset( $attributes['flexDirection'] ) ? sanitize_key( $attributes['flexDirection'] ) : $default_direction;
	$flex_direction     = in_array( $raw_direction, $allowed_directions, true ) ? $raw_direction : $default_direction;

	$allowed_justify = array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' );
	$raw_justify     = isset( $attributes['justifyContent'] ) ? sanitize_key( $attributes['justifyContent'] ) : 'center';
	$justify_content = in_array( $raw_justify, $allowed_justify, true ) ? $raw_justify : 'center';

	$columns      = isset( $attributes['columns'] ) ? max( 1, min( 6, absint( $attributes['columns'] ) ) ) : 3;
	$link_to_post = ! empty( $attributes['linkToPost'] );

	return array(
		'flex_direction'  => $flex_direction,
		'justify_content' => $justify_content,
		'columns'         => $columns,
		'link_to_post'    => $link_to_post,
	);
}

/**
 * Safely retrieves the PODS field type slug across different PODS API return formats.
 *
 * @param Pods|false|null $pod        Initialized PODS object.
 * @param string          $field_name Field slug to inspect.
 * @return string Field type slug (e.g. 'text', 'number', 'pick', 'file') or empty string.
 */
function pods_block_suite_get_field_type( $pod, $field_name ) {
	if ( ! $pod || empty( $field_name ) ) {
		return '';
	}

	if ( method_exists( $pod, 'valid' ) && ! $pod->valid() ) {
		return '';
	}

	$field_info = $pod->fields( $field_name );

	if ( is_array( $field_info ) && isset( $field_info['type'] ) ) {
		return (string) $field_info['type'];
	}

	if ( is_object( $field_info ) ) {
		if ( method_exists( $field_info, 'get_type' ) ) {
			return (string) $field_info->get_type();
		}
		if ( isset( $field_info['type'] ) ) {
			return (string) $field_info['type'];
		}
	}

	return '';
}

/**
 * Returns the color families shown in the visitor filter.
 *
 * @return array<string, string> Color IDs mapped to translated labels.
 */
function pods_block_suite_get_color_list() {
	$colors = array(
		'red'    => __( 'Red', 'pods-block-suite' ),
		'orange' => __( 'Orange', 'pods-block-suite' ),
		'yellow' => __( 'Yellow', 'pods-block-suite' ),
		'green'  => __( 'Green', 'pods-block-suite' ),
		'cyan'   => __( 'Cyan', 'pods-block-suite' ),
		'blue'   => __( 'Blue', 'pods-block-suite' ),
		'purple' => __( 'Purple', 'pods-block-suite' ),
		'gray'   => __( 'Gray', 'pods-block-suite' ),
		'white'  => __( 'White', 'pods-block-suite' ),
		'black'  => __( 'Black', 'pods-block-suite' ),
	);

	/**
	 * Filters the color IDs and labels displayed in the visitor filter.
	 *
	 * @param array<string, string> $colors Color IDs mapped to translated labels.
	 */
	return apply_filters( 'pods_block_suite_color_list', $colors );
}

/**
 * Checks whether a post's content and fields can be displayed to this visitor.
 *
 * @param WP_Post $post Target post.
 * @return bool Whether the post is accessible.
 */
function pods_block_suite_can_render_post( WP_Post $post ) {
	if ( post_password_required( $post ) ) {
		return false;
	}

	if ( is_post_publicly_viewable( $post ) ) {
		return true;
	}

	$status = get_post_status_object( get_post_status( $post ) );
	if ( ! $status || $status->internal ) {
		return false;
	}

	if ( $status->private ) {
		return current_user_can( 'read_post', $post->ID );
	}

	return ( $status->protected || $status->public ) && current_user_can( 'edit_post', $post->ID );
}

/**
 * Renders a block's inner blocks within a specific post context (postId and postType),
 * temporarily updating global $post and restoring it cleanly afterward.
 *
 * @param WP_Block $block     Parent container block instance.
 * @param int      $post_id   Target post ID for inner block context.
 * @param string   $post_type Target post type slug for inner block context.
 * @return string Rendered inner blocks HTML.
 */
function pods_block_suite_render_contextual_inner_blocks( WP_Block $block, $post_id, $post_type ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || empty( $post_type ) || empty( $block->inner_blocks ) ) {
		return '';
	}

	global $post;
	$original_post = $post;

	$target_post = get_post( $post_id );
	if ( ( post_type_exists( $post_type ) && ( ! $target_post || $target_post->post_type !== $post_type ) )
		|| ( $target_post && ! pods_block_suite_can_render_post( $target_post ) )
	) {
		return '';
	}

	if ( $target_post ) {
		$post = $target_post;
		setup_postdata( $post );
	}

	$context_filter = static function ( $context ) use ( $post_id, $post_type ) {
		$context['postId']   = $post_id;
		$context['postType'] = $post_type;
		return $context;
	};

	add_filter( 'render_block_context', $context_filter, 10, 1 );

	$rendered_html = '';
	foreach ( $block->inner_blocks as $inner_block ) {
		$contextual_block = new WP_Block(
			$inner_block->parsed_block,
			array(
				'postId'   => $post_id,
				'postType' => $post_type,
			)
		);
		$rendered_html   .= $contextual_block->render();
	}

	remove_filter( 'render_block_context', $context_filter, 10 );

	// Restore original global $post state
	$post = $original_post;
	if ( $original_post ) {
		setup_postdata( $original_post );
	} else {
		wp_reset_postdata();
	}

	return $rendered_html;
}