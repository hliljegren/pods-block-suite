<?php
/**
 * Renders the PODS Item Container block on the frontend.
 *
 * @var array    $attributes Saved block attributes.
 * @var string   $content    Pre-rendered inner blocks HTML (fallback).
 * @var WP_Block $block      Full block instance including context and inner blocks.
 */

// Use custom Post ID if set, otherwise fall back to current context/post
$custom_post_id = isset( $attributes['customPostId'] ) ? absint( $attributes['customPostId'] ) : 0;
$target_post_id = $custom_post_id > 0 ? $custom_post_id : ( $block->context['postId'] ?? get_the_ID() );

// Retrieve the actual post_type for the target Post ID
$target_post_type = $target_post_id ? get_post_type( $target_post_id ) : ( $block->context['postType'] ?? 'post' );

if ( ! $target_post_id || ! $target_post_type ) {
	return;
}

// Render each inner block with overridden postId and postType context
$inner_blocks_html = '';
if ( ! empty( $block->inner_blocks ) ) {
	foreach ( $block->inner_blocks as $inner_block ) {
		$new_context = array_merge(
			$inner_block->context,
			array(
				'postId'   => $target_post_id,
				'postType' => $target_post_type,
			)
		);

		$contextual_block   = new WP_Block( $inner_block->parsed_block, $new_context );
		$inner_blocks_html .= $contextual_block->render();
	}
}

$wrapper_attributes = get_block_wrapper_attributes();

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes,
	$inner_blocks_html
);