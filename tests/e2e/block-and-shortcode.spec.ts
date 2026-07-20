/**
 * Block + shortcode render E2E.
 *
 * BSWP-47. The assertion that matters: the front-end iframe src is exactly what the
 * shared URL builder produces, and it keeps rendering after a disconnect.
 *
 * @package
 */

import { expect, test } from '@playwright/test';
import {
	connect,
	disconnect,
	dismissEditorWelcome,
	login,
	publishPostWithContent,
	resetStub,
} from './helpers';

test.beforeEach( async ( { page } ) => {
	await resetStub( page );
	await login( page );
} );

test( 'insert the block, pick a class, publish, and assert the front-end iframe', async ( {
	page,
} ) => {
	await connect( page );

	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await dismissEditorWelcome( page );

	// Insert the block.
	await page
		.getByRole( 'button', { name: /Toggle block inserter|Add block/i } )
		.first()
		.click();
	await page.getByPlaceholder( /Search/i ).fill( 'Book Surfer' );
	await page
		.getByRole( 'option', { name: /Book Surfer Booking Form/i } )
		.click();

	// The placeholder lists classes; choose one, then a layout.
	await page.getByText( 'Sunrise Yoga' ).waitFor();
	await page
		.locator( 'li', { hasText: 'Sunrise Yoga' } )
		.getByRole( 'button', { name: 'Use this' } )
		.click();
	await page.getByRole( 'button', { name: /Modern/ } ).click();

	// The live preview appears.
	await expect(
		page.locator( '.booksurfer-block-preview iframe' )
	).toBeVisible( { timeout: 20000 } );

	// Publish.
	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page
		.getByRole( 'region', { name: 'Editor publish' } )
		.getByRole( 'button', { name: 'Publish', exact: true } )
		.click();
	const viewLink = page.getByRole( 'link', { name: /View Page/i } );
	await viewLink.waitFor();
	const url = await viewLink.getAttribute( 'href' );

	// Front end: the iframe carries the expected src and attributes.
	await page.goto( url! );
	const iframe = page.locator( 'iframe' ).first();
	await expect( iframe ).toBeVisible();

	const src = await iframe.getAttribute( 'src' );
	expect( src ).toContain( '/demo-studio/book/1' );
	expect( src ).toContain( 'layout=modern' );
	expect( src ).toContain( 'primary=0D9488' );
	expect( src ).toContain( 'font=Roboto' );
	expect( src ).toContain( 'radius=14' );

	await expect( iframe ).toHaveAttribute( 'allow', 'payment' );
	await expect( iframe ).toHaveAttribute( 'loading', 'lazy' );

	// The Modern layout's default size is applied.
	const style = await iframe.getAttribute( 'style' );
	expect( style ).toContain( 'width:520px' );
	expect( style ).toContain( 'height:680px' );

	// The stub form actually renders inside the iframe.
	await expect(
		page
			.frameLocator( 'iframe' )
			.locator( '[data-testid="booksurfer-stub-form"]' )
	).toBeVisible();
} );

test( 'the shortcode renders the same iframe in the classic editor path', async ( {
	page,
} ) => {
	await connect( page );

	const url = await publishPostWithContent(
		page,
		'[booksurfer id="2" layout="compact"]'
	);

	await page.goto( url );
	const iframe = page.locator( 'iframe' ).first();
	await expect( iframe ).toBeVisible();

	const src = await iframe.getAttribute( 'src' );
	expect( src ).toContain( '/demo-studio/book/2' );
	expect( src ).toContain( 'layout=compact' );

	const style = await iframe.getAttribute( 'style' );
	expect( style ).toContain( 'width:360px' );
	expect( style ).toContain( 'height:400px' );
} );

test( 'a published embed keeps rendering after the site disconnects', async ( {
	page,
} ) => {
	await connect( page );

	const url = await publishPostWithContent(
		page,
		'[booksurfer id="1" handle="demo-studio"]'
	);

	await page.goto( url );
	const before = await page.locator( 'iframe' ).first().getAttribute( 'src' );

	await disconnect( page );

	await page.goto( url );
	const iframe = page.locator( 'iframe' ).first();
	await expect( iframe ).toBeVisible();
	expect( await iframe.getAttribute( 'src' ) ).toBe( before );
} );

test( 'the front end enqueues no plugin JS or CSS', async ( { page } ) => {
	await connect( page );
	const url = await publishPostWithContent(
		page,
		'[booksurfer id="1" handle="demo-studio"]'
	);

	const pluginAssets: string[] = [];
	page.on( 'request', ( request ) => {
		const requestUrl = request.url();
		if ( /plugins\/book-surfer\/build\//.test( requestUrl ) ) {
			pluginAssets.push( requestUrl );
		}
	} );

	await page.goto( url );
	await page.locator( 'iframe' ).first().waitFor();

	expect(
		pluginAssets,
		'The block must ship zero front-end assets.'
	).toEqual( [] );
} );

test( 'a misconfigured embed shows an editor notice but nothing to visitors', async ( {
	page,
	browser,
} ) => {
	await connect( page );
	const url = await publishPostWithContent( page, '[booksurfer]' );

	// Logged-in editor sees the inline notice.
	await page.goto( url );
	await expect( page.getByText( /not configured/i ) ).toBeVisible();

	// An anonymous visitor sees nothing at all.
	const anon = await browser.newContext();
	const anonPage = await anon.newPage();
	await anonPage.goto( url );
	await expect( anonPage.getByText( /not configured/i ) ).toHaveCount( 0 );
	await expect( anonPage.locator( 'iframe' ) ).toHaveCount( 0 );
	await anon.close();
} );
