<script lang="ts">
    /**
     * Login.svelte — canonical admin login page.
     *
     * Mirrors Laravel Breeze 1.x's Inertia login form structurally. The
     * Svelte page is hand-rolled because Breeze 1.x ships only React/Vue
     * stubs for the Laravel 10 line; the controllers/routes/middleware
     * ARE the Breeze canonical shape (see `app/Http/Controllers/Auth/`).
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Inertia form posts to `/login` via `useForm`. The CSRF token is
     *     read from the shared `csrf_token` Inertia prop (sourced from
     *     HandleInertiaRequests::share) and merged into the form payload
     *     via `transform()`. This is belt-and-braces: Inertia's client
     *     ALSO reads the <meta name="csrf-token"> tag automatically, but
     *     surfacing it via props removes any dependency on the meta tag
     *     scraping path (which has been the source of subtle 419s in
     *     Laravel 10 + Inertia 2 setups).
     *   - The page has a "← Back to home" breadcrumb so a failed login
     *     attempt doesn't trap the admin on the form. The breadcrumb is
     *     NOT a "cancel" button — it just routes to /, which is the
     *     temple's public home.
     *   - No registration link. No forgot-password link. Single canonical
     *     admin authenticates via env-driven credentials only.
     *   - Layout: a single centered card. No public-site mandala/watermark.
     *     The admin kernel is a separate visual surface.
     */
    import { useForm, page } from '@inertiajs/svelte';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Button } from '$shared/ui/button';
    import { ArrowLeft } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Props = PageComponentProps<{
        status?: string | null;
    }>;

    let { status }: Props = $props();

    const csrfToken = $derived($page.props.csrf_token ?? '');

    /**
     * Form-fields shape. The Inertia useForm() generic is parameterised
     * with this; the .transform() callback receives `LoginFields` (NOT
     * the InertiaForm<T> proxy — that's what `: typeof $form` was
     * incorrectly inferring, which is why svelte-check flagged the
     * call site: the proxy carries isDirty, errors, progress, etc. that
     * aren't part of the form's wire shape).
     */
    type LoginFields = {
        email: string;
        password: string;
        remember: boolean;
    };

    const form = useForm<LoginFields>({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        $form
            .transform((data: LoginFields) => ({
                ...data,
                _token: csrfToken,
            }))
            .post('/login', {
                onFinish: () => $form.reset('password'),
            });
    }
</script>

<div class="flex min-h-screen flex-col bg-background">
    <!--
        Breadcrumb — placed above the centered card so a failed login
        attempt doesn't trap the admin on the form. Single "Back to home"
        link is intentional: there's no other admin route to navigate to
        while the user is unauthenticated.
    -->
    <nav
        class="container flex h-16 items-center text-sm text-muted-foreground"
        aria-label="Breadcrumb"
    >
        <ol class="flex items-center gap-2">
            <li>
                <a
                    href="/"
                    class="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    <span>Back to home</span>
                </a>
            </li>
            <li aria-hidden="true">/</li>
            <li class="text-foreground" aria-current="page">
                Admin sign in
            </li>
        </ol>
    </nav>

    <div class="flex flex-1 items-center justify-center px-4 pb-12">
        <div class="w-full max-w-sm space-y-6 rounded-lg border border-border bg-card p-8 shadow-sm">
            <div class="space-y-1 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Admin sign in
                </h1>
                <p class="text-sm text-muted-foreground">
                    Enter your credentials to access the temple trust console.
                </p>
            </div>

            {#if status}
                <div
                    class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary"
                    role="status"
                >
                    {status}
                </div>
            {/if}

            <form onsubmit={submit} class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        bind:value={$form.email}
                        placeholder="you@example.com"
                    />
                    {#if $form.errors.email}
                        <p class="text-sm text-destructive">{$form.errors.email}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        bind:value={$form.password}
                    />
                    {#if $form.errors.password}
                        <p class="text-sm text-destructive">{$form.errors.password}</p>
                    {/if}
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        bind:checked={$form.remember}
                        class="h-4 w-4 rounded border-input"
                    />
                    <Label for="remember">Remember me</Label>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    disabled={$form.processing}
                >
                    {$form.processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>
        </div>
    </div>
</div>
