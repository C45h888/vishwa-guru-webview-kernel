<script lang="ts">
    const links = [
        { href: '/', label: 'Home' },
        { href: '/campaigns', label: 'Campaigns' },
        { href: '/gallery', label: 'Gallery' },
        { href: '/events', label: 'Events' },
        { href: '/about', label: 'About' },
        { href: '/contact', label: 'Contact' },
    ];
</script>

<ul class="flex items-center gap-6 text-sm font-medium">
    {#each links as link (link.href)}
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