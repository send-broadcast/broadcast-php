<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * The channel's brand kit from Settings → Brand Kit.
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
     * templates read permission (or, from Broadcast 2.46.0, channel settings
     * read). Block emails built with the drag-and-drop editor inherit these
     * values, so read this to design on-brand.
     */
    public function get(): mixed
    {
        return $this->httpGet('/api/v1/channel/design');
    }

    /**
     * Change the brand kit (Broadcast 2.46.0 or later). Needs the channel
     * settings write permission.
     *
     * Partial: send only what changes, in the shape get() returns. An empty
     * string or null puts a value back to its default; `social_links` replaces
     * the list; `font_stack` and `width` are read-only. Set the logo with the
     * `logo_url` of an image in the channel's file library (the server never
     * fetches a URL). Returns the whole kit after the change; read
     * `->warnings()` for anything the server ignored.
     *
     * @param array<string,mixed> $design
     */
    public function update(array $design): mixed
    {
        return $this->httpPatch('/api/v1/channel/design', ['channel_design' => $design]);
    }
}
