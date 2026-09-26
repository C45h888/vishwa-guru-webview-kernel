<script lang="ts">
    /**
     * SupportsRow — quiet 4-item icon row that lists what an offering
     * sustains. Replaces the 21:9 banner image that used to live in
     * the "Where every rupee goes" section.
     *
     * Doctrine:
     *   - On desktop, four equal columns. On mobile, a 2x2 grid.
     *   - Each item has a small icon, a label, and a one-line note.
     *     No cards; the row is meant to feel like a ledger, not a
     *     feature grid.
     *   - Icons are picked by index from a small allowlist (lucide
     *     components) so the visual rhythm stays consistent across
     *     causes. Adding a new support item is just appending to the
     *     `items` prop.
     */
    import {
        HandHeart,
        Wrench,
        Utensils,
        Users,
        type Icon as IconType,
    } from 'lucide-svelte';
    import type { ComponentType } from 'svelte';

    interface SupportItem {
        label: string;
        note?: string;
    }

    interface Props {
        items: readonly SupportItem[];
        eyebrow?: string;
        headline?: string;
    }

    let {
        items,
        eyebrow = 'What every offering supports',
        headline = 'Where every rupee goes',
    }: Props = $props();

    /**
     * Icon allowlist. We pick by `i % length` so the visual rhythm
     * cycles predictably across pages — the same four icons appear in
     * the same order wherever a SupportsRow is rendered.
     */
    const ICON_ALLOWLIST: ComponentType<IconType>[] = [
        HandHeart,
        Wrench,
        Utensils,
        Users,
    ];
</script>

<section class="bg-ivory py-16 lg:py-24">
    <div class="container">
        <div class="mx-auto max-w-5xl space-y-10">
            <div class="mx-auto max-w-2xl space-y-3 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
                >
                    {eyebrow}
                </p>
                <h2
                    class="font-serif text-2xl font-semibold leading-tight lg:text-3xl"
                >
                    {headline}
                </h2>
            </div>

            <ul
                class="grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-4"
                aria-label="What an offering supports"
            >
                {#each items as item, i (item.label)}
                    {@const Icon = ICON_ALLOWLIST[i % ICON_ALLOWLIST.length]}
                    <li class="flex flex-col items-center gap-3 text-center">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <Icon
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="font-serif text-base font-semibold leading-snug text-foreground"
                        >
                            {item.label}
                        </p>
                        {#if item.note}
                            <p
                                class="max-w-[16ch] text-xs leading-relaxed text-muted-foreground"
                            >
                                {item.note}
                            </p>
                        {/if}
                    </li>
                {/each}
            </ul>
        </div>
    </div>
</section>
