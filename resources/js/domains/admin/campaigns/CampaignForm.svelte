<script lang="ts">
    /**
     * Admin/Campaigns/CampaignForm.svelte — shared form body for the
     * "new campaign" + "edit campaign" pages.
     *
     * Doctrine:
     *   - No state machine in V1 (per user decision). State is a
     *     plain <select> with the allowed set [draft, active, completed].
     *   - The cover image uploader hits the JSON endpoint
     *     /admin/media/upload via XHR (so we can show upload progress).
     *     The returned file_asset_id is written into a hidden input so
     *     it submits with the form.
     *   - Errors are surfaced inline; we read from `$form.errors` (the
     *     Inertia form helper's reactive errors bag) so each field's
     *     validation message appears after a 422 redirect.
     *   - The form is intended to be wrapped in `{#key campaign.id}` by
     *     the parent Edit page so that navigating between edit pages
     *     re-mounts the form with fresh initial values. The form
     *     itself doesn't manage re-mount.
     *
     * Bug fixes (Pass A) baked in:
     *   1.2  Use `$form.errors` (Inertia's reactive bag, which is
     *        Record<string, string[]>) instead of a separate `errors`
     *        prop typed as Record<string, string>.
     *   1.9  Disable every field while `$form.processing` is true so
     *        the admin can't double-submit.
     *   1.10 Register a `beforeunload` listener when `$form.isDirty`
     *        so the admin doesn't lose unsaved changes on close.
     *   1.11 Show an <img> preview of the uploaded cover using the
     *        `/media/{id}` public route (the file_asset row is already
     *        on the public disk at upload time).
     *   1.12 Clear the underlying file input on upload failure so the
     *        admin can re-pick a different file without page reload.
     *   1.13 Use XHR instead of fetch so `upload.onprogress` can drive
     *        a real progress bar during the 5 MB upload.
     */
    import { useForm } from '@inertiajs/svelte';
    import { Button } from '$shared/ui/button';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Upload, X } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';
    import { slugify } from '$shared/lib/slug';

    export type CampaignFormValues = {
        slug: string;
        title: string;
        description: string;
        short_description: string;
        category: string;
        currency_code: string;
        target_amount_minor: string;     // string in form (parsed to int on submit)
        state: string;
        starts_at: string;                 // datetime-local string
        ends_at: string;                   // datetime-local string
        is_featured: boolean;
        display_order: string;             // string in form
        cover_image_file_id: string;
    };

    type Props = PageComponentProps<{
        method: 'post' | 'put';
        action: string;
        submit_label: string;
        initial: Partial<CampaignFormValues>;
        states: string[];
    }>;

    let { method, action, submit_label, initial, states }: Props = $props();

    // Form helper. Initialised once per component mount, so the parent
    // must wrap with {#key ...} to force re-mount when navigating between
    // edit pages.
    const form = useForm({
        slug: '',
        title: '',
        description: '',
        short_description: '',
        category: '',
        currency_code: 'INR',
        target_amount_minor: '',
        state: 'draft',
        starts_at: '',
        ends_at: '',
        is_featured: false,
        display_order: '0',
        cover_image_file_id: '',
        ...initial,
    });

    // Upload state — local to this form instance, not in the form helper.
    let uploadState = $state<'idle' | 'uploading' | 'uploaded' | 'error'>('idle');
    let uploadError = $state<string>('');
    let uploadedFileName = $state<string>('');
    let uploadProgress = $state<number>(0); // 0..100

    // 1.11: Preview URL. The file asset is on the public disk; the
    // /media/{id} route streams it. We compute the URL reactively whenever
    // the cover_image_file_id changes.
    const previewUrl = $derived(
        $form.cover_image_file_id ? `/media/${$form.cover_image_file_id}` : null,
    );

    // 1.10: Unsaved-changes guard. Register beforeunload only while the
    // form is dirty; the in-app Inertia navigation is handled separately
    // (we don't intercept router.visit here because Inertia already
    // supports an `onBefore` hook on the form helper if needed later).
    $effect(() => {
        if (!$form.isDirty) return;
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            // Modern browsers ignore the return value and show their own copy.
            e.returnValue = '';
            return '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    });

    /**
     * 1.13: XHR-based upload with progress, instead of fetch.
     * 1.12: Clears the file input on failure so the user can re-pick.
     * 1.11: Captures the file_asset_id and stores it in the form helper.
     */
    function uploadCover(event: Event) {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;

        uploadState = 'uploading';
        uploadError = '';
        uploadProgress = 0;

        const fd = new FormData();
        fd.append('file', file);
        fd.append('purpose', 'campaign_cover');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/media/upload', true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                uploadProgress = Math.round((e.loaded / e.total) * 100);
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    $form.cover_image_file_id = data.id;
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
                    // ignore parse error
                }
                uploadState = 'error';
                uploadError = msg;
                input.value = ''; // 1.12: clear so the user can re-pick
            }
        };

        xhr.onerror = () => {
            uploadState = 'error';
            uploadError = 'Network error during upload.';
            input.value = '';
        };

        xhr.send(fd);
    }

    function clearCover() {
        $form.cover_image_file_id = '';
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

    // 1.9: A single derived flag for "everything disabled while saving".
    const disabled = $derived($form.processing || uploadState === 'uploading');

    // Slug auto-generate (Pass B polish). Once the admin edits the slug
    // manually, the auto-fill stops so we do not trample their edits.
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
</script>

<form
    onsubmit={submit}
    class="space-y-6"
    enctype="multipart/form-data"
>
    <!-- Title + slug -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="title">Title</Label>
            <Input
                id="title"
                name="title"
                bind:value={$form.title}
                required
                maxlength={255}
                disabled={disabled}
            />
            {#if $form.errors.title}<p class="text-sm text-destructive">{$form.errors.title[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="slug">Slug</Label>
            <Input
                id="slug"
                name="slug"
                bind:value={$form.slug}
                required
                placeholder="lowercase-with-hyphens"
                disabled={disabled}
            />
            {#if $form.errors.slug}<p class="text-sm text-destructive">{$form.errors.slug[0]}</p>{/if}
        </div>
    </div>

    <!-- Descriptions -->
    <div class="space-y-2">
        <Label for="short_description">Short description</Label>
        <Input
            id="short_description"
            name="short_description"
            bind:value={$form.short_description}
            maxlength={500}
            disabled={disabled}
        />
        {#if $form.errors.short_description}<p class="text-sm text-destructive">{$form.errors.short_description[0]}</p>{/if}
    </div>
    <div class="space-y-2">
        <Label for="description">Full description</Label>
        <textarea
            id="description"
            name="description"
            bind:value={$form.description}
            rows="6"
            disabled={disabled}
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
        ></textarea>
        {#if $form.errors.description}<p class="text-sm text-destructive">{$form.errors.description[0]}</p>{/if}
    </div>

    <!-- Category + currency + state -->
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-2">
            <Label for="category">Category</Label>
            <Input
                id="category"
                name="category"
                bind:value={$form.category}
                required
                placeholder="general, education, festival…"
                disabled={disabled}
            />
            {#if $form.errors.category}<p class="text-sm text-destructive">{$form.errors.category[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="currency_code">Currency</Label>
            <select
                id="currency_code"
                name="currency_code"
                bind:value={$form.currency_code}
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                required
                disabled={disabled}
            >
                <option value="INR">INR</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
            </select>
            {#if $form.errors.currency_code}<p class="text-sm text-destructive">{$form.errors.currency_code[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="state">State</Label>
            <select
                id="state"
                name="state"
                bind:value={$form.state}
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                required
                disabled={disabled}
            >
                {#each states as s (s)}
                    <option value={s}>{s}</option>
                {/each}
            </select>
            {#if $form.errors.state}<p class="text-sm text-destructive">{$form.errors.state[0]}</p>{/if}
        </div>
    </div>

    <!-- Target amount -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="target_amount_minor">Target amount (minor units)</Label>
            <Input
                id="target_amount_minor"
                name="target_amount_minor"
                type="number"
                min="0"
                step="1"
                bind:value={$form.target_amount_minor}
                placeholder="500000 = ₹5,000"
                disabled={disabled}
            />
            {#if $form.errors.target_amount_minor}<p class="text-sm text-destructive">{$form.errors.target_amount_minor[0]}</p>{/if}
        </div>
        <div class="space-y-2">
            <Label for="display_order">Display order</Label>
            <Input
                id="display_order"
                name="display_order"
                type="number"
                min="0"
                step="1"
                bind:value={$form.display_order}
                disabled={disabled}
            />
            {#if $form.errors.display_order}<p class="text-sm text-destructive">{$form.errors.display_order[0]}</p>{/if}
        </div>
    </div>

    <!-- Date window -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
            <Label for="starts_at">Starts at</Label>
            <Input
                id="starts_at"
                name="starts_at"
                type="datetime-local"
                bind:value={$form.starts_at}
                disabled={disabled}
            />
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
    </div>

    <!-- Featured -->
    <div class="flex items-center gap-2">
        <input
            id="is_featured"
            name="is_featured"
            type="checkbox"
            bind:checked={$form.is_featured}
            disabled={disabled}
            class="h-4 w-4 rounded border-input disabled:opacity-60"
        />
        <Label for="is_featured">Featured (surfaces in the homepage tile set)</Label>
    </div>

    <!-- Cover image uploader -->
    <div class="space-y-2">
        <Label for="cover_image_file">Cover image</Label>
        {#if uploadState === 'uploaded' || ($form.cover_image_file_id && uploadState !== 'idle')}
            <div class="space-y-3 rounded-md border border-border bg-muted/40 p-3">
                {#if previewUrl}
                    <div class="overflow-hidden rounded-md border border-border/60">
                        <img
                            src={previewUrl}
                            alt={uploadedFileName || 'Cover preview'}
                            class="aspect-[16/9] w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                {/if}
                <div class="flex items-center gap-3">
                    <span class="text-sm">
                        Uploaded: {uploadedFileName || $form.cover_image_file_id}
                    </span>
                    <button
                        type="button"
                        onclick={clearCover}
                        disabled={disabled}
                        class="ml-auto inline-flex items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-xs font-medium hover:bg-muted disabled:opacity-60"
                    >
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
                <input
                    id="cover_image_file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onchange={uploadCover}
                    class="hidden"
                    disabled={disabled}
                />
            </label>
            {#if uploadState === 'uploading'}
                <div class="h-1 w-full overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full bg-primary transition-all"
                        style:width={`${uploadProgress}%`}
                    ></div>
                </div>
            {/if}
            {#if uploadError}
                <p class="text-sm text-destructive">{uploadError}</p>
            {/if}
        {/if}
        <input type="hidden" name="cover_image_file_id" bind:value={$form.cover_image_file_id} />
        {#if $form.errors.cover_image_file_id}<p class="text-sm text-destructive">{$form.errors.cover_image_file_id[0]}</p>{/if}
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2 border-t border-border pt-6">
        <Button
            type="submit"
            disabled={disabled}
        >
            {$form.processing ? 'Saving…' : submit_label}
        </Button>
    </div>
</form>
