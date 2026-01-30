/**
 * Profile Service
 * Handles all profile-related API calls
 */

import api from './api';
import type { User, UserProfile, UpdateProfileData, ChangePasswordData } from '@/types/user';

export interface ProfileResponse {
    user: User;
    profile: UserProfile | null;
}

export interface UpdateProfileResponse {
    message: string;
    user: User;
    profile: UserProfile;
}

export interface AvatarUploadResponse {
    message: string;
    avatar_url: string;
    profile: UserProfile;
}

export interface MessageResponse {
    message: string;
}

const profileService = {
    /**
     * Get the authenticated user's profile
     */
    async getProfile(): Promise<ProfileResponse> {
        const response = await api.get<ProfileResponse>('/profile');
        return response.data;
    },

    /**
     * Update the authenticated user's profile
     */
    async updateProfile(data: UpdateProfileData): Promise<UpdateProfileResponse> {
        const response = await api.put<UpdateProfileResponse>('/profile', data);
        return response.data;
    },

    /**
     * Upload or update avatar
     */
    async uploadAvatar(file: File): Promise<AvatarUploadResponse> {
        const formData = new FormData();
        formData.append('avatar', file);

        const response = await api.post<AvatarUploadResponse>('/profile/avatar', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        return response.data;
    },

    /**
     * Delete avatar
     */
    async deleteAvatar(): Promise<MessageResponse> {
        const response = await api.delete<MessageResponse>('/profile/avatar');
        return response.data;
    },

    /**
     * Change password
     */
    async changePassword(data: ChangePasswordData): Promise<MessageResponse> {
        const response = await api.post<MessageResponse>('/profile/password', data);
        return response.data;
    },
};

export default profileService;
