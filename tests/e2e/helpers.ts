/**
 * E2E helpers.
 *
 * BSWP-47. Shared login/stub utilities. Everything runs against the BSWP-5 stub.
 *
 * @package BookSurfer
 */

import type { Page } from '@playwright/test';

export const STUB_URL = process.env.BOOKSURFER_STUB_URL || 'http://127.0.0.1:5199';
export const ADMIN_USER = 'admin';
export const ADMIN_PASS = 'password';

/** Log in to wp-admin. */
export async function login( page: Page, user = ADMIN_USER, pass = ADMIN_PASS ): Promise< void > {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

/** Clear all stub state so each test starts from a known place. */
export async function resetStub( page: Page ): Promise< void > {
	await page.request.get( `${ STUB_URL }/__stub/reset` );
}

/** Open the plugin's settings screen. */
export async function gotoSettings( page: Page ): Promise< void > {
	await page.goto( '/wp-admin/admin.php?page=book-surfer' );
}

/**
 * Drive the stub's consent screen to completion in the popup the plugin opened.
 *
 * @param page     The admin page that triggered the popup.
 * @param approve  Approve or deny.
 */
export async function completeConsent( page: Page, approve = true ): Promise< void > {
	const popup = await page.waitForEvent( 'popup' );
	await popup.waitForLoadState();
	await popup.click( approve ? 'text=Approve' : 'text=Deny' );
	await popup.waitForLoadState();
}

/** Connect the site to the stub studio via the real UI flow. */
export async function connect( page: Page ): Promise< void > {
	await gotoSettings( page );
	const connectButton = page.getByRole( 'button', { name: /Connect with Book Surfer/i } );
	await connectButton.click();
	await completeConsent( page, true );
	await page.getByText( /Demo Studio/ ).waitFor( { timeout: 30000 } );
}

/** Disconnect via the UI. */
export async function disconnect( page: Page ): Promise< void > {
	await gotoSettings( page );
	await page.getByRole( 'button', { name: 'Disconnect', exact: true } ).first().click();
	await page.getByRole( 'dialog' ).getByRole( 'button', { name: 'Disconnect' } ).click();
	await page.getByRole( 'button', { name: /Connect with Book Surfer/i } ).waitFor();
}

/** Create a published post containing the given content, returning its permalink. */
export async function publishPostWithContent( page: Page, content: string ): Promise< string > {
	// Use the REST-free classic path: wp-admin post-new with the code editor is
	// fiddly, so drive WP-CLI-free creation through the block editor's raw handler.
	await page.goto( '/wp-admin/post-new.php?post_type=post' );
	await dismissEditorWelcome( page );

	await page.getByRole( 'button', { name: 'Options' } ).click();
	await page.getByRole( 'menuitem', { name: /Code editor/i } ).click();
	await page.locator( '.editor-post-text-editor' ).fill( content );

	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page.getByRole( 'region', { name: 'Editor publish' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
	const viewLink = page.getByRole( 'link', { name: /View Post/i } );
	await viewLink.waitFor();
	return ( await viewLink.getAttribute( 'href' ) ) || '';
}

/** Close the block editor's welcome modal if it appears. */
export async function dismissEditorWelcome( page: Page ): Promise< void > {
	const close = page.getByRole( 'button', { name: /Close dialog|Close/i } );
	try {
		await close.first().click( { timeout: 3000 } );
	} catch {
		// Modal not shown — fine.
	}
}
