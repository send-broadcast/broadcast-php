<?php

declare(strict_types=1);

namespace Broadcast;

use Broadcast\Exception\TimeoutException;

/** Default transport. Follows no redirects — Connection handles them explicitly. */
final class CurlHttpClient implements HttpClientInterface
{
    /**
     * @param non-empty-string $method
     * @param non-empty-string $url
     * @param array<string,string> $headers
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout, int $openTimeout): array
    {
        $handle = curl_init();

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            // Never let cURL follow a redirect: every request carries
            // Authorization: Bearer, and cURL would take it to the new host.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $openTimeout,
        ]);

        if ($headerLines !== []) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        }
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        if ($raw === false) {
            throw new TimeoutException(sprintf('Request timeout: %s (curl %d)', $error, $errno));
        }

        $rawString = (string) $raw;

        return [
            'status' => $status,
            'headers' => self::parseHeaders(substr($rawString, 0, $headerSize)),
            'body' => substr($rawString, $headerSize),
        ];
    }

    /** @return array<string,string> */
    private static function parseHeaders(string $block): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $position)));
            $headers[$name] = trim(substr($line, $position + 1));
        }

        return $headers;
    }
}
