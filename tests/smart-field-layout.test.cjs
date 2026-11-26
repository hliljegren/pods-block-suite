const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );
const babel = require( '@babel/core' );
const React = require( 'react' );
const { renderToStaticMarkup } = require( 'react-dom/server' );
const metadata = require( '../src/smart-field/block.json' );

let field;
let controls;
const wrapper = ( { children } ) => React.createElement( React.Fragment, null, children );
const captureControl = ( props ) => {
	controls.push( props );
	return null;
};
const dependencies = {
	'react/jsx-runtime': require( 'react/jsx-runtime' ),
	'@wordpress/i18n': { __: ( text ) => text, sprintf: ( text ) => text },
	'@wordpress/block-editor': { useBlockProps: () => ( {} ), InspectorControls: wrapper },
	'@wordpress/components': {
		PanelBody: wrapper,
		SelectControl: captureControl,
		RangeControl: captureControl,
		TextControl: captureControl,
		CheckboxControl: captureControl,
		Placeholder: wrapper,
		Spinner: wrapper,
	},
	'@wordpress/element': { useCallback: ( callback ) => callback },
	'@wordpress/server-side-render': () => null,
	'../shared/hooks/usePodsSchema': {
		usePodsSchema: () => ( {
			schema: {},
			isLoadingSchema: false,
			podOptions: [],
			activePodData: { fields: [ field ], standardFields: [] },
		} ),
	},
	'../shared/preventEditorLinkNavigation': { preventEditorLinkNavigation: () => {} },
	'./block.json': metadata,
};
const source = fs.readFileSync( path.join( __dirname, '../src/smart-field/edit.js' ), 'utf8' );
const compiled = babel.transformSync( source, {
	babelrc: false,
	configFile: false,
	presets: [ [ '@babel/preset-react', { runtime: 'automatic' } ] ],
	plugins: [ '@babel/plugin-transform-modules-commonjs' ],
} ).code;
const moduleResult = { exports: {} };
const load = vm.runInThisContext( `(function (require, module, exports) { ${ compiled }\n})` );
load( ( name ) => {
	assert.ok( Object.hasOwn( dependencies, name ), `Unexpected dependency: ${ name }` );
	return dependencies[ name ];
}, moduleResult, moduleResult.exports );
const Edit = moduleResult.exports.default;

function renderEditor( fieldInfo, layoutMode = metadata.attributes.layoutMode.default ) {
	field = fieldInfo;
	controls = [];
	const updates = [];
	renderToStaticMarkup( React.createElement( Edit, {
		attributes: {
			selectedPod: 'post',
			fieldName: field.name,
			displayAs: 'auto',
			layoutMode,
			justifyContent: 'center',
		},
		context: {},
		setAttributes: ( value ) => updates.push( value ),
	} ) );
	return { controls, updates };
}

test( 'single title and image expose layout and justification without a multi-item limit', () => {
	for ( const fieldInfo of [
		{ name: 'post_title', type: 'text', isMultiple: false },
		{ name: 'post_thumbnail', type: 'file', isMultiple: false },
	] ) {
		const result = renderEditor( fieldInfo );
		const layout = result.controls.find( ( control ) => control.label === 'Layout' );
		assert.equal( layout.value, 'row' );
		assert.ok( layout.options.some( ( option ) => option.value === 'column' ) );
		assert.ok( result.controls.some( ( control ) => control.label === 'Justify Content' ) );
		assert.ok( ! result.controls.some( ( control ) => control.label === 'Max Items to Display (0 = Unlimited)' ) );
	}
} );

test( 'multi-value fields retain their column layout and list controls', () => {
	const result = renderEditor( { name: 'photos', type: 'file', isMultiple: true }, 'column' );
	const layout = result.controls.find( ( control ) => control.label === 'Layout' );
	assert.equal( layout.value, 'column' );
	assert.ok( layout.options.some( ( option ) => option.value === 'ul' ) );
	assert.ok( result.controls.some( ( control ) => control.label === 'Max Items to Display (0 = Unlimited)' ) );
	result.controls.find( ( control ) => control.label === 'Display As' ).onChange( 'social' );
	assert.deepEqual( result.updates, [ { displayAs: 'social' } ] );
} );
