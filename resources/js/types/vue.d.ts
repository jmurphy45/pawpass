declare module 'vue' {
    interface ComponentCustomProperties {
        route: (name: string, params?: Record<string, unknown> | unknown[], absolute?: boolean) => string;
    }
}

export {};
