/**
 * OAuth Service
 * Handles OAuth connection status, redirect URLs, and disconnection for cloud providers
 */

import api from './api';
import type { OAuthStatus, OAuthProvider } from '@/types/oauth';

const oauthService = {
    /**
     * Get OAuth connection status for all providers
     */
    async getStatus(): Promise<OAuthStatus> {
        const response = await api.get<OAuthStatus>('/oauth/status');
        return response.data;
    },

    /**
     * Get the OAuth authorization redirect URL for a provider
     */
    async getRedirectUrl(provider: OAuthProvider): Promise<string> {
        const response = await api.get<{ url: string }>(`/oauth/${provider}/redirect`);
        return response.data.url;
    },

    /**
     * Disconnect a cloud storage provider
     */
    async disconnect(provider: OAuthProvider): Promise<void> {
        await api.delete(`/oauth/${provider}/disconnect`);
    },
};

export default oauthService;
