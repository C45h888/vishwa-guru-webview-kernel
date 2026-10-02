<script lang="ts">
    import MandalaDecoration from './MandalaDecoration.svelte';

    interface Props {
        appName: string;
        appUrl: string;
    }

    let { appName, appUrl: _appUrl }: Props = $props();

    const year = new Date().getFullYear();

    const quicklinks = [
        { href: '/campaigns', label: 'Campaigns' },
        { href: '/events', label: 'Events' },
        { href: '/gallery', label: 'Gallery' },
        { href: '/about', label: 'About' },
        { href: '/contact', label: 'Contact' },
    ];

    // Social destinations remain plain text until the trust confirms
    // which channels are active, so coming-soon labels are not tabbable links.
    const social = [
        { label: 'Facebook', ariaLabel: 'Facebook (coming soon)' },
        { label: 'X', ariaLabel: 'X (coming soon)' },
        { label: 'Instagram', ariaLabel: 'Instagram (coming soon)' },
        { label: 'YouTube', ariaLabel: 'YouTube (coming soon)' },
    ];

    // Legal and terms content use the existing public CMS routes.
    const legal = [
        { href: '/legal', label: 'Legal' },
        { href: '/privacy', label: 'Privacy Policy' },
        { href: '/terms', label: 'Terms & Conditions' },
    ];
</script>

<footer class="border-t border-primary/30 bg-background">
    <div class="container py-16 lg:py-20">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-4 lg:gap-12">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <MandalaDecoration size={56} tint="gold" class="shrink-0" />
                    <span
                        class="font-serif text-lg font-semibold tracking-tight text-foreground"
                    >
                        {appName}
                    </span>
                </div>
                <p class="max-w-xs text-sm leading-relaxed text-muted-foreground">
                    A charitable trust led by Sri Ram Ram Das Guruji.
                    Since 2007 the trust has schooled children in need of
                    care — including blind and deaf pupils receiving free
                    education — with daily annadanam of food, water, and
                    school events. Donations enter one pooled Schools &amp;
                    Campus Fund for daily school operations and the staged
                    proposed campus programme near Nanjangud, outside Mysore.
                </p>
            </div>

            <div class="space-y-3">
                <h3
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    Quicklinks
                </h3>
                <ul class="space-y-2 text-sm">
                    {#each quicklinks as link (link.href)}
                        <li>
                            <a
                                href={link.href}
                                class="text-foreground/80 transition-colors hover:text-primary"
                            >
                                {link.label}
                            </a>
                        </li>
                    {/each}
                </ul>
            </div>

            <div class="space-y-3">
                <h3
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    Visit
                </h3>
                <address
                    class="space-y-2 text-sm not-italic text-foreground/80"
                >
                    <p>
                        School work and proposed campus near Nanjangud.<br />
                        <a href="/contact" class="underline-offset-2 hover:underline">Trust office address and directions</a>
                    </p>
                    <p>
                        <a
                            href="tel:+919844132318"
                            class="transition-colors hover:text-primary"
                        >
                            +91 98441 32318
                        </a>
                    </p>
                    <p>
                        <a
                            href="/contact"
                            class="transition-colors hover:text-primary"
                        >
                            Contact us
                        </a>
                    </p>
                </address>
            </div>

            <div class="space-y-5">
                <div class="space-y-3">
                    <h3
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                    >
                        Trust
                    </h3>
                    <ul class="space-y-2 text-sm text-foreground/80">
                        <li>Charitable trust · Nanjangud, Karnataka</li>
                        <li>Secure payments via Razorpay</li>
                        <li>Official receipt for every donation</li>
                    </ul>
                </div>

                <div class="space-y-3">
                    <h4
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                    >
                        Follow
                    </h4>
                    <ul class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        {#each social as link (link.label)}
                            <li>
                                <span
                                    aria-label={link.ariaLabel}
                                    aria-disabled="true"
                                    class="text-foreground/40"
                                >
                                    {link.label}
                                </span>
                            </li>
                        {/each}
                    </ul>
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    {#each legal as link (link.href)}
                        <a
                            href={link.href}
                            class="text-muted-foreground transition-colors hover:text-primary"
                        >
                            {link.label}
                        </a>
                    {/each}
                </div>
            </div>
        </div>

        <div
            class="mt-12 flex flex-col gap-2 border-t border-border/60 pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
            <span>© {year} {appName} — A charitable trust, Nanjangud, Karnataka</span>
            <span>Secure payments via Razorpay</span>
        </div>
    </div>
</footer>
