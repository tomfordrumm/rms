export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthRestaurant = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
};

export type Auth = {
    user: User;
    restaurant: AuthRestaurant | null;
    hasRestaurant: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
