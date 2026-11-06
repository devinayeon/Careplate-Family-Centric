export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    avatar?: string;
    roles?: string[];
    permissions?: string[];
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
}

export interface Auth {
    user: User;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

export * from './landing';
