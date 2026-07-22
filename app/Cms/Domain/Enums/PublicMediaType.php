<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

enum PublicMediaType: string
{
    case HERO_DESKTOP = 'hero_desktop';
    case HERO_MOBILE = 'hero_mobile';
    case CAMPAIGN_COVER = 'campaign_cover';
    case EVENT_BANNER = 'event_banner';
    case GALLERY_COVER = 'gallery_cover';
    case GALLERY_IMAGE = 'gallery_image';
    case CONTENT_BLOCK_IMAGE = 'content_block_image';
    case SOCIAL_SHARE_IMAGE = 'social_share_image';
    case TEMPLE_LOGO = 'temple_logo';
    case TRUST_SEAL = 'trust_seal';
}
