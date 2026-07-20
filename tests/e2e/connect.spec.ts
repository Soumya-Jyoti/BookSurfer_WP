/**
 * Connect / disconnect E2E.
 *
 * BSWP-47. Covers the happy path and — more importantly — the negative flows that
 * are easy to break and impossible to catch in unit tests: denial, popup blocked,
 * and disconnect with the SaaS unreachable.
 *
 * @package
 */

import { expect, test } from '@playwright/test';
import {
	STUB_URL,
	completeConsent,
	connect,
	disconnect,
	gotoSettings,
	login,
	resetStub,
} from './helpers';

test.beforeEach( async ( { page } ) => {
	await resetStub( page );
	await login( page );
} );

test( 'connects, shows the studio, and lists classes', async ( { page } ) => {
	await connect( page );

	await expect( page.getByText( 'Demo Studio' ) ).toBeVisible();
	await expect( page.getByText( '@demo-studio' ) ).toBeVisible();

	// The class browser loads through our own REST route.
	await expect( page.getByText( 'Sunrise Yoga' ) ).toBeVisible();
	await expect( page.getByText( 'Evening Pilates' ) ).toBeVisible();

	// Price formatting carried over from the Framer plugin.
	await expect( page.getByText( '₹500' ) ).toBeVisible();
	await expect( page.getByText( 'Free' ) ).toBeVisible();

	await disconnect( page );
} );

test( 'no token ever reaches the browser', async ( { page } ) => {
	const bodies: string[] = [];

	page.on( 'response', async ( response ) => {
		if ( response.url().includes( '/wp-json/booksurfer/' ) ) {
			try {
				bodies.push( await response.text() );
			} catch {
				// Body already consumed — ignore.
			}
		}
	} );

	await connect( page );

	expect( bodies.length ).toBeGreaterThan( 0 );
	for ( const body of bodies ) {
		expect( body ).not.toContain( 'stub-access-' );
		expect( body ).not.toContain( 'stub-refresh-' );
		expect( body ).not.toContain( 'refresh_token' );
		expect( body ).not.toContain( 'access_token' );
	}

	await disconnect( page );
} );

test( 'a denied consent leaves the site disconnected with a retry available', async ( {
	page,
} ) => {
	await gotoSettings( page );
	await page
		.getByRole( 'button', { name: /Connect with Book Surfer/i } )
		.click();
	await completeConsent( page, false );

	// The state is burned; polling ends without connecting.
	await expect(
		page.getByRole( 'button', { name: /Connect with Book Surfer/i } )
	).toBeVisible( {
		timeout: 30000,
	} );
	await expect( page.getByText( 'Demo Studio' ) ).toHaveCount( 0 );
} );

test( 'a blocked popup offers an inline fallback link rather than failing silently', async ( {
	page,
} ) => {
	await gotoSettings( page );

	// Simulate a popup blocker.
	await page.addInitScript( () => {
		window.open = () => null;
	} );
	await page.reload();

	await page
		.getByRole( 'button', { name: /Connect with Book Surfer/i } )
		.click();

	const fallback = page.getByRole( 'link', { name: /Open it manually/i } );
	await expect( fallback ).toBeVisible();

	const href = await fallback.getAttribute( 'href' );
	expect( href ).toContain( '/connect/wordpress' );
	expect( href ).toContain( 'state=' );
	expect( href ).toContain( 'origin=' );
} );

test( 'disconnect succeeds even when the SaaS is unreachable', async ( {
	page,
} ) => {
	await connect( page );

	// Make every outbound revoke call fail.
	await page.route( `${ STUB_URL }/**`, ( route ) => route.abort() );

	await disconnect( page );

	await gotoSettings( page );
	await expect(
		page.getByRole( 'button', { name: /Connect with Book Surfer/i } )
	).toBeVisible();
} );

test( 'the settings page paints connected state without a spinner (preloaded status)', async ( {
	page,
} ) => {
	await connect( page );

	await gotoSettings( page );
	// Studio name is present on first paint because status is preloaded.
	await expect( page.getByText( 'Demo Studio' ) ).toBeVisible( {
		timeout: 3000,
	} );

	await disconnect( page );
} );
