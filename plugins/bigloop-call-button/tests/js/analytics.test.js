import { percentChange, bucketSeries } from '../../assets/src/admin/screens/Analytics';

describe( 'analytics helpers', () => {
	test( 'percentChange', () => {
		expect( percentChange( 150, 100 ) ).toBe( 50 );
		expect( percentChange( 50, 100 ) ).toBe( -50 );
		expect( percentChange( 0, 0 ) ).toBe( 0 );
		expect( percentChange( 5, 0 ) ).toBeNull();
	} );

	test( 'bucketSeries keeps days up to 31 and groups longer ranges by week', () => {
		const days = ( n ) => Array.from( { length: n }, ( _, i ) => ( { day: `2026-01-${ String( ( i % 28 ) + 1 ).padStart( 2, '0' ) }`, clicks: 1 } ) );
		expect( bucketSeries( days( 30 ) ) ).toHaveLength( 30 );
		const weeks = bucketSeries( days( 90 ) );
		expect( weeks ).toHaveLength( 13 );
		expect( weeks.reduce( ( s, w ) => s + w.clicks, 0 ) ).toBe( 90 );
		expect( weeks[ 12 ].clicks ).toBe( 6 );
	} );
} );
