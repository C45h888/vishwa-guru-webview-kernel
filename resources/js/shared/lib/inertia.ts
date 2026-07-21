export interface AuthUser {
    id: string;
    name: string;
    email: string;
}

export interface SharedPageProps {
    appName: string;
    appUrl: string;
    authUser: AuthUser | null;
}

export type AppPageProps<TProps = Record<string, unknown>> = TProps & SharedPageProps;

export type PageComponentProps<TProps = Record<string, unknown>> = AppPageProps<TProps>;
