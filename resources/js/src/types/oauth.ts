export interface OAuthProviderStatus {
    connected: boolean;
    email?: string;
    expires_at?: string;
}

export interface OAuthStatus {
    microsoft: OAuthProviderStatus;
    google: OAuthProviderStatus;
}

export type OAuthProvider = 'microsoft' | 'google';
