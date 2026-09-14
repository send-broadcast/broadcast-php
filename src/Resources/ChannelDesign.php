<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * The channel's brand kit from Settings → Design. Read-only.
 */
final class ChannelDesign extends BaseResource
{
    /**
     * The brand kit, fully resolved (defaults filled in): `colors`,
     * `typography` (font key and email-safe `font_stack`), `layout` (`width`,
     * `radius`), and `brand` (`logo_url` as a public URL or null, `logo_width`,
     * `website_url`, `social_links`, `social_icon_style`).
     *
     * Always the token's own channel; there is no channel parameter. Needs the
     * templates read permission. Block emails built with the drag-and-drop
     * editor inherit these values, so read this to design on-brand.
     */
    public function get(): mixed
    {
        return $this->httpGet('/api/v1/channel/design');
    }
}
