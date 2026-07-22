<script lang="ts">
    import MandalaDecoration from './MandalaDecoration.svelte';

    interface Props {
        appName: string;
        appUrl: string;
    }

    let { appName, appUrl: _appUrl }: Props = $props();

    const year = new Date().getFullYear();
    const hostname = (() => {
        try {
            return new URL(_appUrl).hostname;
        } catch {
            return '';
        }
    })();
</script>

<footer class="border-t border-border/60 bg-muted/30">
    <div class="container py-12 lg:py-16">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-3">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <MandalaDecoration size={48} tint="gold" />
                    <span class="font-serif text-lg font-semibold">
                        {appName}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground max-w-xs">
                    Supporting daily pooja, annadanam, and temple maintenance
                    through seva and the generosity of devotees.
                </p>
            </div>

            <div class="space-y-3">
                <h3 class="font-serif text-base font-semibold">Explore</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/campaigns" class="text-muted-foreground hover:text-primary">Campaigns</a></li>
                    <li><a href="/events" class="text-muted-foreground hover:text-primary">Events</a></li>
                    <li><a href="/gallery" class="text-muted-foreground hover:text-primary">Gallery</a></li>
                    <li><a href="/about" class="text-muted-foreground hover:text-primary">About</a></li>
                    <li><a href="/contact" class="text-muted-foreground hover:text-primary">Contact</a></li>
                    <li><a href="/donate" class="text-muted-foreground hover:text-primary">Donate</a></li>
                </ul>
            </div>

            <div class="space-y-3">
                <h3 class="font-serif text-base font-semibold">Trust</h3>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    <li>Registered charitable trust</li>
                    <li>80G tax-eligible donations</li>
                    <li>Secure payments via Razorpay</li>
                </ul>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-2 border-t border-border/60 pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>© {year} {appName}{hostname ? ` · ${hostname}` : ''}</span>
            <span>Built with care · Powered by Razorpay</span>
        </div>
    </div>
</footer>