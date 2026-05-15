/**
 * IRCC Credentials Service (Spec 60)
 * Handles all encrypted IRCC credential vault API calls.
 *
 * Note: `api` already has `baseURL: '/api'`, so we use relative paths.
 */

import api from './api';

export interface IrccSecurityQuestion {
    pregunta: string;
    respuesta: string;
}

export interface IrccCredentials {
    exists: boolean;
    ircc_username: string | null;
    ircc_password: string | null;
    email_address: string | null;
    email_password: string | null;
    application_number: string | null;
    notes: string | null;
    security_questions: IrccSecurityQuestion[];
    created_by_name: string | null;
    updated_at: string | null;
}

export interface IrccCredentialsPayload {
    ircc_username?: string | null;
    ircc_password?: string | null;
    email_address?: string | null;
    email_password?: string | null;
    application_number?: string | null;
    notes?: string | null;
    security_questions?: IrccSecurityQuestion[];
}

const irccCredentialsService = {
    getAvailability(caseId: number) {
        return api.get<{ available: boolean }>(`/cases/${caseId}/ircc-credentials/availability`);
    },

    get(caseId: number) {
        return api.get<{ data: IrccCredentials }>(`/cases/${caseId}/ircc-credentials`);
    },

    save(caseId: number, payload: IrccCredentialsPayload) {
        return api.put<{ success: boolean; updated_at: string }>(
            `/cases/${caseId}/ircc-credentials`,
            payload,
        );
    },
};

export default irccCredentialsService;
export { irccCredentialsService };
