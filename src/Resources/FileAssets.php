<?php

declare(strict_types=1);

namespace Broadcast\Resources;

use Broadcast\Multipart;

/**
 * The channel's file library (Broadcast 2.48.0 or later): images (PNG, JPEG,
 * GIF, under 10 MB) with public URLs that never expire. Use a file's `url` in
 * emails, or as the brand kit logo:
 *
 *   $file = $client->fileAssets->upload('logo.png');
 *   $client->channelDesign->update(['brand' => ['logo_url' => $file['url']]]);
 *
 * Reading needs the `file_assets_read` permission; uploading and deleting
 * need `file_assets_write`.
 */
final class FileAssets extends BaseResource
{
    public const CONTENT_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    /**
     * Files the channel can see, newest first: `['data' => [...], 'total' => n]`.
     *
     * Optional: filter (finds files by name, closest matches first), limit,
     * offset.
     *
     * @param array<string,mixed> $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/file_assets', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/file_assets/{$id}");
    }

    /**
     * Upload a file as multipart/form-data.
     *
     * `$file` is a path, or an open stream (then `filename` is required unless
     * the stream is a local file). The content type follows the file extension
     * unless `content_type` is given.
     *
     * Optional: filename, content_type, channel_restricted (true links the
     * file to this channel only; default every channel, as in the web app),
     * idempotency_key (makes a retry safe; see Transactionals::create()).
     *
     * @param string|resource $file
     * @param array<string,mixed> $options
     */
    public function upload(mixed $file, array $options = []): mixed
    {
        $filename = isset($options['filename']) ? (string) $options['filename'] : null;
        [$bytes, $name] = self::readFile($file, $filename);
        $contentType = isset($options['content_type'])
            ? (string) $options['content_type']
            : self::contentTypeFor($name);

        $body = (new Multipart())->withFile('file', $bytes, $name, $contentType);
        if (isset($options['channel_restricted'])) {
            $body = $body->withField('channel_restricted', (bool) $options['channel_restricted']);
        }

        $headers = self::idempotencyHeaders(self::keyOption($options));

        return $this->client->request('POST', '/api/v1/file_assets', $body, $headers);
    }

    /**
     * Upload base64-encoded bytes in a JSON body, for callers that already
     * hold base64 (or cannot send multipart).
     *
     * Required: filename, data. Optional: content_type, channel_restricted,
     * idempotency_key.
     *
     * @param array<string,mixed> $params
     */
    public function uploadBase64(array $params): mixed
    {
        $headers = self::idempotencyHeaders(self::keyOption($params));
        unset($params['idempotency_key']);

        return $this->httpPost('/api/v1/file_assets', self::compact($params), $headers);
    }

    /**
     * Refused (422, ValidationException) while the file is a channel's brand
     * kit logo: choose another logo first.
     */
    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/file_assets/{$id}");
    }

    /**
     * @param mixed $file a path or a stream resource
     * @return array{0:string,1:string} the bytes and the file name
     */
    private static function readFile(mixed $file, ?string $filename): array
    {
        if (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            if ($filename === null && $meta['wrapper_type'] === 'plainfile') {
                $filename = basename($meta['uri'] ?? '');
            }
            if ($filename === null || $filename === '') {
                throw new \InvalidArgumentException('filename is required when uploading from a stream');
            }

            $bytes = stream_get_contents($file);
            if ($bytes === false) {
                throw new \InvalidArgumentException('Could not read the stream');
            }

            return [$bytes, $filename];
        }

        if (!is_string($file)) {
            throw new \InvalidArgumentException('file must be a path or a stream resource');
        }

        $bytes = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        if ($bytes === false) {
            throw new \InvalidArgumentException(sprintf('Cannot read file: %s', $file));
        }

        return [$bytes, $filename ?? basename($file)];
    }

    private static function contentTypeFor(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';
    }

    /** @param array<string,mixed> $options */
    private static function keyOption(array $options): ?string
    {
        return isset($options['idempotency_key']) ? (string) $options['idempotency_key'] : null;
    }
}
