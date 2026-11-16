<?php
/**
 * Renders the PODS Related Item Container block on the frontend.
 *
 * @var array    $attributes Saved block attributes.
 * @var string   $content    Fallback inner content.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods_field' ) ) {
	return;
}

$relation_field = isset( $attributes['relationField'] ) ? sanitize_text_field( $attributes['relationField'] ) : '';
$max_items      = isset( $attributes['maxItems'] ) ? absint( $attributes['maxItems'] ) : 0;
$flex_direction = isset( $attributes['flexDirection'] ) && 'row' === $attributes['flexDirection'] ? 'row' : 'column';
$columns        = isset( $attributes['columns'] ) ? max( 1, min( 6, absint( $attributes['columns'] ) ) ) : 3;
$link_to_post   = ! empty( $attributes['linkToPost'] );

if ( empty( $relation_field ) ) {
	return;
}

// 1. Retrieve current source Post ID and Post Type from context
$source_post_id   = $block->context['postId'] ?? get_the_ID();
$source_post_type = $block->context['postType'] ?? get_post_type( $source_post_id );

if ( ! $source_post_id || ! $source_post_type ) {
	return;
}

// 2. Fetch related field value via PODS API
$raw_related = pods_field( $source_post_type, $source_post_id, $relation_field );

if ( empty( $raw_related ) ) {
	return;
}

// 3. Normalize result into a clean array of Post IDs (supports both single and one-to-many relations)
$related_ids = array();

if ( is_numeric( $raw_related ) ) {
	$related_ids[] = absint( $raw_related );
} elseif ( is_array( $raw_related ) ) {
	if ( isset( $raw_related['ID'] ) ) {
		$related_ids[] = absint( $raw_related['ID'] );
	} else {
		foreach ( $raw_related as $item ) {
			if ( is_array( $item ) && isset( $item['ID'] ) ) {
				$related_ids[] = absint( $item['ID'] );
			} elseif ( is_numeric( $item ) ) {
				$related_ids[] = absint( $item );
			}
		}
	}
}

$related_ids = array_filter( $related_ids );

if ( empty( $related_ids ) ) {
	return;
}

// 4. Limit the number of items if maxItems is greater than 0
if ( $max_items > 0 ) {
	$related_ids = array_slice( $related_ids, 0, $max_items );
}

// 5. Loop through each related post and render inner blocks with updated context
$output_html = '';

foreach ( $related_ids as $related_id ) {
	$related_post_type = get_post_type( $related_id );
	if ( ! $related_post_type ) {
		continue;
	}

	$item_html = '';
	if ( ! empty( $block->inner_blocks ) ) {
		foreach ( $block->inner_blocks as $inner_block ) {
			$new_context = array_merge(
				$inner_block->context,
				array(
					'postId'   => $related_id,
					'postType' => $related_post_type,
				)
			);

			$contextual_block = new WP_Block( $inner_block->parsed_block, $new_context );
			$item_html       .= $contextual_block->render();
		}
	}

	if ( ! empty( $item_html ) ) {
		$permalink = $link_to_post ? get_permalink( $related_id ) : '';

		if ( $link_to_post && $permalink ) {
			$output_html .= sprintf(
				'<a href="%1$s" class="pods-related-item pods-related-item-link" data-related-id="%2$d">%3$s</a>',
				esc_url( $permalink ),
				$related_id,
				$item_html
			);
		} else {
			$output_html .= sprintf(
				'<div class="pods-related-item" data-related-id="%1$d">%2$s</div>',
				$related_id,
				$item_html
			);
		}
	}
}

if ( empty( $output_html ) ) {
	return;
}

// 6. Render outer wrapper with direction class and column CSS variable
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => sprintf( 'pods-related-item-container is-direction-%s', esc_attr( $flex_direction ) ),
		'style' => sprintf( '--pods-columns: %d;', $columns ),
	)
);

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes,
	$output_html
);