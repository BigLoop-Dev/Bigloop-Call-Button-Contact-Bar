import { settingsDiff, isDirty } from '../../assets/src/admin/lib/settings';

describe( 'settingsDiff', () => {
	const saved = { general: { a: false, b: 1 }, access: { c: false } };

	it( 'returns an empty object when nothing changed', () => {
		expect( settingsDiff( saved, JSON.parse( JSON.stringify( saved ) ) ) ).toEqual( {} );
		expect( isDirty( saved, saved ) ).toBe( false );
	} );

	it( 'returns only changed fields, nested by section', () => {
		const draft = { general: { a: true, b: 1 }, access: { c: false } };
		expect( settingsDiff( saved, draft ) ).toEqual( { general: { a: true } } );
		expect( isDirty( saved, draft ) ).toBe( true );
	} );

	it( 'handles missing inputs', () => {
		expect( settingsDiff( null, saved ) ).toEqual( {} );
	} );
} );
