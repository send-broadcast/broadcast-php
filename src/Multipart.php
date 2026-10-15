<?php

declare(strict_types=1);

namespace Broadcast;

/**
 * A multipart/form-data request body: text fields plus file parts.
 *
 * Connection sends it as is, with its own Content-Type, instead of encoding
 * the payload as JSON. Built in memory so it goes through any
 * HttpClientInterface, which takes the body as a string. The boundary is
 * fixed at construction, so a retried request sends the same bytes.
 *
 * @internal
 */
final class Multipart
{
    /** @var array<string,string> */
    private array $fields = [];

    /** @var array<string,array{bytes:string,filename:string,contentType:string}> */
    private array $files = [];

    private string $boundary;

    public function __construct()
    {
        $this->boundary = 'broadcast-php-' . bin2hex(random_bytes(16));
    }

    public function withField(string $name, string|int|float|bool $value): self
    {
        $copy = clone $this;
        $copy->fields[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        return $copy;
    }

    public function withFile(string $name, string $bytes, string $filename, string $contentType): self
    {
        $copy = clone $this;
        $copy->files[$name] = ['bytes' => $bytes, 'filename' => $filename, 'contentType' => $contentType];

        return $copy;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]) || isset($this->files[$name]);
    }

    public function contentType(): string
    {
        return 'multipart/form-data; boundary=' . $this->boundary;
    }

    public function encode(): string
    {
        $body = '';

        foreach ($this->files as $name => $file) {
            $body .= '--' . $this->boundary . "\r\n"
                . sprintf(
                    "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n",
                    self::quote($name),
                    self::quote($file['filename'])
                )
                . 'Content-Type: ' . $file['contentType'] . "\r\n\r\n"
                . $file['bytes'] . "\r\n";
        }

        foreach ($this->fields as $name => $value) {
            $body .= '--' . $this->boundary . "\r\n"
                . sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", self::quote($name))
                . $value . "\r\n";
        }

        return $body . '--' . $this->boundary . "--\r\n";
    }

    /** Escape a header parameter value the way browsers do (WHATWG form encoding). */
    private static function quote(string $value): string
    {
        return str_replace(["\r", "\n", '"'], ['%0D', '%0A', '%22'], $value);
    }
}
