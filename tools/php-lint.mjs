#!/usr/bin/env node
/**
 * Runs `php -l` on every PHP file under the given directory (excluding vendor/node_modules).
 * Usage: node tools/php-lint.mjs <dir> [php-binary]
 */
import { readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.argv[ 2 ] || '.';
const php = process.argv[ 3 ] || process.env.PHP_BIN || 'php';
const skip = new Set( [ 'node_modules', 'vendor', '.git', 'build' ] );

function walk( dir, out = [] ) {
	for ( const name of readdirSync( dir ) ) {
		if ( skip.has( name ) ) continue;
		const p = join( dir, name );
		const s = statSync( p );
		if ( s.isDirectory() ) walk( p, out );
		else if ( name.endsWith( '.php' ) ) out.push( p );
	}
	return out;
}

const files = walk( root );
let failed = 0;
for ( const f of files ) {
	const r = spawnSync( php, [ '-l', f ], { encoding: 'utf8' } );
	if ( r.status !== 0 ) {
		failed++;
		process.stderr.write( r.stdout + r.stderr );
	}
}
console.log( `php -l: ${ files.length - failed }/${ files.length } files OK` );
process.exit( failed ? 1 : 0 );
