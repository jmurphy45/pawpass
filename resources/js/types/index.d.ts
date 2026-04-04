declare function route(name: string, params?: Record<string, unknown> | Array<unknown>, absolute?: boolean): string;

declare global {
    const route: (name: string, params?: Record<string, unknown> | Array<unknown>, absolute?: boolean) => string;
}

export interface Tenant {
    name: string;
    slug: string;
    primary_color: string;
    logo_url: string | null;
    business_type: 'daycare' | 'kennel' | 'hybrid';
}

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    role: string;
}

export interface Dog {
    id: string;
    name: string;
    breed: string | null;
    color: string | null;
    credit_balance: number;
    credits_expire_at: string | null;
    unlimited_pass_expires_at: string | null;
    deleted_at: string | null;
}

export interface Package {
    id: string;
    name: string;
    price_cents: number;
    credits: number;
    max_dogs: number;
    billing_interval: string | null;
    duration_days: number | null;
    is_featured: boolean;
}

export interface Order {
    id: string;
    package_name: string;
    dog_names: string[];
    amount_cents: number;
    status: string;
    created_at: string;
    has_receipt: boolean;
}

export interface Attendance {
    id: string;
    dog_name: string;
    checked_in_at: string;
    checked_out_at: string | null;
    credit_delta: number;
}

export interface Notification {
    id: string;
    type: string;
    message: string | null;
    read_at: string | null;
    created_at: string;
}

export interface CreditLedger {
    id: string;
    type: string;
    amount: number;
    balance_after: number;
    note: string | null;
    expires_at: string | null;
    created_at: string;
}

export interface PageProps {
    [key: string]: unknown;
    tenant: Tenant | null;
    tenantPlan: string | null;
    tenantStatus: string | null;
    tenantFeatures: string[];
    auth: {
        user: AuthUser | null;
    };
    unreadCount: number;
    flash: {
        success: string | null;
        error: string | null;
    };
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}
