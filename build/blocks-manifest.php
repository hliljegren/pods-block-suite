<?php
// This file is generated. Do not modify it manually.
return array(
	'item-container' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pods-block-suite/item-container',
		'version' => '1.2.0',
		'title' => 'PODS Item Container',
		'category' => 'widgets',
		'icon' => 'database',
		'description' => 'A container block that provides a specific (or current) Post ID context to inner blocks such as PODS Smart Field.',
		'attributes' => array(
			'customPostId' => array(
				'type' => 'number',
				'default' => 0
			),
			'resolvedPostType' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkToPost' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'providesContext' => array(
			'postId' => 'customPostId',
			'postType' => 'resolvedPostType'
		),
		'supports' => array(
			'html' => false,
			'layout' => true,
			'color' => array(
				'background' => true,
				'text' => true
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			)
		),
		'textdomain' => 'pods-block-suite',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'render' => 'file:./render.php'
	),
	'item-list-container' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pods-block-suite/item-list-container',
		'version' => '1.0.0',
		'title' => 'PODS Item List Container',
		'category' => 'widgets',
		'icon' => 'list-view',
		'description' => 'Lists items from a POD with optional frontend visitor filters, pagination, and flexible grid layout.',
		'attributes' => array(
			'selectedPod' => array(
				'type' => 'string',
				'default' => ''
			),
			'limit' => array(
				'type' => 'number',
				'default' => 12
			),
			'orderBy' => array(
				'type' => 'string',
				'default' => 't.post_date'
			),
			'orderDir' => array(
				'type' => 'string',
				'default' => 'DESC'
			),
			'enablePagination' => array(
				'type' => 'boolean',
				'default' => true
			),
			'filterFields' => array(
				'type' => 'array',
				'default' => array(
					
				),
				'items' => array(
					'type' => 'string'
				)
			),
			'enableSearch' => array(
				'type' => 'boolean',
				'default' => false
			),
			'flexDirection' => array(
				'type' => 'string',
				'default' => 'row'
			),
			'justifyContent' => array(
				'type' => 'string',
				'default' => 'center'
			),
			'columns' => array(
				'type' => 'number',
				'default' => 3
			),
			'linkToPost' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'supports' => array(
			'html' => false,
			'layout' => true,
			'color' => array(
				'background' => true,
				'text' => true
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			)
		),
		'textdomain' => 'pods-block-suite',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'related-item-container' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pods-block-suite/related-item-container',
		'version' => '1.1.0',
		'title' => 'PODS Related Item Container',
		'category' => 'widgets',
		'icon' => 'networking',
		'description' => 'Loops through a related PODS field (single or one-to-many) and renders each item in a flexible layout or as clickable cards.',
		'attributes' => array(
			'selectedPod' => array(
				'type' => 'string',
				'default' => ''
			),
			'relationshipField' => array(
				'type' => 'string',
				'default' => ''
			),
			'relationField' => array(
				'type' => 'string',
				'default' => ''
			),
			'limit' => array(
				'type' => 'number',
				'default' => 0
			),
			'maxItems' => array(
				'type' => 'number',
				'default' => 0
			),
			'orderBy' => array(
				'type' => 'string',
				'default' => 'post_date'
			),
			'orderDir' => array(
				'type' => 'string',
				'default' => 'DESC'
			),
			'flexDirection' => array(
				'type' => 'string',
				'default' => 'column'
			),
			'justifyContent' => array(
				'type' => 'string',
				'default' => 'center'
			),
			'columns' => array(
				'type' => 'number',
				'default' => 3
			),
			'linkToPost' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'layout' => true,
			'color' => array(
				'background' => true,
				'text' => true
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			)
		),
		'textdomain' => 'pods-block-suite',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'smart-field' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pods-block-suite/smart-field',
		'version' => '1.1.0',
		'title' => 'PODS Smart Field',
		'category' => 'widgets',
		'icon' => 'media-default',
		'description' => 'Intelligent field block with dynamic POD and field selectors, automatically rendering media, relationships, oEmbeds, colors, social icons, links, and text.',
		'attributes' => array(
			'selectedPod' => array(
				'type' => 'string',
				'default' => ''
			),
			'fieldName' => array(
				'type' => 'string',
				'default' => ''
			),
			'displayAs' => array(
				'type' => 'string',
				'default' => 'auto'
			),
			'layoutMode' => array(
				'type' => 'string',
				'default' => 'column'
			),
			'justifyContent' => array(
				'type' => 'string',
				'default' => 'center'
			),
			'limit' => array(
				'type' => 'number',
				'default' => 0
			),
			'imageSize' => array(
				'type' => 'string',
				'default' => 'large'
			),
			'linkToMedia' => array(
				'type' => 'boolean',
				'default' => false
			),
			'linkToRelated' => array(
				'type' => 'boolean',
				'default' => true
			),
			'relatedDisplayField' => array(
				'type' => 'string',
				'default' => 'post_title'
			),
			'renderAsLink' => array(
				'type' => 'boolean',
				'default' => true
			),
			'linkText' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkNewTab' => array(
				'type' => 'boolean',
				'default' => false
			),
			'socialColorMode' => array(
				'type' => 'string',
				'default' => 'brand'
			),
			'socialSize' => array(
				'type' => 'number',
				'default' => 36
			),
			'socialBorderRadius' => array(
				'type' => 'number',
				'default' => 50
			),
			'colorShape' => array(
				'type' => 'string',
				'default' => 'square'
			),
			'colorSize' => array(
				'type' => 'number',
				'default' => 32
			),
			'showColorLabel' => array(
				'type' => 'boolean',
				'default' => false
			),
			'htmlTag' => array(
				'type' => 'string',
				'default' => 'div'
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'align' => true,
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			)
		),
		'textdomain' => 'pods-block-suite',
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
