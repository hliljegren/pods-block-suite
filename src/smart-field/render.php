<?php
/**
 * Renders the PODS Smart Field block on the frontend and inside the editor's ServerSideRender.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Empty for dynamic leaf blocks.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods' ) ) {
	return;
}

$selected_pod          = isset( $attributes['selectedPod'] ) ? sanitize_key( $attributes['selectedPod'] ) : '';
$field_name            = isset( $attributes['fieldName'] ) ? sanitize_text_field( $attributes['fieldName'] ) : '';
$display_as            = isset( $attributes['displayAs'] ) ? sanitize_key( $attributes['displayAs'] ) : 'auto';
$layout_mode           = isset( $attributes['layoutMode'] ) ? sanitize_key( $attributes['layoutMode'] ) : 'column';
$allowed_justify       = array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' );
$raw_justify           = isset( $attributes['justifyContent'] ) ? sanitize_key( $attributes['justifyContent'] ) : 'center';
$justify_content       = in_array( $raw_justify, $allowed_justify, true ) ? $raw_justify : 'center';$limit 	               = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 0;$image_size            = isset( $attributes['imageSize'] ) ? sanitize_key( $attributes['imageSize'] ) : 'large';
$link_to_media         = ! empty( $attributes['linkToMedia'] );
$link_to_related       = isset( $attributes['linkToRelated'] ) ? (bool) $attributes['linkToRelated'] : true;
$related_display_field = ! empty( $attributes['relatedDisplayField'] ) ? sanitize_text_field( $attributes['relatedDisplayField'] ) : 'post_title';
$render_as_link        = isset( $attributes['renderAsLink'] ) ? (bool) $attributes['renderAsLink'] : true;
$link_text             = isset( $attributes['linkText'] ) ? sanitize_text_field( $attributes['linkText'] ) : '';
$link_new_tab          = ! empty( $attributes['linkNewTab'] );
$social_color_mode     = isset( $attributes['socialColorMode'] ) && 'inherit' === $attributes['socialColorMode'] ? 'inherit' : 'brand';
$social_size           = isset( $attributes['socialSize'] ) ? max( 16, min( 120, absint( $attributes['socialSize'] ) ) ) : 36;
$social_border_radius  = isset( $attributes['socialBorderRadius'] ) ? max( 0, min( 50, absint( $attributes['socialBorderRadius'] ) ) ) : 50;
$color_shape           = isset( $attributes['colorShape'] ) ? sanitize_key( $attributes['colorShape'] ) : 'square';
$color_size            = isset( $attributes['colorSize'] ) ? max( 12, min( 200, absint( $attributes['colorSize'] ) ) ) : 32;
$show_color_label      = ! empty( $attributes['showColorLabel'] );
$html_tag              = isset( $attributes['htmlTag'] ) ? sanitize_key( $attributes['htmlTag'] ) : 'div';

$allowed_tags = array( 'div', 'p', 'h2', 'h3', 'span' );
if ( ! in_array( $html_tag, $allowed_tags, true ) ) {
	$html_tag = 'div';
}

$allowed_layouts = array( 'column', 'row', 'ul', 'ol' );
if ( ! in_array( $layout_mode, $allowed_layouts, true ) ) {
	$layout_mode = 'column';
}

$allowed_shapes = array( 'square', 'circle', 'text' );
if ( ! in_array( $color_shape, $allowed_shapes, true ) ) {
	$color_shape = 'square';
}

if ( empty( $field_name ) ) {
	return;
}

$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

// 1. Retrieve Post ID and Post Type from block context (or ServerSideRender query args)
$post_id   = $block->context['postId'] ?? 0;
$post_type = $block->context['postType'] ?? '';

if ( ! $post_id && isset( $_GET['context_post_id'] ) ) {
	$post_id = absint( $_GET['context_post_id'] );
}
if ( ! $post_type && isset( $_GET['context_post_type'] ) ) {
	$post_type = sanitize_key( $_GET['context_post_type'] );
}

if ( ! $post_id ) {
	$post_id = get_the_ID();
}
if ( $post_id && ! $post_type ) {
	$post_type = get_post_type( $post_id );
}

// Editor Preview Fallback: If inside a list container (where current page post_type differs from selectedPod),
// fetch a sample published item from selectedPod so the editor shows a live preview.
if ( $is_editor_preview && ! empty( $selected_pod ) && $post_type !== $selected_pod ) {
	$query_args = array(
		'post_type'      => $selected_pod,
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	);

	if ( 'post_thumbnail' === $field_name ) {
		$query_args['meta_key'] = '_thumbnail_id';
	}

	$sample_posts = get_posts( $query_args );
	if ( empty( $sample_posts ) && isset( $query_args['meta_key'] ) ) {
		unset( $query_args['meta_key'] );
		$sample_posts = get_posts( $query_args );
	}

	if ( ! empty( $sample_posts ) ) {
		$post_id   = $sample_posts[0]->ID;
		$post_type = $selected_pod;
	}
}

if ( ! $post_id || ! $post_type ) {
	return;
}

// 2. Initialize the PODS object
$pod = pods( $post_type, $post_id );

// 3. Detect field type and determine the effective display mode
$field_info = ( $pod && $pod->exists() ) ? $pod->fields( $field_name ) : null;
$field_type = '';

if ( is_array( $field_info ) && isset( $field_info['type'] ) ) {
	$field_type = $field_info['type'];
} elseif ( is_object( $field_info ) ) {
	if ( method_exists( $field_info, 'get_type' ) ) {
		$field_type = $field_info->get_type();
	} elseif ( isset( $field_info['type'] ) ) {
		$field_type = $field_info['type'];
	}
}

$effective_mode = 'text';
if ( 'auto' !== $display_as && in_array( $display_as, array( 'media', 'social', 'oembed', 'color', 'relation', 'link', 'text' ), true ) ) {
	$effective_mode = $display_as;
} elseif ( 'post_thumbnail' === $field_name || in_array( $field_type, array( 'file', 'media', 'avatar' ), true ) ) {
	$effective_mode = 'media';
} elseif ( 'oembed' === $field_type ) {
	$effective_mode = 'oembed';
} elseif ( 'color' === $field_type ) {
	$effective_mode = 'color';
} elseif ( 'pick' === $field_type ) {
	$effective_mode = 'relation';
} elseif ( 'website' === $field_type || 'permalink' === $field_name ) {
	$effective_mode = 'link';
}

/**
 * Helper closure to wrap an array of HTML items into either a semantic list (<ul>/<ol>) or Flexbox items,
 * respecting the optional max items limit.
 */
$format_items_output = function ( array $items ) use ( $layout_mode, $limit ) {
	if ( empty( $items ) ) {
		return '';
	}

	if ( $limit > 0 ) {
		$items = array_slice( $items, 0, $limit );
	}

	if ( 'ul' === $layout_mode || 'ol' === $layout_mode ) {
		$list_items = array_map(
			function ( $item ) {
				return '<li>' . $item . '</li>';
			},
			$items
		);
		return sprintf( '<%1$s class="pods-smart-field-list">%2$s</%1$s>', $layout_mode, implode( "\n", $list_items ) );
	}

	if ( count( $items ) > 1 ) {
		$wrapped = array_map(
			function ( $item ) {
				return '<div class="pods-smart-field-item">' . $item . '</div>';
			},
			$items
		);
		return implode( "\n", $wrapped );
	}

	return $items[0];
};

/**
 * Helper closure to detect a social platform from a URL and return its label, brand color, and SVG icon.
 */
$get_social_platform_data = function ( $url ) {
	$lower_url = strtolower( $url );
	$host      = wp_parse_url( $lower_url, PHP_URL_HOST ) ?: '';

	$platforms = array(
		'instagram' => array(
			'match' => array( 'instagram.com', 'instagr.am' ),
			'label' => 'Instagram',
			'color' => '#E4405F',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
		),
		'facebook'  => array(
			'match' => array( 'facebook.com', 'fb.com', 'fb.me' ),
			'label' => 'Facebook',
			'color' => '#1877F2',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>',
		),
		'x'         => array(
			'match' => array( 'x.com', 'twitter.com' ),
			'label' => 'X (Twitter)',
			'color' => '#000000',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
		),
		'linkedin'  => array(
			'match' => array( 'linkedin.com' ),
			'label' => 'LinkedIn',
			'color' => '#0A66C2',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>',
		),
		'github'    => array(
			'match' => array( 'github.com' ),
			'label' => 'GitHub',
			'color' => '#181717',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
		),
		'youtube'   => array(
			'match' => array( 'youtube.com', 'youtu.be' ),
			'label' => 'YouTube',
			'color' => '#FF0000',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
		),
		'tiktok'    => array(
			'match' => array( 'tiktok.com' ),
			'label' => 'TikTok',
			'color' => '#000000',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
		),
		'bluesky'   => array(
			'match' => array( 'bsky.app', 'bsky.social' ),
			'label' => 'Bluesky',
			'color' => '#0285FF',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.383 3.364.136-.02.275-.039.415-.056-.138.022-.276.04-.415.056-3.912.58-7.387 2.005-2.83 7.078 5.013 5.19 6.87-1.113 7.823-4.308.953 3.195 2.05 9.271 7.733 4.308 4.267-4.308 1.172-6.498-2.74-7.078a8.741 8.741 0 0 1-.415-.056c.14.017.279.036.415.056 2.67.297 5.568-.628 6.383-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.206-.659-.298-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8Z"/></svg>',
		),
		'mastodon'  => array(
			'match' => array( 'mastodon.social', 'mastodon.online', 'mstdn.social' ),
			'label' => 'Mastodon',
			'color' => '#6364FF',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.268 5.313c-.35-2.578-2.617-4.61-5.304-5.004C17.51.242 15.792 0 11.813 0h-.03c-3.98 0-4.835.242-5.288.309C3.882.692 1.496 2.518.917 5.127.64 6.412.61 7.837.661 9.143c.074 1.874.088 3.745.26 5.611.118 1.24.325 2.47.62 3.68.55 2.237 2.777 4.098 4.96 4.857 2.336.792 4.849.923 7.256.38.265-.061.527-.132.786-.213.585-.184 1.27-.39 1.774-.753a.057.057 0 0 0 .023-.043v-1.809a.052.052 0 0 0-.02-.041.053.053 0 0 0-.046-.01 20.282 20.282 0 0 1-4.709.545c-2.73 0-3.463-1.284-3.674-1.818a5.593 5.593 0 0 1-.319-1.433.053.053 0 0 1 .066-.054c1.517.363 3.072.546 4.632.546.376 0 .75 0 1.125-.01 1.57-.044 3.224-.124 4.768-.422.038-.008.077-.015.11-.024 2.435-.464 4.753-1.92 4.989-5.604.008-.145.03-1.52.03-1.67.002-.512.167-3.63-.024-5.545zm-3.748 9.195h-2.561V8.29c0-1.309-.55-1.976-1.67-1.976-1.23 0-1.846.79-1.846 2.35v3.403h-2.546V8.663c0-1.56-.617-2.35-1.848-2.35-1.112 0-1.668.668-1.67 1.977v6.218H4.822V8.102c0-1.31.337-2.35 1.011-3.12.696-.77 1.608-1.164 2.74-1.164 1.311 0 2.302.5 2.962 1.498l.638 1.06.638-1.06c.66-.999 1.65-1.498 2.96-1.498 1.13 0 2.043.395 2.74 1.164.675.77 1.012 1.81 1.012 3.12z"/></svg>',
		),
		'pinterest' => array(
			'match' => array( 'pinterest.com', 'pin.it' ),
			'label' => 'Pinterest',
			'color' => '#BD081C',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>',
		),
		'vimeo'     => array(
			'match' => array( 'vimeo.com' ),
			'label' => 'Vimeo',
			'color' => '#1AB7EA',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197a315.065 315.065 0 0 0 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.06 3.628 1.664 3.493 4.797l-.013.01z"/></svg>',
		),
	);

	// Check mailto links
	if ( strpos( $lower_url, 'mailto:' ) === 0 ) {
		return array(
			'label' => __( 'Email', 'pods-block-suite' ),
			'color' => '#555555',
			'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
		);
	}

	foreach ( $platforms as $platform ) {
		foreach ( $platform['match'] as $domain ) {
			if ( false !== strpos( $host, $domain ) ) {
				return $platform;
			}
		}
	}

	// Default Website / Globe fallback
	return array(
		'label' => __( 'Website', 'pods-block-suite' ),
		'color' => '#2271b1',
		'svg'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>',
	);
};

$rendered_content = '';

// 4A. MODE: MEDIA (Featured Image, PODS Images, Video, Audio, Files)
if ( 'media' === $effective_mode ) {
	$attachment_ids = array();

	if ( 'post_thumbnail' === $field_name ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$attachment_ids[] = absint( $thumb_id );
		}
	} elseif ( $pod && $pod->exists() ) {
		$raw_files = $pod->field( $field_name );

		if ( is_numeric( $raw_files ) ) {
			$attachment_ids[] = absint( $raw_files );
		} elseif ( is_array( $raw_files ) ) {
			if ( isset( $raw_files['ID'] ) ) {
				$attachment_ids[] = absint( $raw_files['ID'] );
			} else {
				foreach ( $raw_files as $file_item ) {
					if ( is_array( $file_item ) && isset( $file_item['ID'] ) ) {
						$attachment_ids[] = absint( $file_item['ID'] );
					} elseif ( is_numeric( $file_item ) ) {
						$attachment_ids[] = absint( $file_item );
					} elseif ( is_string( $file_item ) && filter_var( $file_item, FILTER_VALIDATE_URL ) ) {
						$found_id = attachment_url_to_postid( $file_item );
						if ( $found_id ) {
							$attachment_ids[] = $found_id;
						}
					}
				}
			}
		}
	}

	$attachment_ids = array_filter( $attachment_ids );

	if ( ! empty( $attachment_ids ) ) {
		$items_html = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$mime_type = get_post_mime_type( $attachment_id );
			$file_url  = wp_get_attachment_url( $attachment_id );
			$item_html = '';

			if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
				$img_html = wp_get_attachment_image( $attachment_id, $image_size );
				if ( $link_to_media && $file_url ) {
					$item_html = sprintf(
						'<a href="%s" class="pods-smart-field-media-link">%s</a>',
						esc_url( $file_url ),
						$img_html
					);
				} else {
					$item_html = $img_html;
				}
			} elseif ( $mime_type && strpos( $mime_type, 'video/' ) === 0 && $file_url ) {
				$item_html = sprintf( '<video controls preload="metadata" src="%s"></video>', esc_url( $file_url ) );
			} elseif ( $mime_type && strpos( $mime_type, 'audio/' ) === 0 && $file_url ) {
				$item_html = sprintf( '<audio controls preload="metadata" src="%s"></audio>', esc_url( $file_url ) );
			} elseif ( $file_url ) {
				$file_title = get_the_title( $attachment_id ) ?: basename( $file_url );
				$item_html  = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $file_url ),
					esc_html( $file_title )
				);
			}

			if ( ! empty( $item_html ) ) {
				$items_html[] = $item_html;
			}
		}

		$rendered_content = $format_items_output( $items_html );
	}

// 4B. MODE: SOCIAL ICONS (Single or repeatable URLs rendered as platform SVG icons)
} elseif ( 'social' === $effective_mode ) {
	$raw_urls = ( 'permalink' === $field_name ) ? get_permalink( $post_id ) : ( ( $pod && $pod->exists() ) ? $pod->field( $field_name ) : '' );
	$url_list = array();

	if ( is_string( $raw_urls ) && '' !== trim( $raw_urls ) ) {
		$url_list[] = trim( $raw_urls );
	} elseif ( is_array( $raw_urls ) ) {
		foreach ( $raw_urls as $u_item ) {
			if ( is_string( $u_item ) && '' !== trim( $u_item ) ) {
				$url_list[] = trim( $u_item );
			} elseif ( is_array( $u_item ) && ! empty( $u_item['url'] ) ) {
				$url_list[] = trim( $u_item['url'] );
			}
		}
	}

	if ( ! empty( $url_list ) ) {
		$items_html   = array();
		$target_attrs = $link_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

		foreach ( $url_list as $single_url ) {
			$clean_url = trim( wp_strip_all_tags( wp_unslash( $single_url ) ) );
			if ( empty( $clean_url ) || '0' === $clean_url ) {
				continue;
			}

			$platform   = $get_social_platform_data( $clean_url );
			$inline_css = sprintf(
				'width:%1$dpx;height:%1$dpx;border-radius:%2$d%%;--pods-brand-color:%3$s;',
				$social_size,
				$social_border_radius,
				esc_attr( $platform['color'] )
			);

			$items_html[] = sprintf(
				'<a href="%1$s" class="pods-smart-field-social-icon is-style-%2$s" style="%3$s" aria-label="%4$s" title="%4$s"%5$s>%6$s</a>',
				esc_url( $clean_url ),
				esc_attr( $social_color_mode ),
				$inline_css,
				esc_attr( $platform['label'] ),
				$target_attrs,
				$platform['svg']
			);
		}

		$rendered_content = $format_items_output( $items_html );
	}

// 4C. MODE: OEMBED (YouTube, Vimeo, SoundCloud, etc.)
} elseif ( 'oembed' === $effective_mode && $pod && $pod->exists() ) {
	$raw_embed = $pod->field( $field_name );
	if ( is_array( $raw_embed ) ) {
		$raw_embed = reset( $raw_embed );
	}

	$embed_url = is_string( $raw_embed ) ? trim( wp_strip_all_tags( wp_unslash( $raw_embed ) ) ) : '';

	if ( ! empty( $embed_url ) && '0' !== $embed_url ) {
		$embed_html = wp_oembed_get( $embed_url );
		if ( $embed_html ) {
			$rendered_content = sprintf( '<div class="pods-smart-field-oembed">%s</div>', $embed_html );
		} else {
			$rendered_content = sprintf(
				'<a href="%1$s" class="pods-smart-field-url-link" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $embed_url ),
				esc_html( $embed_url )
			);
		}
	}

// 4D. MODE: COLOR PICKER (Square swatch, Circle swatch, or Hex text)
} elseif ( 'color' === $effective_mode && $pod && $pod->exists() ) {
	$raw_color = $pod->field( $field_name );
	if ( is_array( $raw_color ) ) {
		$raw_color = reset( $raw_color );
	}

	$color_str = is_string( $raw_color ) ? trim( $raw_color ) : '';
	if ( '' !== $color_str && '#' !== $color_str[0] ) {
		$color_str = '#' . $color_str;
	}

	$sanitized_hex = sanitize_hex_color( $color_str );

	if ( ! empty( $sanitized_hex ) ) {
		if ( 'text' === $color_shape ) {
			$rendered_content = sprintf(
				'<span class="pods-smart-field-color-label">%s</span>',
				esc_html( $sanitized_hex )
			);
		} else {
			$swatch_html = sprintf(
				'<span class="pods-smart-field-color-swatch is-shape-%1$s" style="background-color:%2$s;width:%3$dpx;height:%3$dpx;" title="%2$s" aria-label="%2$s"></span>',
				esc_attr( $color_shape ),
				esc_attr( $sanitized_hex ),
				$color_size
			);

			$label_html = $show_color_label
				? sprintf( '<span class="pods-smart-field-color-label">%s</span>', esc_html( $sanitized_hex ) )
				: '';

			$rendered_content = sprintf(
				'<span class="pods-smart-field-color-wrap">%1$s%2$s</span>',
				$swatch_html,
				$label_html
			);
		}
	}

// 4E. MODE: RELATIONSHIP (Single and one-to-many relations)
} elseif ( 'relation' === $effective_mode && $pod && $pod->exists() ) {
	$raw_related = $pod->field( $field_name );
	$related_ids = array();

	if ( is_numeric( $raw_related ) ) {
		$related_ids[] = absint( $raw_related );
	} elseif ( is_array( $raw_related ) ) {
		if ( isset( $raw_related['ID'] ) ) {
			$related_ids[] = absint( $raw_related['ID'] );
		} else {
			foreach ( $raw_related as $rel_item ) {
				if ( is_array( $rel_item ) && isset( $rel_item['ID'] ) ) {
					$related_ids[] = absint( $rel_item['ID'] );
				} elseif ( is_numeric( $rel_item ) ) {
					$related_ids[] = absint( $rel_item );
				}
			}
		}
	}

	$related_ids = array_filter( $related_ids );

	if ( ! empty( $related_ids ) ) {
		$items_html = array();

		foreach ( $related_ids as $rel_id ) {
			$rel_post_type = get_post_type( $rel_id );
			if ( ! $rel_post_type ) {
				continue;
			}

			$label_text = '';
			if ( 'post_title' === $related_display_field ) {
				$label_text = get_the_title( $rel_id );
			} else {
				$rel_pod = pods( $rel_post_type, $rel_id );
				if ( $rel_pod && $rel_pod->exists() ) {
					$label_text = $rel_pod->display( $related_display_field );
				}
				if ( empty( $label_text ) ) {
					$label_text = get_the_title( $rel_id );
				}
			}

			if ( empty( $label_text ) ) {
				continue;
			}

			$permalink = $link_to_related ? get_permalink( $rel_id ) : '';
			if ( $link_to_related && $permalink ) {
				$items_html[] = sprintf(
					'<a href="%s" class="pods-smart-field-relation-link">%s</a>',
					esc_url( $permalink ),
					wp_kses_post( $label_text )
				);
			} else {
				$items_html[] = sprintf(
					'<span class="pods-smart-field-relation-text">%s</span>',
					wp_kses_post( $label_text )
				);
			}
		}

		$rendered_content = $format_items_output( $items_html );
	}

// 4F. MODE: URL / WEBSITE LINK
} elseif ( 'link' === $effective_mode ) {
	$raw_url = '';
	if ( 'permalink' === $field_name ) {
		$raw_url = get_permalink( $post_id );
	} elseif ( $pod && $pod->exists() ) {
		$raw_url = $pod->field( $field_name );
	}

	if ( is_array( $raw_url ) ) {
		$raw_url = reset( $raw_url );
	}

	$url_string = is_string( $raw_url ) ? trim( $raw_url ) : '';

	if ( ! empty( $url_string ) ) {
		if ( $render_as_link ) {
			$final_label  = ! empty( $link_text ) ? $link_text : $url_string;
			$target_attrs = $link_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

			$rendered_content = sprintf(
				'<a href="%1$s" class="pods-smart-field-url-link"%2$s>%3$s</a>',
				esc_url( $url_string ),
				$target_attrs,
				esc_html( $final_label )
			);
		} else {
			$rendered_content = sprintf(
				'<%1$s class="pods-smart-field-text">%2$s</%1$s>',
				$html_tag,
				esc_html( $url_string )
			);
		}
	}

// 4G. MODE: STANDARD WORDPRESS FIELDS & PODS TEXT/GENERAL FIELDS
} else {
	$display_value = '';

	switch ( $field_name ) {
		case 'post_title':
			$display_value = get_the_title( $post_id );
			break;
		case 'post_content':
			$raw_content   = get_post_field( 'post_content', $post_id );
			$display_value = apply_filters( 'the_content', $raw_content );
			break;
		case 'post_excerpt':
			$display_value = has_excerpt( $post_id )
				? get_the_excerpt( $post_id )
				: wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 );
			break;
		case 'post_date':
			$display_value = get_the_date( '', $post_id );
			break;
		case 'ID':
			$display_value = (string) $post_id;
			break;
		case 'post_author':
			$author_id     = get_post_field( 'post_author', $post_id );
			$display_value = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
			break;
		case 'permalink':
			$display_value = get_permalink( $post_id );
			break;
		default:
			if ( $pod && $pod->exists() ) {
				$display_value = $pod->display( $field_name );
			}
			break;
	}

	if ( ! empty( $display_value ) || '0' === (string) $display_value ) {
		$rendered_content = sprintf(
			'<%1$s class="pods-smart-field-text">%2$s</%1$s>',
			$html_tag,
			wp_kses_post( $display_value )
		);
	}
}

// 5. Empty state handling
if ( empty( $rendered_content ) ) {
	if ( $is_editor_preview ) {
		echo '<div class="pods-smart-field-empty" style="opacity:0.6;font-style:italic;">' .
			sprintf(
				/* translators: %s: PODS field slug */
				esc_html__( 'PODS Smart Field: "%s" is empty or could not be found.', 'pods-block-suite' ),
				esc_html( $field_name )
			) .
			'</div>';
	}
	return;
}

// 6. Render outer wrapper with active layout class
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => sprintf( 'pods-smart-field is-layout-%s', esc_attr( $layout_mode ) ),
		'style' => sprintf( '--pods-justify-content: %s;', esc_attr( $justify_content ) ),
	)
);

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes,
	$rendered_content
);