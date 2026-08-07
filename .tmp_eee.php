<?php
declare(strict_types=1);
$path = '/app/app/Events/Infrastructure/Repositories/EloquentEventRepository.php';
$content = file_get_contents($path);

// 1. CREATE: auto-populate published_at when state='published' on create
$oldCreate = "        \$row = array_merge(\$defaults, \$data);
        if (is_array(\$row['metadata'] ?? null)) {
            \$row['metadata'] = json_encode(\$row['metadata'], JSON_THROW_ON_ERROR);
        }";
$newCreate = "        \$row = array_merge(\$defaults, \$data);
        if (is_array(\$row['metadata'] ?? null)) {
            \$row['metadata'] = json_encode(\$row['metadata'], JSON_THROW_ON_ERROR);
        }
        // Doctrine: when an event is created with state='published', stamp
        // published_at with now. Symmetric to end() which stamps
        // completed_at when state=completed. Idempotent: never overwrites
        // an explicit published_at.
        if ((\$row['state'] ?? null) === 'published' && empty(\$row['published_at'])) {
            \$row['published_at'] = \$now;
        }";
if (strpos($content, $oldCreate) !== false) {
    $content = str_replace($oldCreate, $newCreate, $content);
    echo 'CREATE BLOCK: patched'.PHP_EOL;
} else {
    echo 'CREATE BLOCK: NOT FOUND'.PHP_EOL;
}

// 2. UPDATE: auto-populate published_at when state transitions to published
$oldUpdate = "        \$diff['updated_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        if (isset(\$diff['metadata']) && is_array(\$diff['metadata'])) {
            \$diff['metadata'] = json_encode(\$diff['metadata'], JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('is_featured', \$diff)) {
            \$diff['is_featured'] = self::toBool(\$diff['is_featured']) ? 1 : 0;
        }";
$newUpdate = "        \$diff['updated_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        if (isset(\$diff['metadata']) && is_array(\$diff['metadata'])) {
            \$diff['metadata'] = json_encode(\$diff['metadata'], JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('is_featured', \$diff)) {
            \$diff['is_featured'] = self::toBool(\$diff['is_featured']) ? 1 : 0;
        }
        // Doctrine: when state transitions to 'published' and published_at
        // is not already set, stamp published_at with now. Symmetric to
        // end() which stamps completed_at on state=completed. Without
        // this, an admin editing an event and flipping state to 'published'
        // leaves published_at=NULL (Bug: confirmed via state audit
        // 2026-08-06). Idempotent: never overwrites an existing
        // published_at.
        if ((\$diff['state'] ?? null) === 'published' && empty(\$diff['published_at'])) {
            \$existing = \$this->findByIdIncludingDrafts(\$id);
            if (\$existing !== null && \$existing->publishedAt === null) {
                \$diff['published_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            } else {
                unset(\$diff['published_at']);
            }
        }";
if (strpos($content, $oldUpdate) !== false) {
    $content = str_replace($oldUpdate, $newUpdate, $content);
    echo 'UPDATE BLOCK: patched'.PHP_EOL;
} else {
    echo 'UPDATE BLOCK: NOT FOUND'.PHP_EOL;
}

file_put_contents($path, $content);
echo 'DONE'.PHP_EOL;
