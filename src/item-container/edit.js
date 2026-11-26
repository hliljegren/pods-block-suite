import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	CheckboxControl,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { preventEditorLinkNavigation } from '../shared/preventEditorLinkNavigation';
import './editor.scss';

export default function Edit( { attributes, setAttributes, context } ) {
	const { customPostId, resolvedPostType, linkToPost } = attributes;

	// Resolve post type for manual customPostId, or fall back to current page/loop context
	const fetchedPostType = useSelect(
		( select ) => {
			if ( customPostId > 0 ) {
				const record = select( coreStore ).getEntityRecord(
					'root',
					'postType',
					undefined
				);
				// Look up the post across registered post types via getEntityRecord if needed,
				// or check standard 'post' / 'page' / context postType first
				const { getPostTypes, getEntityRecord } = select( coreStore );
				const postTypes = getPostTypes( { per_page: -1 } ) || [];
				for ( const pt of postTypes ) {
					if ( ! pt.viewable && pt.slug !== 'post' && pt.slug !== 'page' ) {
						continue;
					}
					const postObj = getEntityRecord( 'postType', pt.slug, customPostId );
					if ( postObj && postObj.type ) {
						return postObj.type;
					}
				}
				return '';
			}
			return context.postType || '';
		},
		[ customPostId, context.postType ]
	);

	useEffect( () => {
		if ( fetchedPostType && fetchedPostType !== resolvedPostType ) {
			setAttributes( { resolvedPostType: fetchedPostType } );
		} else if ( ! customPostId && context.postType && context.postType !== resolvedPostType ) {
			setAttributes( { resolvedPostType: context.postType } );
		}
	}, [ fetchedPostType, customPostId, context.postType, resolvedPostType, setAttributes ] );

	const activeId = customPostId > 0 ? customPostId : context.postId || 0;
	const activeType = resolvedPostType || context.postType || '';

	const editorLabel = activeId
		? sprintf(
				/* translators: 1: Post type slug, 2: Post ID */
				__( 'PODS Item Container (%1$s #%2$d)', 'pods-block-suite' ),
				activeType || 'post',
				activeId
		  )
		: __( 'PODS Item Container (Current Context)', 'pods-block-suite' );

	const blockProps = useBlockProps( {
		className: 'pods-item-container-editor',
		'data-editor-label': editorLabel,
		onClickCapture: preventEditorLinkNavigation,
		onAuxClickCapture: preventEditorLinkNavigation,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Item Context Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Specific Post ID (0 = Current Context)', 'pods-block-suite' ) }
						help={ __(
							'Leave as 0 to automatically inherit the current post or query item context.',
							'pods-block-suite'
						) }
						type="number"
						value={ customPostId }
						onChange={ ( val ) => {
							const parsed = parseInt( val, 10 );
							setAttributes( {
								customPostId: isNaN( parsed ) ? 0 : Math.max( 0, parsed ),
							} );
						} }
					/>

					<CheckboxControl
						label={ __( 'Make entire container clickable (Link to post)', 'pods-block-suite' ) }
						help={ __(
							'Wraps the container content in a link to the target post permalink.',
							'pods-block-suite'
						) }
						checked={ linkToPost }
						onChange={ ( val ) => setAttributes( { linkToPost: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...innerBlocksProps } />
		</>
	);
}