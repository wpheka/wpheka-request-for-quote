/**
 * Laptop only (queue 2026-10-22 item 5): the upgrade from 1.8.3. Not part of the suite.
 *
 *   NODE_PATH=... node tests/functional/lap-upgrade.cjs set   <base-url> [shots-dir]
 *   NODE_PATH=... node tests/functional/lap-upgrade.cjs check <base-url> [shots-dir]
 *
 * set:   with 1.8.3 active, change every setting on its old screen and save it with its own
 *        Save button (AJAX), as a store owner would.
 * check: with 1.9.0 active, read what the new page shows, save it once unchanged and open the
 *        Requests tab. Logs in as admin / password. Prints what it finds.
 *
 * @package WPHEKA_Rfq
 */

'use strict';

const { chromium } = require( 'playwright' );

const MODE = process.argv[ 2 ];
const BASE = ( process.argv[ 3 ] || 'http://localhost:8892' ).replace( /\/$/, '' );
const SHOTS = process.argv[ 4 ] ? process.argv[ 4 ].replace( /\/?$/, '/' ) : '';
const PAGE = `${ BASE }/wp-admin/admin.php?page=wpheka_request_for_quote`;

( async () => {
	const browser = await chromium.launch( { channel: 'chrome', headless: true } );
	const page = await ( await browser.newContext( { viewport: { width: 1440, height: 1000 } } ) ).newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/, { waitUntil: 'domcontentloaded', timeout: 90000 } ), page.click( '#wp-submit' ) ] );

	if ( 'set' === MODE ) {
		await page.goto( PAGE, { waitUntil: 'load' } );
		const radio = ( name, value ) => page.locator( `input[name="${ name }"][value="${ value }"]` ).check( { force: true } );
		const box = async ( name, on ) => {
			const el = page.locator( `input[type="checkbox"][name="${ name }"]` );
			if ( on !== ( await el.isChecked() ) ) {
				await el.click( { force: true } );
			}
		};
		await radio( 'user_type', 'logged' );
		await radio( 'out_of_stock_option', 'hide_out_of_stock' );
		await radio( 'button_position', 'before' );
		await page.selectOption( 'select[name="button_type"]', 'link' );
		await page.fill( 'input[name="button_link_text"]', 'Get a price' );
		await box( 'button_in_other_pages', true );
		await box( 'hide_price', true );
		await box( 'hide_add_to_cart', true );
		await radio( 'after_click_action', 'redirect' );
		await box( 'show_phone_field', true );
		await box( 'phone_required', true );
		await box( 'show_company_field', true );
		const saved = page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() || '' ).includes( 'save_wpheka_rfq_plugin_data' ) );
		await page.locator( '.wpheka-save-changes' ).click();
		const res = await saved;
		console.log( 'OLD SAVE', res.status(), ( await res.text() ).slice( 0, 120 ) );
		await page.waitForTimeout( 3000 );
		if ( SHOTS ) {
			await page.goto( PAGE, { waitUntil: 'load' } );
			await page.screenshot( { path: SHOTS + 'upgrade-183-settings.png', fullPage: true } );
		}
	} else {
		await page.goto( `${ PAGE }&tab=settings`, { waitUntil: 'networkidle' } );
		const shown = await page.evaluate( () => {
			const out = {};
			document.querySelectorAll( '#rfq-settings input, #rfq-settings select' ).forEach( ( el ) => {
				if ( ! el.name || 'hidden' === el.type || /nonce|referer|^action$/.test( el.name ) ) {
					return;
				}
				const key = el.name.replace( 'wpheka_rfq_general_settings[', '' ).replace( ']', '' );
				if ( 'radio' === el.type ) {
					if ( el.checked ) {
						out[ key ] = el.value;
					}
				} else if ( 'checkbox' === el.type ) {
					out[ key ] = el.checked ? 'yes' : 'no';
				} else {
					out[ key ] = el.value;
				}
			} );
			return out;
		} );
		console.log( 'SHOWN BY 1.9.0', JSON.stringify( shown ) );
		if ( SHOTS ) {
			await page.screenshot( { path: SHOTS + 'upgrade-190-settings.png', fullPage: true } );
		}
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.locator( '#rfq-settings' ).evaluate( ( f ) => f.requestSubmit() ) ] );
		console.log( 'SAVED WITHOUT CHANGES:', await page.locator( '.w-toast.is-shown[role="status"]' ).count(), 'saved toast,', await page.locator( '.w-toast--error' ).count(), 'error toast' );
		await page.goto( `${ PAGE }&tab=requests`, { waitUntil: 'networkidle' } );
		console.log( 'REQUESTS TAB', ( await page.locator( '.w-empty' ).innerText().catch( () => '(no empty state)' ) ).replace( /\s+/g, ' ' ) );
	}
	console.log( 'SCRIPT ERRORS', JSON.stringify( errors ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
