import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { relationField, maxItems, flexDirection, columns, linkToPost } = attributes;

	// Build a localized label displayed above the container in the block editor
	const limitLabel =
		maxItems > 0
			? sprintf( __( '(max %d)', 'pods-block-suite' ), maxItems )
			: __( '(all)', 'pods-block-suite' );

	const layoutLabel =
		flexDirection === 'row'
			? sprintf( __( 'Row (%d cols)', 'pods-block-suite' ), columns )
			: __( 'Column', 'pods-block-suite' );

	const linkLabel = linkToPost
		? sprintf( ' • %s', __( 'Clickable card', 'pods-block-suite' ) )
		: '';

	const editorLabel = relationField
		? sprintf(
				/* translators: 1: Relationship field slug, 2: Limit label, 3: Layout label, 4: Optional link label */
				__( 'PODS Relation: %1$s %2$s • %3$s%4$s', 'pods-block-suite' ),
				relationField,
				limitLabel,
				layoutLabel,
				linkLabel
		  )
		: __( 'PODS Related Item Container (enter field name in sidebar)', 'pods-block-suite' );

	const blockProps = useBlockProps( {
		className: 'pods-related-container-editor',
		'data-editor-label': editorLabel,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'PODS Relationship Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Relationship Field Name', 'pods-block-suite' ) }
						help={ __(
							'Enter the machine name (slug) of the PODS relationship field, e.g. "author" or "related_books".',
							'pods-block-suite'
						) }
						placeholder={ __( 'e.g. author', 'pods-block-suite' ) }
						value={ relationField }
						onChange={ ( val ) =>
							setAttributes( { relationField: val.trim() } )
						}
					/>

					<TextControl
						label={ __( 'Max Related Items', 'pods-block-suite' ) }
						help={ __(
							'Leave as 0 to display all related items.',
							'pods-block-suite'
						) }
						type="number"
						min={ 0 }
						value={ maxItems || '' }
						onChange={ ( val ) => {
							const parsed = parseInt( val, 10 );
							setAttributes( {
								maxItems: isNaN( parsed ) || parsed < 0 ? 0 : parsed,
							} );
						} }
					/>

					<CheckboxControl
						label={ __( 'Make entire card clickable (Link to post)', 'pods-block-suite' ) }
						help={ __(
							'Wraps each related item in a link pointing to its single post permalink.',
							'pods-block-suite'
						) }
						checked={ linkToPost }
						onChange={ ( val ) => setAttributes( { linkToPost: val } ) }
					/>

					{ ! relationField && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Enter a relationship field name above, then insert field blocks inside this container.',
								'pods-block-suite'
							) }
						</Notice>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Layout Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Flex Direction', 'pods-block-suite' ) }
						value={ flexDirection }
						options={ [
							{ label: __( 'Column (vertical)', 'pods-block-suite' ), value: 'column' },
							{ label: __( 'Row (horizontal grid)', 'pods-block-suite' ), value: 'row' },
						] }
						onChange={ ( val ) => setAttributes( { flexDirection: val } ) }
					/>

					{ flexDirection === 'row' && (
						<RangeControl
							label={ __( 'Columns per row', 'pods-block-suite' ) }
							help={ __(
								'Controls the width of each related item card on desktop screens.',
								'pods-block-suite'
							) }
							value={ columns }
							onChange={ ( val ) => setAttributes( { columns: val } ) }
							min={ 1 }
							max={ 6 }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...innerBlocksProps } />
		</>
	);
}