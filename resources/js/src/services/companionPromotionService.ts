import api from '@/services/api';

export interface PromotionEligibilityResponse {
    eligible: boolean;
    reasons: string[];
    already_promoted: boolean;
    existing_client_id: number | null;
}

export interface PromotionSuccessData {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    status: string;
}

const companionPromotionService = {
    checkEligibility(companionId: number) {
        return api.get<{ data: PromotionEligibilityResponse }>(
            `/companions/${companionId}/promotion-eligibility`
        );
    },

    promote(companionId: number) {
        return api.post<{ data: PromotionSuccessData; message: string }>(
            `/companions/${companionId}/promote-to-client`,
            { confirmed: true }
        );
    },
};

export default companionPromotionService;
