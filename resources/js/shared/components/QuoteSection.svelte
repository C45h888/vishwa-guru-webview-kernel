<script lang="ts">
    interface Props {
        quote: string;
        eyebrow?: string;
        attribution?: string | null;
    }

    let { quote, eyebrow, attribution = null }: Props = $props();
</script>

<section class="bg-ivory py-20 lg:py-28">
    <div class="container">
        <div class="mx-auto max-w-3xl text-center">
            {#if eyebrow}
                <p
                    class="mb-6 text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    {eyebrow}
                </p>
            {/if}

            <div
                class="mx-auto mb-8 h-px w-10 bg-primary"
                aria-hidden="true"
            ></div>

            <blockquote
                class="font-serif text-2xl leading-relaxed text-foreground/90 lg:text-3xl"
            >
                "{quote}"
            </blockquote>

            {#if attribution}
                <p class="mt-6 text-sm font-medium text-foreground/70">
                    — {attribution}
                </p>
            {/if}

            <div
                class="mx-auto mt-8 h-px w-10 bg-primary"
                aria-hidden="true"
            ></div>
        </div>
    </div>
</section>
