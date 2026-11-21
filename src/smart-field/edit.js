import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	CheckboxControl,
	RangeControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import { usePodsSchema } from '../shared/hooks/usePodsSchema';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes, context } ) {
	const {
		selectedPod,
		fieldName,
		displayAs,
		layoutMode,
		justifyContent,
		limit,
		imageSize,
		linkToMedia,
		linkToRelated,
		relatedDisplayField,
		renderAsLink,
		linkText,
		linkNewTab,
		socialColorMode,
		socialSize,
		socialBorderRadius,
		colorShape,
		colorSize,
		showColorLabel,
		htmlTag,
	} = attributes;

	const blockProps = useBlockProps();

	const handleAutoSelectPod = useCallback(
		( podSlug ) => {
			setAttributes( { selectedPod: podSlug } );
		},
		[ setAttributes ]
	);

	// 1. Fetch schema & handle context auto-selection via shared hook
	const { schema, isLoadingSchema, podOptions, activePodData } = usePodsSchema( {
		selectedPod,
		contextPostType: context.postType,
		onAutoSelectPod: handleAutoSelectPod,
	} );

	// 2. Resolve selected field metadata
	const allFieldsForPod = activePodData
		? [ ...activePodData.fields, ...activePodData.standardFields ]
		: [];
	const currentFieldMeta = allFieldsForPod.find( ( f ) => f.name === fieldName ) || null;

	// Determine effective display mode ('media', 'relation', 'oembed', 'color', 'social', 'link', or 'text')
	let effectiveMode = 'text';
	if ( displayAs && displayAs !== 'auto' ) {
		effectiveMode = displayAs;
	} else if ( currentFieldMeta ) {
		if (
			fieldName === 'post_thumbnail' ||
			[ 'file', 'media', 'avatar' ].includes( currentFieldMeta.type )
		) {
			effectiveMode = 'media';
		} else if ( currentFieldMeta.type === 'pick' ) {
			effectiveMode = 'relation';
		} else if ( currentFieldMeta.type === 'oembed' ) {
			effectiveMode = 'oembed';
		} else if ( currentFieldMeta.type === 'color' ) {
			effectiveMode = 'color';
		} else if ( currentFieldMeta.type === 'website' || fieldName === 'permalink' ) {
			effectiveMode = 'link';
		}
	}

	// Only show multi-item controls when both the display mode and the field configuration support multiple items
	const isMultiMode = [ 'media', 'relation', 'social' ].includes( effectiveMode );
	const isMultiValueField = currentFieldMeta ? Boolean( currentFieldMeta.isMultiple ) : true;
	const showMultiItemControls = isMultiMode && isMultiValueField;

	// Retrieve fields from the target POD when a relationship field is selected
	const relatedPodSlug = currentFieldMeta?.relatedPod || '';
	const relatedPodData = schema && relatedPodSlug ? schema[ relatedPodSlug ] || null : null;
	const relatedFieldsList = relatedPodData
		? [ ...relatedPodData.standardFields, ...relatedPodData.fields ]
		: [];

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'PODS Data Source', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					{ isLoadingSchema ? (
						<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
							<Spinner />
							<span>{ __( 'Loading PODS fields...', 'pods-block-suite' ) }</span>
						</div>
					) : (
						<>
							<SelectControl
								label={ __( '1. Select POD', 'pods-block-suite' ) }
								help={
									context.postType
										? __( 'Pre-selected from page or container context.', 'pods-block-suite' )
										: __( 'Choose which POD to retrieve fields from.', 'pods-block-suite' )
								}
								value={ selectedPod }
								options={ podOptions }
								onChange={ ( val ) => {
									setAttributes( {
										selectedPod: val,
										fieldName: '',
									} );
								} }
							/>

							{ activePodData ? (
								<div className="components-base-control">
									<label
										className="components-base-control__label"
										htmlFor="pods-smart-field-select"
										style={ { display: 'block', marginBottom: '6px' } }
									>
										{ __( '2. Select Field', 'pods-block-suite' ) }
									</label>
									<select
										id="pods-smart-field-select"
										className="components-select-control__input"
										style={ { width: '100%', height: '36px' } }
										value={ fieldName }
										onChange={ ( e ) =>
											setAttributes( { fieldName: e.target.value } )
										}
									>
										<option value="">
											{ __( '— Select a field —', 'pods-block-suite' ) }
										</option>
										{ activePodData.fields.length > 0 && (
											<optgroup label={ __( 'PODS Fields', 'pods-block-suite' ) }>
												{ activePodData.fields.map( ( f ) => (
													<option key={ f.name } value={ f.name }>
														{ f.label } [{ f.type }]
													</option>
												) ) }
											</optgroup>
										) }
										<optgroup label={ __( 'WordPress Standard Fields', 'pods-block-suite' ) }>
											{ activePodData.standardFields.map( ( f ) => (
												<option key={ f.name } value={ f.name }>
													{ f.label }
												</option>
											) ) }
										</optgroup>
									</select>
								</div>
							) : (
								<TextControl
									label={ __( 'Or enter field slug manually', 'pods-block-suite' ) }
									value={ fieldName }
									onChange={ ( val ) =>
										setAttributes( { fieldName: val.trim() } )
									}
								/>
							) }

							{ fieldName && (
								<SelectControl
									label={ __( 'Display As', 'pods-block-suite' ) }
									value={ displayAs }
									options={ [
										{
											label: currentFieldMeta
												? sprintf(
														/* translators: %s: Detected display mode */
														__( 'Automatic (%s)', 'pods-block-suite' ),
														effectiveMode
												  )
												: __( 'Automatic (Auto)', 'pods-block-suite' ),
											value: 'auto',
										},
										{ label: __( 'Media (Image / Video / Audio)', 'pods-block-suite' ), value: 'media' },
										{ label: __( 'Social Icons (Auto-detect platform)', 'pods-block-suite' ), value: 'social' },
										{ label: __( 'oEmbed (YouTube / Vimeo / Embed)', 'pods-block-suite' ), value: 'oembed' },
										{ label: __( 'Color Swatch (Square / Circle)', 'pods-block-suite' ), value: 'color' },
										{ label: __( 'Related Post (Link / List)', 'pods-block-suite' ), value: 'relation' },
										{ label: __( 'Website URL (Link)', 'pods-block-suite' ), value: 'link' },
										{ label: __( 'Plain Text / HTML', 'pods-block-suite' ), value: 'text' },
									] }
									onChange={ ( val ) => {
										const updates = { displayAs: val };
										if ( val === 'social' && layoutMode === 'column' ) {
											updates.layoutMode = 'row';
										}
										setAttributes( updates );
									} }
								/>
							) }
						</>
					) }
				</PanelBody>

				{/* CONTEXT-AWARE SETTINGS PANEL */}
				{ fieldName && (
					<PanelBody
						title={ __( 'Display Settings', 'pods-block-suite' ) }
						initialOpen={ true }
					>
						{/* MEDIA SETTINGS */}
						{ effectiveMode === 'media' && (
							<>
								<SelectControl
									label={ __( 'Image Size', 'pods-block-suite' ) }
									value={ imageSize }
									options={ [
										{ label: __( 'Thumbnail', 'pods-block-suite' ), value: 'thumbnail' },
										{ label: __( 'Medium', 'pods-block-suite' ), value: 'medium' },
										{ label: __( 'Large', 'pods-block-suite' ), value: 'large' },
										{ label: __( 'Full Size', 'pods-block-suite' ), value: 'full' },
									] }
									onChange={ ( val ) => setAttributes( { imageSize: val } ) }
								/>

								<CheckboxControl
									label={ __( 'Link to media file', 'pods-block-suite' ) }
									help={ __(
										'Wraps images in a link to the original media file (enables lightbox plugins).',
										'pods-block-suite'
									) }
									checked={ linkToMedia }
									onChange={ ( val ) => setAttributes( { linkToMedia: val } ) }
								/>
							</>
						) }

						{/* SOCIAL ICONS SETTINGS */}
						{ effectiveMode === 'social' && (
							<>
								<SelectControl
									label={ __( 'Icon Color Style', 'pods-block-suite' ) }
									help={ __(
										'Use official brand colors or inherit the block text color.',
										'pods-block-suite'
									) }
									value={ socialColorMode }
									options={ [
										{ label: __( 'Brand Colors', 'pods-block-suite' ), value: 'brand' },
										{ label: __( 'Inherit Block Text Color', 'pods-block-suite' ), value: 'inherit' },
									] }
									onChange={ ( val ) => setAttributes( { socialColorMode: val } ) }
								/>

								<RangeControl
									label={ __( 'Icon Size (px)', 'pods-block-suite' ) }
									value={ socialSize }
									onChange={ ( val ) => setAttributes( { socialSize: val } ) }
									min={ 20 }
									max={ 72 }
								/>

								<RangeControl
									label={ __( 'Corner Radius (%)', 'pods-block-suite' ) }
									help={ __(
										'0% = Square, 20% = Rounded Square, 50% = Circle.',
										'pods-block-suite'
									) }
									value={ socialBorderRadius }
									onChange={ ( val ) => setAttributes( { socialBorderRadius: val } ) }
									min={ 0 }
									max={ 50 }
								/>

								<CheckboxControl
									label={ __( 'Open in new tab (target="_blank")', 'pods-block-suite' ) }
									checked={ linkNewTab }
									onChange={ ( val ) => setAttributes( { linkNewTab: val } ) }
								/>
							</>
						) }

						{/* COLOR SWATCH SETTINGS */}
						{ effectiveMode === 'color' && (
							<>
								<SelectControl
									label={ __( 'Color Display Style', 'pods-block-suite' ) }
									value={ colorShape }
									options={ [
										{ label: __( 'Square Swatch', 'pods-block-suite' ), value: 'square' },
										{ label: __( 'Circle Swatch', 'pods-block-suite' ), value: 'circle' },
										{ label: __( 'Hex Code Text Only', 'pods-block-suite' ), value: 'text' },
									] }
									onChange={ ( val ) => setAttributes( { colorShape: val } ) }
								/>

								{ colorShape !== 'text' && (
									<>
										<RangeControl
											label={ __( 'Swatch Size (px)', 'pods-block-suite' ) }
											value={ colorSize }
											onChange={ ( val ) => setAttributes( { colorSize: val } ) }
											min={ 12 }
											max={ 120 }
										/>

										<CheckboxControl
											label={ __( 'Show hex code label next to swatch', 'pods-block-suite' ) }
											checked={ showColorLabel }
											onChange={ ( val ) => setAttributes( { showColorLabel: val } ) }
										/>
									</>
								) }
							</>
						) }

						{/* RELATIONSHIP SETTINGS */}
						{ effectiveMode === 'relation' && (
							<>
								<CheckboxControl
									label={ __( 'Link to related post', 'pods-block-suite' ) }
									help={ __(
										'Makes each related item clickable to its single post page.',
										'pods-block-suite'
									) }
									checked={ linkToRelated }
									onChange={ ( val ) => setAttributes( { linkToRelated: val } ) }
								/>

								{ relatedFieldsList.length > 0 ? (
									<SelectControl
										label={ __( 'Field to display as text / link label', 'pods-block-suite' ) }
										help={ __(
											'Choose which field from the related post to display.',
											'pods-block-suite'
										) }
										value={ relatedDisplayField }
										options={ relatedFieldsList.map( ( rf ) => ( {
											label: rf.label,
											value: rf.name,
										} ) ) }
										onChange={ ( val ) =>
											setAttributes( { relatedDisplayField: val } )
										}
									/>
								) : (
									<TextControl
										label={ __( 'Field to display as text / link label', 'pods-block-suite' ) }
										help={ __(
											'Defaults to "post_title".',
											'pods-block-suite'
										) }
										value={ relatedDisplayField }
										onChange={ ( val ) =>
											setAttributes( { relatedDisplayField: val.trim() || 'post_title' } )
										}
									/>
								) }
							</>
						) }

						{/* URL / WEBSITE LINK SETTINGS */}
						{ effectiveMode === 'link' && (
							<>
								<CheckboxControl
									label={ __( 'Render as clickable link', 'pods-block-suite' ) }
									help={ __(
										'Uncheck to output the raw URL as plain text instead.',
										'pods-block-suite'
									) }
									checked={ renderAsLink }
									onChange={ ( val ) => setAttributes( { renderAsLink: val } ) }
								/>

								{ renderAsLink && (
									<>
										<TextControl
											label={ __( 'Link Text (optional)', 'pods-block-suite' ) }
											help={ __(
												'Leave empty to use the URL itself as the link text.',
												'pods-block-suite'
											) }
											placeholder={ __( 'e.g. Visit Website', 'pods-block-suite' ) }
											value={ linkText }
											onChange={ ( val ) => setAttributes( { linkText: val } ) }
										/>

										<CheckboxControl
											label={ __( 'Open in new tab (target="_blank")', 'pods-block-suite' ) }
											checked={ linkNewTab }
											onChange={ ( val ) => setAttributes( { linkNewTab: val } ) }
										/>
									</>
								) }
							</>
						) }

						{/* MULTI-ITEM LIMIT & LAYOUT (ONLY SHOWN FOR REPEATABLE / MULTI-VALUE FIELDS) */}
						{ showMultiItemControls && (
							<>
								<RangeControl
									label={ __( 'Max Items to Display (0 = Unlimited)', 'pods-block-suite' ) }
									help={ __(
										'Limits how many repeated or related items are shown.',
										'pods-block-suite'
									) }
									value={ limit }
									onChange={ ( val ) => setAttributes( { limit: val } ) }
									min={ 0 }
									max={ 50 }
								/>

								<SelectControl
									label={ __( 'Multi-item Layout', 'pods-block-suite' ) }
									help={ __(
										'Controls how repeated items or multiple related posts are arranged.',
										'pods-block-suite'
									) }
									value={ layoutMode }
									options={ [
										{ label: __( 'Flexbox: Row (horizontal)', 'pods-block-suite' ), value: 'row' },
										{ label: __( 'Flexbox: Column (vertical)', 'pods-block-suite' ), value: 'column' },
										{ label: __( 'Unordered List (<ul>)', 'pods-block-suite' ), value: 'ul' },
										{ label: __( 'Ordered List (<ol>)', 'pods-block-suite' ), value: 'ol' },
									] }
									onChange={ ( val ) => setAttributes( { layoutMode: val } ) }
								/>

								{ ( layoutMode === 'row' || layoutMode === 'column' ) && (
									<SelectControl
										label={ __( 'Justify Content', 'pods-block-suite' ) }
										value={ justifyContent }
										options={ [
											{ label: __( 'Center (center)', 'pods-block-suite' ), value: 'center' },
											{ label: __( 'Start / Left (flex-start)', 'pods-block-suite' ), value: 'flex-start' },
											{ label: __( 'End / Right (flex-end)', 'pods-block-suite' ), value: 'flex-end' },
											{ label: __( 'Space Between (space-between)', 'pods-block-suite' ), value: 'space-between' },
											{ label: __( 'Space Around (space-around)', 'pods-block-suite' ), value: 'space-around' },
											{ label: __( 'Space Evenly (space-evenly)', 'pods-block-suite' ), value: 'space-evenly' },
										] }
										onChange={ ( val ) => setAttributes( { justifyContent: val } ) }
									/>
								) }
							</>
						) }

						{/* TEXT / HTML WRAPPER TAG SETTINGS */}
						{ ( effectiveMode === 'text' || ( effectiveMode === 'link' && ! renderAsLink ) ) && (
							<SelectControl
								label={ __( 'HTML Wrapper Tag', 'pods-block-suite' ) }
								value={ htmlTag }
								options={ [
									{ label: __( '<div> (Default)', 'pods-block-suite' ), value: 'div' },
									{ label: __( '<p> (Paragraph)', 'pods-block-suite' ), value: 'p' },
									{ label: __( '<h2> (Heading 2)', 'pods-block-suite' ), value: 'h2' },
									{ label: __( '<h3> (Heading 3)', 'pods-block-suite' ), value: 'h3' },
									{ label: __( '<span> (Inline)', 'pods-block-suite' ), value: 'span' },
								] }
								onChange={ ( val ) => setAttributes( { htmlTag: val } ) }
							/>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ ! fieldName ? (
					<Placeholder
						icon="media-default"
						label={ __( 'PODS Smart Field', 'pods-block-suite' ) }
						instructions={ __(
							'Select a POD and a field in the block sidebar on the right.',
							'pods-block-suite'
						) }
					/>
				) : (
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						urlQueryArgs={ {
							context_post_id: context.postId || 0,
							context_post_type: context.postType || '',
						} }
					/>
				) }
			</div>
		</>
	);
}