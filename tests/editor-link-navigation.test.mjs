import { test } from 'node:test';
import assert from 'node:assert/strict';
import { preventEditorLinkNavigation } from '../src/shared/preventEditorLinkNavigation.js';

test( 'prevents navigation for links and their descendants inside a block', () => {
	const link = {};
	let prevented = false;
	const event = {
		target: { closest: ( selector ) => selector === 'a[href]' && link },
		currentTarget: { contains: ( element ) => element === link },
		preventDefault: () => { prevented = true; },
	};

	preventEditorLinkNavigation( event );
	assert.equal( prevented, true );
} );

test( 'leaves non-link interactions and outside links alone', () => {
	for ( const link of [ null, {} ] ) {
		let prevented = false;
		preventEditorLinkNavigation( {
			target: { closest: () => link },
			currentTarget: { contains: () => false },
			preventDefault: () => { prevented = true; },
		} );
		assert.equal( prevented, false );
	}
} );
