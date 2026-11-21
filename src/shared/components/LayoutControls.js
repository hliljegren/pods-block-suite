import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	RangeControl,
	CheckboxControl,
} from '@wordpress/components';

/**
 * Shared LayoutControls component for PODS container and field blocks.
 * Renders optional clickable card toggle, Flex Direction, Justify Content,
 * and Columns per row slider when in horizontal row mode.
 *
 * @param {Object}   props                Component props.
 * @param {string}   props.flexDirection  Current flex direction ('row' or 'column').
 * @param {string}   props.justifyContent Current CSS justify-content value.
 * @param {number}   props.columns        Number of columns per row (1-6).
 * @param {boolean}  props.linkToPost     Whether each item card wraps in a permalink anchor.
 * @param {boolean}  props.showLinkToPost Whether to render the linkToPost checkbox (default true).
 * @param {boolean}  props.showColumns    Whether to render the columns slider in row mode (default true).
 * @param {Function} props.onChange       Callback invoked with attribute updates object (e.g. setAttributes).
 * @return {JSX.Element} Rendered layout controls.
 */
export function LayoutControls( {
	flexDirection = 'row',
	justifyContent = 'center',
	columns = 3,
	linkToPost = false,
	showLinkToPost = true,
	showColumns = true,
	onChange,
} ) {
	return (
		<>
			{ showLinkToPost && (
				<CheckboxControl
					label={ __( 'Make entire card clickable (Link to post)', 'pods-block-suite' ) }
					help={ __(
						'Wraps each item in a link to its single post permalink.',
						'pods-block-suite'
					) }
					checked={ linkToPost }
					onChange={ ( val ) => onChange( { linkToPost: val } ) }
				/>
			) }

			<SelectControl
				label={ __( 'Flex Direction', 'pods-block-suite' ) }
				value={ flexDirection }
				options={ [
					{
						label: __( 'Row (horizontal grid)', 'pods-block-suite' ),
						value: 'row',
					},
					{
						label: __( 'Column (vertical)', 'pods-block-suite' ),
						value: 'column',
					},
				] }
				onChange={ ( val ) => onChange( { flexDirection: val } ) }
			/>

			<SelectControl
				label={ __( 'Justify Content', 'pods-block-suite' ) }
				help={ __(
					'Controls how items are aligned and distributed along the flex axis.',
					'pods-block-suite'
				) }
				value={ justifyContent }
				options={ [
					{
						label: __( 'Center (center)', 'pods-block-suite' ),
						value: 'center',
					},
					{
						label: __( 'Start / Left (flex-start)', 'pods-block-suite' ),
						value: 'flex-start',
					},
					{
						label: __( 'End / Right (flex-end)', 'pods-block-suite' ),
						value: 'flex-end',
					},
					{
						label: __( 'Space Between (space-between)', 'pods-block-suite' ),
						value: 'space-between',
					},
					{
						label: __( 'Space Around (space-around)', 'pods-block-suite' ),
						value: 'space-around',
					},
					{
						label: __( 'Space Evenly (space-evenly)', 'pods-block-suite' ),
						value: 'space-evenly',
					},
				] }
				onChange={ ( val ) => onChange( { justifyContent: val } ) }
			/>

			{ showColumns && flexDirection === 'row' && (
				<RangeControl
					label={ __( 'Columns per row', 'pods-block-suite' ) }
					value={ columns }
					onChange={ ( val ) => onChange( { columns: val } ) }
					min={ 1 }
					max={ 6 }
				/>
			) }
		</>
	);
}