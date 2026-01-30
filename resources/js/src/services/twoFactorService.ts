import api from './api';

export interface TwoFactorSetupResponse {
    qr_code: string;
    secret: string;
    recovery_codes: string[];
}

export interface RecoveryCodesResponse {
    recovery_codes: string[];
    message?: string;
}

export interface TwoFactorChallengeData {
    code?: string;
    recovery_code?: string;
}

export interface TwoFactorChallengeResponse {
    message: string;
    user?: any;
    two_factor_required?: boolean;
}

export interface MessageResponse {
    message: string;
}

const twoFactorService = {
    async enable(): Promise<TwoFactorSetupResponse> {
        const response = await api.post<TwoFactorSetupResponse>('/two-factor/enable');
        return response.data;
    },

    async confirm(code: string): Promise<MessageResponse> {
        const response = await api.post<MessageResponse>('/two-factor/confirm', { code });
        return response.data;
    },

    async disable(password: string): Promise<MessageResponse> {
        const response = await api.delete<MessageResponse>('/two-factor/disable', {
            data: { password },
        });
        return response.data;
    },

    async getRecoveryCodes(): Promise<RecoveryCodesResponse> {
        const response = await api.get<RecoveryCodesResponse>('/two-factor/recovery-codes');
        return response.data;
    },

    async regenerateRecoveryCodes(password: string): Promise<RecoveryCodesResponse> {
        const response = await api.post<RecoveryCodesResponse>('/two-factor/recovery-codes', { password });
        return response.data;
    },

    async challenge(data: TwoFactorChallengeData): Promise<TwoFactorChallengeResponse> {
        const response = await api.post<TwoFactorChallengeResponse>('/two-factor-challenge', data);
        return response.data;
    },
};

export default twoFactorService;
