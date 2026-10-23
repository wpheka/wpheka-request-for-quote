/**
 * Laptop only (queue 2026-10-22 item 5): screenshots of the 1.9.0 page for review, at 1440,
 * 1024, 782 and 390 wide. Not part of the suite.
 *
 *   NODE_PATH=... node tests/functional/lap-screens.cjs <base-url> <shots-dir> <label> [tab ...]
 *
 * Saves <label>-<tab>-<width>.png for each tab (settings, requests, help), full page, and
 * reports any sideways scroll. Logs in as admin / password.
 *
 * @package WPHEKA_Rfq
 */

'use strict';

const { chromium } = require( 'playwright' );

const BASE = process.argv[ 2 ].replace( /\/$/, '' );
const SHOTS = process.argv[ 3 ].replace( /\/?$/, '/' );
const LABEL = process.argv[ 4 ];
const TABS = process.argv.slice( 5 ).length ? process.argv.slice( 5 ) : [ 'settings', 'requests', 'help' ];

( async () => {
	const browser = await chromium.launch( { channel: 'chrome', headless: true } );
	const widths = process.env.LAP_WIDTHS ? process.env.LAP_WIDTHS.split( ',' ).map( Number ) : [ 1440, 1024, 782, 390 ];
	for ( const width of widths ) {
		const page = await ( await browser.newContext( { viewport: { width, height: 900 } } ) ).newPage();
		// Two tries: a busy Docker host can be slow to answer the first login.
		for ( let attempt = 1; attempt <= 2; attempt++ ) {
			try {
				await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 180000 } );
				await page.fill( '#user_login', 'admin' );
				await page.fill( '#user_pass', 'password' );
				await Promise.all( [ page.waitForURL( /wp-admin/, { waitUntil: 'domcontentloaded', timeout: 180000 } ), page.click( '#wp-submit' ) ] );
				break;
			} catch ( e ) {
				if ( 2 === attempt ) {
					throw e;
				}
			}
		}
		page.setDefaultTimeout( 180000 );
		for ( const tab of TABS ) {
			await page.goto( `${ BASE }/wp-admin/admin.php?page=wpheka_request_for_quote&tab=${ tab }`, { waitUntil: 'networkidle' } );
			const scroll = await page.evaluate( () => [ document.documentElement.scrollWidth, window.innerWidth ] );
			await page.screenshot( { path: `${ SHOTS }${ LABEL }-${ tab }-${ width }.png`, fullPage: true } );
			console.log( `${ LABEL } ${ tab } ${ width }: scrollWidth ${ scroll[ 0 ] } of ${ scroll[ 1 ] }${ scroll[ 0 ] > scroll[ 1 ] ? '  SIDEWAYS SCROLL' : '' }` );
		}
		await page.context().close();
	}
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
