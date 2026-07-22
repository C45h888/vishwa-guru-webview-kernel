<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

enum PublicMediaState: string
{
    case DRAFT = 'draft';
    case READY = 'ready';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
