/**
 * Joins truthy class names.
 *
 * @param {...(string|false|null|undefined)} parts Class names.
 * @return {string} Class string.
 */
export function cx( ...parts ) {
	return parts.filter( Boolean ).join( ' ' );
}

let uid = 0;
/**
 * Returns a unique DOM id with a prefix (stable for a component's lifetime when used with useMemo/useState).
 *
 * @param {string} prefix Prefix.
 * @return {string} Id.
 */
export function uniqueId( prefix = 'bl' ) {
	uid += 1;
	return `${ prefix }-${ uid }`;
}
