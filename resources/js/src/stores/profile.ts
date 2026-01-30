/**
 * Profile Store
 * Manages user profile state and operations
 */

import { defineStore } from 'pinia';
import profileService from '@/services/profileService';
import type { User, UserProfile, UpdateProfileData, ChangePasswordData } from '@/types/user';

interface ProfileState {
    user: User | null;
    profile: UserProfile | null;
    isLoading: boolean;
    isSaving: boolean;
    isUploadingAvatar: boolean;
    error: string | null;
}

export const useProfileStore = defineStore('profile', {
    state: (): ProfileState => ({
        user: null,
        profile: null,
        isLoading: false,
        isSaving: false,
        isUploadingAvatar: false,
        error: null,
    }),

    getters: {
        fullName: (state): string => state.user?.name || '',

        email: (state): string => state.user?.email || '',

        avatarUrl: (state): string | null => state.profile?.avatar_url || null,

        hasProfile: (state): boolean => state.profile !== null,

        initials: (state): string => {
            if (!state.user?.name) return '';
            return state.user.name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase()
                .slice(0, 2);
        },

        location: (state): string => {
            const parts = [];
            if (state.profile?.city) parts.push(state.profile.city);
            if (state.profile?.state) parts.push(state.profile.state);
            if (state.profile?.country) parts.push(state.profile.country);
            return parts.join(', ');
        },
    },

    actions: {
        async fetchProfile() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await profileService.getProfile();
                this.user = response.user;
                this.profile = response.profile;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to load profile';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateProfile(data: UpdateProfileData) {
            this.isSaving = true;
            this.error = null;

            try {
                const response = await profileService.updateProfile(data);
                this.user = response.user;
                this.profile = response.profile;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update profile';
                throw error;
            } finally {
                this.isSaving = false;
            }
        },

        async uploadAvatar(file: File) {
            this.isUploadingAvatar = true;
            this.error = null;

            try {
                const response = await profileService.uploadAvatar(file);
                if (this.profile) {
                    this.profile.avatar_url = response.avatar_url;
                } else {
                    this.profile = response.profile;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to upload avatar';
                throw error;
            } finally {
                this.isUploadingAvatar = false;
            }
        },

        async deleteAvatar() {
            this.isUploadingAvatar = true;
            this.error = null;

            try {
                const response = await profileService.deleteAvatar();
                if (this.profile) {
                    this.profile.avatar_url = null;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete avatar';
                throw error;
            } finally {
                this.isUploadingAvatar = false;
            }
        },

        async changePassword(data: ChangePasswordData) {
            this.isSaving = true;
            this.error = null;

            try {
                const response = await profileService.changePassword(data);
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to change password';
                throw error;
            } finally {
                this.isSaving = false;
            }
        },

        clearError() {
            this.error = null;
        },

        reset() {
            this.user = null;
            this.profile = null;
            this.isLoading = false;
            this.isSaving = false;
            this.isUploadingAvatar = false;
            this.error = null;
        },
    },
});
