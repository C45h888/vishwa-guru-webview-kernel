<script lang="ts">
    /**
     * EventForm.svelte — shared form body for the "new event" + "edit
     * event" pages.
     *
     * Doctrine (mirrors CampaignForm.svelte):
     *   - No state machine in V1. State is a plain <select> with the
     *     allowed set [draft, published, completed].
     *   - The banner image uploader hits the JSON endpoint
     *     /admin/media/upload via XHR (for progress reporting).
     *   - Errors are surfaced inline from `$form.errors` (Inertia's
     *     reactive errors bag).
     *   - The form is re-mounted on navigation by the parent wrapping
     *     it in `{#key event.id}`.
     *
     * Events-specific:
     *   - starts_at is required (event horizon).
     *   - ends_at is optional but must be on/after starts_at; the
     *     `min={$form.starts_at}` constraint on the input surfaces
     *     this client-side before submission.
     *   - timezone field defaults to 'Asia/Kolkata'.
     *   - venue + venue_address are optional string fields.
     *
     * Bug fixes (Pass A) baked in:
     *   1.2  `$form.errors` (Record<string, string[]>) instead of a
     *        separate `errors` prop.
     *   1.8  `min={$form.starts_at}` on `ends_at` input.
     *   1.9  Disable all fields while `$form.processing`.
     *   1.10 `beforeunload` guard when `$form.isDirty`.
     *   1.11 <img> preview of the uploaded banner via `/media/{id}`.
     *   1.12 Clear the file input on upload failure.
     *   1.13 XHR with `upload.onprogress` for a real progress bar.
     */
    import { useForm } from '@inertiajs/svelte';
    import { Button } from '$shared/ui/button';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Upload, X } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';
    import { slugify } from '$shared/lib/slug';

    export type EventFormValues = {
        slug: string;
        title: string;
        description: string;
        short_description: string;
        banner_file_id: string;
        starts_at: string;       // datetime-local
        ends_at: string;         // datetime-local
        timezone: string;
        venue: string;
        venue_address: string;
        state: string;
        is_featured: boolean;
        display_order: string;
    };

    type Props = PageComponentProps<{
        method: 'post' | 'put';
        action: string;
        submit_label: string;
        initial: Partial<EventFormValues>;
        states: string[];
    }>;

    let { method, action, submit_label, initial, states }: Props = $props();

    const form = useForm({
        slug: '',
        title: '',
        description: '',
        short_description: '',
        banner_file_id: '',
        starts_at: '',
        ends_at: '',
        timezone: 'Asia/Kolkata',
        venue: '',
        venue_address: '',
        state: 'draft',
        is_featured: false,
        display_order: '0',
        ...initial,
    });

    let uploadState = $state<'idle' | 'uploading' | 'uploaded' | 'error'>('idle');
    let uploadError = $state<string>('');
    let uploadedFileName = $state<string>('');
    let uploadProgress = $state<number>(0);

    const previewUrl = $derived(
        $form.banner_file_id ? `/media/${$form.banner_file_id}` : null,
    );

    $effect(() => {
        if (!$form.isDirty) return;
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
            return '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    });

    function uploadBanner(event: Event) {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;

        uploadState = 'uploading';
        uploadError = '';
        uploadProgress = 0;

        const fd = new FormData();
        fd.append('file', file);
        fd.append('purpose', 'event_cover');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/media/upload', true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                uploadProgress = Math.round((e.loaded / e.total) * 100);
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    $form.banner_file_id = data.id;
                    uploadedFileName = file.name;
                    uploadState = 'uploaded';
                    uploadProgress = 100;
                } catch {
                    uploadState = 'error';
                    uploadError = 'Upload succeeded but the response was malformed.';
                    input.value = '';
                }
            } else {
                let msg = `Upload failed (HTTP ${xhr.status})`;
                try {
                    const data = JSON.parse(xhr.responseText);
                    msg = data?.errors?.file?.[0] ?? msg;
                } catch {
                    // ignore
                }
                uploadState = 'error';
                uploadError = msg;
                input.value = '';
            }
        };

        xhr.onerror = () => {
            uploadState = 'error';
            uploadError = 'Network error during upload.';
            input.value = '';
        };

        xhr.send(fd);
    }

    function clearBanner() {
        $form.banner_file_id = '';
        uploadedFileName = '';
        uploadProgress = 0;
        uploadState = 'idle';
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        if (method === 'post') {
            $form.post(action);
        } else {
            $form.put(action);
        }
    }

    // Slug auto-generate (Pass B polish). Once the admin edits
    // the slug manually, the auto-fill stops so we do not trample
    // their edits.
    let slugWasManuallyEdited = $state($form.slug && $form.slug.length > 0);
    function onTitleChange(event: Event) {
        const target = event.target as HTMLInputElement;
        if (!slugWasManuallyEdited) {
            $form.slug = slugify(target.value);
        }
    }
    function onSlugInput(event: Event) {
        const target = event.target as HTMLInputElement;
        slugWasManuallyEdited = target.value.length > 0;
    }

    const disabled = $derived($form.processing || uploadState === 'uploading');
</script>

<form onsubmit={submit} class="space-y-6" enctype="multipart/form-data">
    <!-- Title + slug -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="title">Title</Label>
            <Input id="title" name="title" bind:value={$form.title} required maxlength={255} disabled={disabled} />
            {#if $form.errors.title}<p class="text-sm text-destructive">{$form.errors.title[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="slug">Slug</Label>
            <Input id="slug" name="slug" bind:value={$form.slug} required placeholder="lowercase-with-hyphens" disabled={disabled} />
            {#if $form.errors.slug}<p class="text-sm text-destructive">{$form.errors.slug[0]}</p>{/if}
        </div>
    </div>

    <!-- Descriptions -->
    <div class="space-y-2">
        <Label for="short_description">Short description</Label>
        <Input id="short_description" name="short_description" bind:value={$form.short_description} maxlength={500} disabled={disabled} />
        {#if $form.errors.short_description}<p class="text-sm text-destructive">{$form.errors.short_description[0]}</p>{/if}
    </div>
    <div class="space-y-2">
        <Label for="description">Full description</Label>
        <textarea id="description" name="description" bind:value={$form.description} rows="6"
            disabled={disabled}
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"></textarea>
        {#if $form.errors.description}<p class="text-sm text-destructive">{$form.errors.description[0]}</p>{/if}
    </div>

    <!-- Date window + state -->
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-2">
            <Label for="starts_at">Starts at</Label>
            <Input id="starts_at" name="starts_at" type="datetime-local" bind:value={$form.starts_at} required disabled={disabled} />
            {#if $form.errors.starts_at}<p class="text-sm text-destructive">{$form.errors.starts_at[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="ends_at">Ends at</Label>
            <Input
                id="ends_at"
                name="ends_at"
                type="datetime-local"
                min={$form.starts_at || undefined}
                bind:value={$form.ends_at}
                disabled={disabled}
            />
            {#if $form.errors.ends_at}<p class="text-sm text-destructive">{$form.errors.ends_at[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="state">State</Label>
            <select id="state" name="state" bind:value={$form.state}
                disabled={disabled}
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60" required>
                {#each states as s (s)}<option value={s}>{s}</option>{/each}
            </select>
            {#if $form.errors.state}<p class="text-sm text-destructive">{$form.errors.state[0]}</p>{/if}
        </div>
    </div>

    <!-- Timezone + venue -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="timezone">Timezone</Label>
            <Input id="timezone" name="timezone" bind:value={$form.timezone} required maxlength={60} disabled={disabled} />
            {#if $form.errors.timezone}<p class="text-sm text-destructive">{$form.errors.timezone[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="venue">Venue</Label>
            <Input id="venue" name="venue" bind:value={$form.venue} maxlength={255} disabled={disabled} />
            {#if $form.errors.venue}<p class="text-sm text-destructive">{$form.errors.venue[0]}</p>{/if}
        </div>
    </div>

    <div class="space-y-2">
        <Label for="venue_address">Venue address</Label>
        <Input id="venue_address" name="venue_address" bind:value={$form.venue_address} maxlength={1000} disabled={disabled} />
        {#if $form.errors.venue_address}<p class="text-sm text-destructive">{$form.errors.venue_address[0]}</p>{/if}
    </div>

    <!-- Featured + display order -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="display_order">Display order</Label>
            <Input id="display_order" name="display_order" type="number" min="0" step="1" bind:value={$form.display_order} disabled={disabled} />
            {#if $form.errors.display_order}<p class="text-sm text-destructive">{$form.errors.display_order[0]}</p>{/if}
        </div>
        <div class="flex items-end gap-2 pb-2">
            <input id="is_featured" name="is_featured" type="checkbox" bind:checked={$form.is_featured}
                disabled={disabled}
                class="h-4 w-4 rounded border-input disabled:opacity-60" />
            <Label for="is_featured">Featured</Label>
        </div>
    </div>

    <!-- Banner image uploader -->
    <div class="space-y-2">
        <Label for="banner_file">Banner image</Label>
        {#if uploadState === 'uploaded' || ($form.banner_file_id && uploadState !== 'idle')}
            <div class="space-y-3 rounded-md border border-border bg-muted/40 p-3">
                {#if previewUrl}
                    <div class="overflow-hidden rounded-md border border-border/60">
                        <img
                            src={previewUrl}
                            alt={uploadedFileName || 'Banner preview'}
                            class="aspect-[16/9] w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                {/if}
                <div class="flex items-center gap-3">
                    <span class="text-sm">
                        Uploaded: {uploadedFileName || $form.banner_file_id}
                    </span>
                    <button type="button" onclick={clearBanner}
                        disabled={disabled}
                        class="ml-auto inline-flex items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-xs font-medium hover:bg-muted disabled:opacity-60">
                        <X class="h-3 w-3" /> Replace
                    </button>
                </div>
            </div>
        {:else}
            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed border-border bg-muted/30 px-4 py-6 text-sm text-muted-foreground hover:bg-muted/50">
                <Upload class="h-4 w-4" />
                <span>
                    {uploadState === 'uploading'
                        ? `Uploading… ${uploadProgress}%`
                        : 'Click to upload (JPEG, PNG, or WebP, max 5 MB)'}
                </span>
                <input id="banner_file" type="file" accept="image/jpeg,image/png,image/webp"
                    onchange={uploadBanner} class="hidden" disabled={disabled} />
            </label>
            {#if uploadState === 'uploading'}
                <div class="h-1 w-full overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full bg-primary transition-all"
                        style:width={`${uploadProgress}%`}
                    ></div>
                </div>
            {/if}
            {#if uploadError}<p class="text-sm text-destructive">{uploadError}</p>{/if}
        {/if}
        <input type="hidden" name="banner_file_id" bind:value={$form.banner_file_id} />
        {#if $form.errors.banner_file_id}<p class="text-sm text-destructive">{$form.errors.banner_file_id[0]}</p>{/if}
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2 border-t border-border pt-6">
        <Button type="submit" disabled={disabled}>
            {$form.processing ? 'Saving…' : submit_label}
        </Button>
    </div>
</form>
