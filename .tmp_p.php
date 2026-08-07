<?php
$old = '    // 1.9: A single derived flag for "everything disabled while saving".
    const disabled = $derived($form.processing || uploadState === \'uploading\');';

$new = '    // 1.9: A single derived flag for "everything disabled while saving".
    const disabled = $derived($form.processing || uploadState === \'uploading\');

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
    }';

$paths = [
    '/app/resources/js/domains/admin/campaigns/CampaignForm.svelte',
    '/app/resources/js/domains/admin/events/EventForm.svelte',
];

foreach ($paths as $path) {
    $c = file_get_contents($path);
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($path, $c);
        echo "$path: block patched\n";
    } else {
        echo "$path: block NOT FOUND\n";
    }
}
