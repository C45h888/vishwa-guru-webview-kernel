<script lang="ts">
    import {
        Receipt,
        Shield,
        Building2,
        BadgeCheck,
    } from 'lucide-svelte';
    import { Badge } from '$shared/ui/badge';

    const badges = [
        { icon: Receipt, label: '80G Tax Benefit' },
        { icon: Shield, label: 'Secure Payments' },
        { icon: Building2, label: 'Registered Trust' },
        { icon: BadgeCheck, label: 'Official Site' },
    ];
</script>

<div class="flex flex-wrap items-center justify-center gap-2">
    {#each badges as { icon: Icon, label } (label)}
        <Badge variant="secondary" class="gap-1.5 py-1.5 px-3 text-xs">
            <Icon class="h-3.5 w-3.5" aria-hidden="true" />
            <span>{label}</span>
        </Badge>
    {/each}
</div>