/**
 * IRCC Credentials Store (Spec 60)
 * In-memory holder for decrypted IRCC credentials.
 *
 * Memory hygiene: clear() overwrites sensitive string fields before resetting
 * to reduce the window of plaintext residency in browser memory.
 *
 * Never persist this store to localStorage / sessionStorage.
 */

import { defineStore } from 'pinia';
import type { IrccCredentials, IrccSecurityQuestion } from '@/services/irccCredentialsService';

interface IrccState {
    credentials: IrccCredentials | null;
    loadedForCaseId: number | null;
    loadedAt: number;
    isLoading: boolean;
    isSaving: boolean;
}

const EMPTY_QUESTIONS = (): IrccSecurityQuestion[] =>
    Array.from({ length: 5 }, () => ({ pregunta: '', respuesta: '' }));

export const useIrccStore = defineStore('ircc', {
    state: (): IrccState => ({
        credentials: null,
        loadedForCaseId: null,
        loadedAt: 0,
        isLoading: false,
        isSaving: false,
    }),

    getters: {
        isLoaded: (state): boolean => state.credentials !== null,
        securityQuestions: (state): IrccSecurityQuestion[] =>
            state.credentials?.security_questions ?? EMPTY_QUESTIONS(),
    },

    actions: {
        setCredentials(caseId: number, data: IrccCredentials) {
            // Ensure exactly 5 security questions are always present in memory
            const questions = Array.isArray(data.security_questions) ? data.security_questions.slice(0, 5) : [];
            while (questions.length < 5) {
                questions.push({ pregunta: '', respuesta: '' });
            }

            this.credentials = {
                ...data,
                security_questions: questions,
            };
            this.loadedForCaseId = caseId;
            this.loadedAt = Date.now();
        },

        clear() {
            // Overwrite sensitive fields before nulling (memory hygiene)
            if (this.credentials) {
                const fields: (keyof IrccCredentials)[] = [
                    'ircc_username',
                    'ircc_password',
                    'email_address',
                    'email_password',
                    'application_number',
                    'notes',
                ];
                for (const field of fields) {
                    if (this.credentials[field]) {
                        (this.credentials as Record<string, unknown>)[field] = '\0'.repeat(32);
                    }
                }
                if (this.credentials.security_questions) {
                    this.credentials.security_questions = this.credentials.security_questions.map(() => ({
                        pregunta: '\0'.repeat(8),
                        respuesta: '\0'.repeat(8),
                    }));
                }
            }
            this.$reset();
        },
    },
});

export default useIrccStore;
