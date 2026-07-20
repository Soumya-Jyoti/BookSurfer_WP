/**
 * Shared data models.
 *
 * BSWP-25/30. Ported from the Framer plugin's useClasses.ts interfaces — the proxy
 * passes the SaaS response through unchanged in shape, so these match 1:1.
 *
 * @package
 */

export interface ClassInfo {
	id: string | number;
	name: string;
	class_type: string;
	instructor: string;
	price: string;
	currency?: string;
	location: string;
	embed_url: string;
}

export interface StudioInfo {
	name: string;
	location?: string;
	bio?: string;
	address?: string;
	email?: string;
	handle: string;
}

export interface ClassesResponse {
	success: boolean;
	classes: ClassInfo[];
	studio?: StudioInfo;
}

export interface ConnectionStatus {
	connected: boolean;
	studio: { handle: string; name: string } | null;
	needs_reconnect: boolean;
	pending: boolean;
	degraded?: boolean;
}

export interface NormalizedError {
	code: string;
	message: string;
	status: number;
}

export interface BootstrapData {
	restBase: string;
	nonce: string;
	appBase: string;
	connectUrl: string;
	dashboardUrl: string;
	siteUrl: string;
	apiBase?: string;
	settingsUrl?: string;
	status: ConnectionStatus;
}
