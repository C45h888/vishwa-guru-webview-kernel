<script lang="ts">
    import MandalaDecoration from './MandalaDecoration.svelte';
    import PublicMediaImage from './PublicMediaImage.svelte';
    import GradientPanel from './GradientPanel.svelte';
    import type { PublicMediaProps } from '$shared/lib/inertia';

    /**
     * Three image cards under the hero — no headings, no eyebrows, no buttons.
     * A single line of serif italic copy sits below each image.
     * Card top border is a 4px saffron line so the trio reads as a unit
     * even when images are absent.
     */

    interface Card {
        image: PublicMediaProps | null;
        alt: string;
        copy: string;
    }

    interface Props {
        firstImage: PublicMediaProps | null;
        secondImage: PublicMediaProps | null;
        thirdImage: PublicMediaProps | null;
    }

    let { firstImage, secondImage, thirdImage }: Props = $props();

    const cards = $derived<Card[]>([
        {
            image: firstImage,
            alt: 'Guru blessing — years of dedication to the right causes',
            copy: 'Years of dedication to the right causes',
        },
        {
            image: secondImage,
            alt: 'Children dancing — harbouring and grace',
            copy: 'Harbouring and grace in ones which were abondoned',
        },
        {
            image: thirdImage,
            alt: 'The proposed campus near Nanjangud',
            copy: 'Devoting our resources to the divine for the bettermnet of the community',
        },
    ]);
</script>

<section class="relative overflow-hidden bg-background py-20 lg:py-28">
    <div
        class="pointer-events-none absolute -right-32 top-1/2 -translate-y-1/2 opacity-[0.06]"
        aria-hidden="true"
    >
        <MandalaDecoration size={420} tint="gold" />
    </div>

    <div class="container relative">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3 md:gap-8">
            {#each cards as card, index (index)}
                <figure
                    class="group relative overflow-hidden rounded-md border-t-4 border-primary bg-ivory"
                >
                    {#if card.image}
                        <div class="aspect-[4/5] overflow-hidden">
                            <PublicMediaImage
                                media={card.image}
                                alt={card.alt}
                                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                            />
                        </div>
                    {:else}
                        <GradientPanel aspectRatio="portrait" variant="ivory" />
                    {/if}

                    <figcaption
                        class="px-2 py-5 text-center font-serif text-base italic text-foreground/85 lg:text-lg"
                    >
                        {card.copy}
                    </figcaption>
                </figure>
            {/each}
        </div>
    </div>
</section>
