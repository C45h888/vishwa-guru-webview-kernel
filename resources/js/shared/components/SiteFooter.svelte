<script lang="ts">
    import MandalaDecoration from './MandalaDecoration.svelte';
    import { Mail, Phone, Shield } from 'lucide-svelte';

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
</script>

<footer class="border-t border-border/40 bg-ivory">
    <div class="container py-16 lg:py-20">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-3 lg:gap-16">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <MandalaDecoration size={40} tint="gold" />
                    <span class="font-serif text-lg font-semibold">
                        {appName}
                    </span>
                </div>
                <p
                    class="max-w-xs text-sm leading-relaxed text-muted-foreground"
                >
                    Preserving sacred traditions through daily pooja,
                    annadanam, and the care of our temple.
                </p>
            </div>

            <div class="space-y-3">
                <h3
                    class="font-serif text-xs font-semibold uppercase tracking-[0.2em] text-foreground/70"
                >
                    Quicklinks
                </h3>
                <ul class="space-y-2 text-sm">
                    {#each quicklinks as link (link.href)}
                        <li>
                            <a
                                href={link.href}
                                class="text-muted-foreground transition-colors hover:text-primary"
                            >
                                {link.label}
                            </a>
                        </li>
                    {/each}
                </ul>
            </div>

            <div class="space-y-3">
                <h3
                    class="font-serif text-xs font-semibold uppercase tracking-[0.2em] text-foreground/70"
                >
                    Trust
                </h3>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    <li class="flex items-center gap-2">
                        <Mail class="h-3.5 w-3.5 text-primary/70" aria-hidden="true" />
                        <span>Registered charitable trust</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <Phone class="h-3.5 w-3.5 text-primary/70" aria-hidden="true" />
                        <span>80G tax-eligible donations</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <Shield class="h-3.5 w-3.5 text-primary/70" aria-hidden="true" />
                        <span>Secure payments via Razorpay</span>
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="mt-12 flex flex-col gap-2 border-t border-border/40 pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
        >
            <span>© {year} {appName}</span>
            <span>Powered by Razorpay</span>
        </div>
    </div>
</footer>