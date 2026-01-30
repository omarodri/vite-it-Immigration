/**
 * User Types
 * Interfaces for user-related data structures
 */

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    two_factor_confirmed_at?: string | null;
    roles?: Role[];
    permissions?: string[];
}

export interface Role {
    id: number;
    name: string;
    display_name?: string;
    description?: string;
}

export interface SocialLinks {
    twitter?: string;
    linkedin?: string;
    github?: string;
    facebook?: string;
}

export interface UserProfile {
    id: number;
    user_id: number;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    avatar_url: string | null;
    bio: string | null;
    date_of_birth: string | null;
    timezone: string | null;
    language: string | null;
    website: string | null;
    social_links: SocialLinks | null;
    created_at: string;
    updated_at: string;
}

export interface UserWithProfile extends User {
    profile?: UserProfile;
}

export interface CreateUserData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles?: number[];
    send_welcome_email?: boolean;
}

export interface UpdateUserData {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    roles?: number[];
}

export interface UpdateProfileData {
    name?: string;
    phone?: string;
    address?: string;
    city?: string;
    state?: string;
    country?: string;
    postal_code?: string;
    bio?: string;
    date_of_birth?: string;
    timezone?: string;
    language?: string;
    website?: string;
    social_links?: SocialLinks;
}

export interface ChangePasswordData {
    current_password: string;
    password: string;
    password_confirmation: string;
}
