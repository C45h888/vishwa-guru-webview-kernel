<script lang="ts">
    /**
     * SevaPill — the signature 3-step explainer for the campaigns
     * surface. Reads as a single connected object: step numbers,
     * connector thread, titles, and bodies are positioned in a grid
     * that stays aligned at every breakpoint.
     *
     * Doctrine (Pass M2 / @frontend-design):
     *   - The thread is the signature: a thin saffron line that runs
     *     horizontally through the three numbers on desktop, and
     *     vertically on mobile (one column). It is rendered with CSS
     *     pseudo-elements so a future motion pass can animate it with
     *     a single transform — no JS needed.
     *   - Numbers are oversized and pale-saffron (text-primary/30).
     *     They are the rhythm; the titles are the content.
     *   - Bg is the rice token (#FAF6EC) so the pill reads as an
     *     object on the page, not as three separate cards.
     *   - The pill is the only component in M2 that *intentionally*
     *     keeps a horizontal layout at md+; everywhere else the
     *     campaigns page stacks vertically.
     */
    interface Step {
        number: string;
        title: string;
        body: string;
    }

    interface Props {
        steps: readonly Step[];
        eyebrow?: string;
        headline?: string;
        intro?: string;
    }

    let {
        steps,
        eyebrow = 'Three steps',
        headline = 'How your offering becomes seva',
        intro = 'Giving here follows a simple rhythm — choose, dedicate, receive. Each step is handled with care, so the offering stays an offering.',
    }: Props = $props();
</script>

<section class="bg-background py-16 lg:py-24">
    <div class="container">
        <div class="mx-auto max-w-5xl space-y-10">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
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
                <p
                    class="text-base leading-relaxed text-muted-foreground"
                >
                    {intro}
                </p>
            </div>

            <ol
                class="relative grid grid-cols-1 gap-12 rounded-md border border-border/40 bg-rice p-8 sm:grid-cols-3 sm:gap-0 sm:p-10 lg:p-12"
                aria-label="The three steps of offering"
            >
                <!-- Horizontal thread (≥sm): runs across the top of the
                     three columns at the number baseline. -->
                <div
                    class="pointer-events-none absolute left-[16.6667%] right-[16.6667%] top-[4.5rem] hidden h-px bg-primary/30 sm:block"
                    aria-hidden="true"
                ></div>
                <!-- Vertical threads (mobile): one between each pair of
                     steps. The first and last are pinned to the column
                     centre. -->
                <div
                    class="pointer-events-none absolute left-1/2 top-24 bottom-24 block w-px -translate-x-1/2 bg-primary/30 sm:hidden"
                    aria-hidden="true"
                ></div>

                {#each steps as step, i (step.number)}
                    <li
                        class="relative flex flex-col items-center gap-4 text-center sm:px-6"
                    >
                        <span
                            class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-rice font-serif text-2xl font-semibold text-primary/50 ring-1 ring-primary/30"
                            aria-hidden="true"
                        >
                            {step.number}
                        </span>
                        <h3
                            class="font-serif text-lg font-semibold leading-tight lg:text-xl"
                        >
                            {step.title}
                        </h3>
                        <p
                            class="max-w-xs text-sm leading-relaxed text-muted-foreground"
                        >
                            {step.body}
                        </p>
                    </li>
                {/each}
            </ol>
        </div>
    </div>
</section>
