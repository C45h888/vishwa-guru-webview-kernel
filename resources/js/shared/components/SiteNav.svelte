<script lang="ts">
    // Routes are reserved for Sub-project 3; hrefs are placeholders.
    const links = [
        { href: '/', label: 'Home' },
        { href: '/campaigns', label: 'Campaigns' },
        { href: '/gallery', label: 'Gallery' },
        { href: '/events', label: 'Events' },
    ];
</script>

<ul class="flex items-center gap-6 text-sm">
    {#each links as link (link.href)}
        <li>
            <a
                href={link.href}
                class="text-muted-foreground transition-colors hover:text-foreground"
            >
                {link.label}
            </a>
        </li>
    {/each}
</ul>
