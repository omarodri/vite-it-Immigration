/**
 * Global Type Declarations
 * Ambient declarations for the application
 */

/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

/**
 * Environment Variables
 */
interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly VITE_APP_URL: string;
    readonly VITE_API_URL: string;
    readonly VITE_API_VERSION: string;
    readonly MODE: string;
    readonly DEV: boolean;
    readonly PROD: boolean;
    readonly SSR: boolean;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

/**
 * Window extensions
 */
interface Window {
    __APP_VERSION__?: string;
    __BUILD_TIME__?: string;
}

/**
 * Laravel Echo types (if using broadcasting)
 */
declare global {
    interface Window {
        Echo?: any;
        Pusher?: any;
    }
}

/**
 * Utility types
 */
declare global {
    /**
     * Make all properties of T optional except for keys in K
     */
    type PartialExcept<T, K extends keyof T> = Partial<Omit<T, K>> & Pick<T, K>;

    /**
     * Make all properties of T required except for keys in K
     */
    type RequiredExcept<T, K extends keyof T> = Required<Omit<T, K>> & Partial<Pick<T, K>>;

    /**
     * Extract the type of array elements
     */
    type ArrayElement<T> = T extends readonly (infer U)[] ? U : never;

    /**
     * Make specified properties nullable
     */
    type Nullable<T, K extends keyof T = keyof T> = Omit<T, K> & { [P in K]: T[P] | null };

    /**
     * Async function return type
     */
    type AsyncReturnType<T extends (...args: any) => Promise<any>> = T extends (...args: any) => Promise<infer R> ? R : any;
}

export {};
