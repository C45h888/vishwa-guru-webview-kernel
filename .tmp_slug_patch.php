<?php
// Add slug auto-generate to both forms via in-place string replacement.
$files = [
    '/app/app/Events/Contracts/EventForm.svelte' => false, // wrong path
    '/app/resources/js/domains/admin/campaigns/CampaignForm.svelte' => true,
    '/app/resources/js/domains/admin/events/EventForm.svelte' => true,
];

foreach ($files as $path => $exists) {
    if (!$exists) continue;
    $content = file_get_contents($path);

    // 1. Add import
    $oldImport = "    import { Upload, X } from 'lucide-svelte';\n    import type { PageComponentProps } from \$shared/lib/inertia';";
    // Use double-quoted for the placeholder
    $oldImport = str_replace("\$shared/lib/inertia'", '$shared/lib/inertia\\';"', $oldImport);
    $newImport = "    import { Upload, X } from 'lucide-svelte';\n    import { slugify } from '$shared/lib/slug';\n    import type { PageComponentProps } from '$shared/lib/inertia';";
    $newImport = str_replace("$shared/lib/inertia'", '$shared/lib/inertia\\';"', $newImport);

    if (strpos($content, $oldImport) !== false) {
        $content = str_replace($oldImport, $newImport, $content);
        echo "$path: import added\n";
    } else {
        echo "$path: import NOT FOUND\n";
        continue;
    }

    // 2. Add slugWasManuallyEdited state + auto-fill logic after useForm call
    $oldUseFormEnd = "    // 1.9: A single derived flag for \"everything disabled while saving\".\n    const disabled = \$derived(\$form.processing || uploadState === 'uploading');";
    $newUseFormEnd = "    // 1.9: A single derived flag for \"everything disabled while saving\".\n    const disabled = \$derived(\$form.processing || uploadState === 'uploading');\n\n    // Slug auto-generate (Pass B polish). Track whether the slug has\n    // been manually edited by the admin; once edited, stop auto-filling.\n    let slugWasManuallyEdited = \$state(\$form.slug && \$form.slug.length > 0);\n    function onTitleChange(event: Event) {\n        const target = event.target as HTMLInputElement;\n        if (!slugWasManuallyEdited) {
            \$form.slug = slugify(target.value);
        }
    }\n    function onSlugInput(event: Event) {
        const target = event.target as HTMLInputElement;
        slugWasManuallyEdited = target.value.length > 0;
    }";

    if (strpos($content, $oldUseFormEnd) !== false) {
        $content = str_replace($oldUseFormEnd, $newUseFormEnd, $content);
        echo "$path: auto-fill logic added\n";
    } else {
        echo "$path: useForm end NOT FOUND\n";
    }

    file_put_contents($path, $content);
}
echo "DONE\n";
