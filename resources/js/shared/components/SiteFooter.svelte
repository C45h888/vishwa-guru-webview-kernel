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

    const social = [
        { href: '#', label: 'Facebook' },
        { href: '#', label: 'X' },
        { href: '#', label: 'Instagram' },
        { href: '#', label: 'YouTube' },
    ];

    const legal = [
        { href: '/terms', label: 'Terms' },
        { href: '/privacy', label: 'Privacy' },
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
                    A trust sustained by the daily care of those in our
                    charge, the generosity of those who call this seva
                    their own, and the trustees who carry both forward.
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
                        {appName}<br />
                        Mysore, Karnataka
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
                        <li>Registered charitable trust</li>
                        <li>Secure payments via Razorpay</li>
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
            <span>© {year} {appName}</span>
            <span>Secure payments via Razorpay</span>
        </div>
    </div>
</footer>
