/**
 * Pure helpers for the settings form (unit-tested).
 */

/**
 * Returns only the fields that differ between saved and draft, as a nested partial.
 *
 * @param {Object} saved Saved values (section → key → value).
 * @param {Object} draft Draft values.
 * @return {Object} Partial update.
 */
export function settingsDiff( saved, draft ) {
	const out = {};
	if ( ! saved || ! draft ) {
		return out;
	}
	Object.keys( draft ).forEach( ( section ) => {
		Object.keys( draft[ section ] || {} ).forEach( ( key ) => {
			const before = saved[ section ] ? saved[ section ][ key ] : undefined;
			if ( before !== draft[ section ][ key ] ) {
				out[ section ] = out[ section ] || {};
				out[ section ][ key ] = draft[ section ][ key ];
			}
		} );
	} );
	return out;
}

/**
 * Whether the draft has unsaved changes.
 *
 * @param {Object} saved Saved values.
 * @param {Object} draft Draft values.
 * @return {boolean} Dirty.
 */
export function isDirty( saved, draft ) {
	return Object.keys( settingsDiff( saved, draft ) ).length > 0;
}
