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
     *   - Inertia form posts to `/login` via `useForm`. Validation errors
     *     are surfaced inline; the throttle error message renders
     *     identically to Breeze (rate-limit is enforced server-side).
     *   - No registration link. No forgot-password link. Single canonical
     *     admin authenticates via env-driven credentials only.
     *   - Layout: a single centered card. No public-site mandala/watermark.
     *     The admin kernel is a separate visual surface.
     */
    import { useForm } from '@inertiajs/svelte';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Button } from '$shared/ui/button';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Props = PageComponentProps<{
        status?: string | null;
    }>;

    let { status }: Props = $props();

    const form = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        $form.post('/login', {
            onFinish: () => $form.reset('password'),
        });
    }
</script>

<div class="flex min-h-screen items-center justify-center bg-background px-4">
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
                    placeholder="admin@vishwaguru.local"
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
