<?php
/**
 * Renders the PODS Item Container block on the frontend.
 *
 * @var array    $attributes Saved block attributes.
 * @var string   $content    Fallback inner content.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods' ) ) {
	return;
}

$raw_manual_id  = isset( $attributes['customPostId'] ) && $attributes['customPostId'] > 0
	? $attributes['customPostId']
	: ( $attributes['postId'] ?? 0 );
$manual_post_id = absint( $raw_manual_id );
$link_to_post   = ! empty( $attributes['linkToPost'] );

// Resolve target post ID (manual override takes precedence over page/loop context)
$target_id = $manual_post_id > 0
	? $manual_post_id
	: ( $block->context['postId'] ?? get_the_ID() );

if ( ! $target_id ) {
	return;
}

$target_post = get_post( $target_id );
if ( ! $target_post ) {
	return;
}

$target_type = $target_post->post_type;

// Render all inner blocks within the resolved post context using our shared helper
$inner_html = pods_block_suite_render_contextual_inner_blocks( $block, $target_id, $target_type );

if ( empty( $inner_html ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pods-item-container',
	)
);

if ( $link_to_post ) {
	$permalink = get_permalink( $target_id );
	if ( $permalink ) {
		printf(
			'<div %1$s><a href="%2$s" class="pods-item-container-link">%3$s</a></div>',
			$wrapper_attributes,
			esc_url( $permalink ),
			$inner_html
		);
		return;
	}
}

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes,
	$inner_html
);