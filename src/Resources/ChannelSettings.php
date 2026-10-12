<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * The channel's settings (Broadcast 2.46.0 or later), grouped like the
 * Settings pages: `general`, `sender`, `unsubscribe_page` (with the privacy
 * policy and legal notice links), `confirmation_page` and `opt_in_forms`
 * (`match_brand_kit`: whether new opt-in forms start from the brand kit). The
 * brand kit switches are set here, not with ChannelDesign::update().
 *
 * Always the token's own channel; there is no channel parameter. Reading
 * needs the channel settings read permission, changing needs channel
 * settings write.
 */
final class ChannelSettings extends BaseResource
{
    /** Every channel setting, with defaults resolved. */
    public function get(): mixed
    {
        return $this->httpGet('/api/v1/channel/settings');
    }

    /**
     * Partial update: send only the groups and keys that change. Nested text
     * and colours merge, an empty string or null removes a value, and
     * `languages` replaces the list. Returns every setting after the change;
     * read `->warnings()` for anything the server ignored or changed.
     *
     * @param array<string,mixed> $settings
     */
    public function update(array $settings): mixed
    {
        return $this->httpPatch('/api/v1/channel/settings', ['channel_settings' => $settings]);
    }
}
