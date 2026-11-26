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
	Spinner,
} from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { usePodsSchema } from '../shared/hooks/usePodsSchema';
import { SortControls } from '../shared/components/SortControls';
import { LayoutControls } from '../shared/components/LayoutControls';
import { preventEditorLinkNavigation } from '../shared/preventEditorLinkNavigation';
import './editor.scss';

export default function Edit( { attributes, setAttributes, context } ) {
	const {
		selectedPod,
		relationshipField,
		limit,
		orderBy,
		orderDir,
		flexDirection,
		justifyContent,
		columns,
		linkToPost,
	} = attributes;

	const handleAutoSelectPod = useCallback(
		( podSlug ) => {
			setAttributes( { selectedPod: podSlug } );
		},
		[ setAttributes ]
	);

	const { schema, isLoadingSchema, podOptions, activePodData } = usePodsSchema( {
		selectedPod,
		contextPostType: context.postType,
		onAutoSelectPod: handleAutoSelectPod,
	} );

	const relationshipFields = activePodData
		? activePodData.fields.filter( ( f ) => f.type === 'pick' )
		: [];

	// Resolve target POD metadata so SortControls displays fields from the related post type
	const currentRelFieldMeta =
		relationshipFields.find( ( f ) => f.name === relationshipField ) || null;
	const targetPodSlug = currentRelFieldMeta?.relatedPod || '';
	const targetPodData = schema && targetPodSlug ? schema[ targetPodSlug ] || null : null;

	const relationOptions = [
		{ label: __( '— Select relationship field —', 'pods-block-suite' ), value: '' },
		...relationshipFields.map( ( f ) => ( {
			label: `${ f.label } (${ f.name })`,
			value: f.name,
		} ) ),
	];

	const editorLabel = relationshipField
		? sprintf(
				/* translators: 1: Relationship field slug, 2: Layout description */
				__( 'PODS Related Items: %1$s (%2$s)', 'pods-block-suite' ),
				relationshipField,
				flexDirection === 'row'
					? sprintf( __( 'Row, %d cols', 'pods-block-suite' ), columns )
					: __( 'Column', 'pods-block-suite' )
		  )
		: __( 'PODS Related Item Container (select relationship field in sidebar)', 'pods-block-suite' );

	const blockProps = useBlockProps( {
		className: 'pods-related-item-container-editor',
		'data-editor-label': editorLabel,
		onClickCapture: preventEditorLinkNavigation,
		onAuxClickCapture: preventEditorLinkNavigation,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Relationship Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					{ isLoadingSchema ? (
						<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
							<Spinner />
							<span>{ __( 'Loading PODS...', 'pods-block-suite' ) }</span>
						</div>
					) : (
						<>
							<SelectControl
								label={ __( '1. Parent POD', 'pods-block-suite' ) }
								value={ selectedPod }
								options={ podOptions }
								onChange={ ( val ) => {
									setAttributes( {
										selectedPod: val,
										relationshipField: '',
										orderBy: 'post_date',
									} );
								} }
							/>

							{ activePodData && relationshipFields.length > 0 ? (
								<SelectControl
									label={ __( '2. Relationship Field', 'pods-block-suite' ) }
									value={ relationshipField }
									options={ relationOptions }
									onChange={ ( val ) =>
										setAttributes( {
											relationshipField: val,
											orderBy: 'post_date',
										} )
									}
								/>
							) : (
								<TextControl
									label={ __( 'Relationship Field Slug', 'pods-block-suite' ) }
									help={ __(
										'Enter the slug of your PODS relationship (pick) field.',
										'pods-block-suite'
									) }
									value={ relationshipField }
									onChange={ ( val ) =>
										setAttributes( { relationshipField: val.trim() } )
									}
								/>
							) }
						</>
					) }

					<TextControl
						label={ __( 'Max Items (0 = Unlimited)', 'pods-block-suite' ) }
						type="number"
						value={ limit }
						onChange={ ( val ) => {
							const parsed = parseInt( val, 10 );
							setAttributes( { limit: isNaN( parsed ) ? 0 : Math.max( 0, parsed ) } );
						} }
					/>

					<SortControls
						orderBy={ orderBy }
						orderDir={ orderDir }
						podData={ targetPodData }
						onChange={ setAttributes }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Layout & Card Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<LayoutControls
						flexDirection={ flexDirection }
						justifyContent={ justifyContent }
						columns={ columns }
						linkToPost={ linkToPost }
						onChange={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...innerBlocksProps } />
		</>
	);
}