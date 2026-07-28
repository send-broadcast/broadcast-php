<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\Client;
use Broadcast\Exception\ApiException;
use Broadcast\Exception\AuthenticationException;
use Broadcast\Exception\AuthorizationException;
use Broadcast\Exception\BroadcastException;
use Broadcast\Exception\ConflictException;
use Broadcast\Exception\NotFoundException;
use Broadcast\Exception\RateLimitException;
use Broadcast\Exception\TimeoutException;
use Broadcast\Exception\ValidationException;
use Broadcast\Resources\Migration;
use Broadcast\Version;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class PackageTest extends BaseTestCase
{
    public function testErrorHierarchyNestsAsTheRubyGemDoes(): void
    {
        self::assertTrue(is_subclass_of(AuthenticationException::class, ApiException::class));
        self::assertTrue(is_subclass_of(AuthorizationException::class, ApiException::class));
        self::assertTrue(is_subclass_of(NotFoundException::class, ApiException::class));
        self::assertTrue(is_subclass_of(ConflictException::class, ApiException::class));
        self::assertTrue(is_subclass_of(RateLimitException::class, ApiException::class));

        // ValidationException and TimeoutException are siblings of
        // ApiException, not children — catching ApiException must not swallow
        // a validation failure.
        self::assertFalse(is_subclass_of(ValidationException::class, ApiException::class));
        self::assertFalse(is_subclass_of(TimeoutException::class, ApiException::class));
        self::assertTrue(is_subclass_of(ValidationException::class, BroadcastException::class));
    }

    public function testEveryResourceIsReachable(): void
    {
        $client = new Client(['apiToken' => 't', 'host' => 'https://mail.example.com']);

        foreach ([
            'subscribers', 'sequences', 'broadcasts', 'segments', 'templates',
            'webhookEndpoints', 'transactionals', 'optInForms', 'emailServers',
            'autopilots', 'discovery', 'migration',
        ] as $name) {
            self::assertTrue(isset($client->{$name}), "missing resource: {$name}");
        }
    }

    public function testMigrationDeclaresEighteenCollections(): void
    {
        self::assertCount(18, Migration::COLLECTIONS);
    }

    /**
     * The 18 migration endpoints are generated through __call, so no path
     * literal exists for the coverage scanner to find. They are declared in
     * .api-coverage.yml instead — which means that file is a hand-maintained
     * promise, and a promise nothing checks is one that drifts.
     *
     * Adding a collection without updating the manifest would silently drop
     * this SDK to 103/104 with no failing test anywhere to explain why.
     */
    public function testCoverageManifestMatchesTheGeneratedCollections(): void
    {
        $manifest = (string) file_get_contents(__DIR__ . '/../.api-coverage.yml');
        preg_match_all('#- GET /api/migration/v1/(\w+)#', $manifest, $matches);

        $declared = $matches[1];
        $implemented = array_values(Migration::COLLECTIONS);
        sort($declared);
        sort($implemented);

        self::assertSame(
            $implemented,
            $declared,
            '.api-coverage.yml is out of sync with Migration::COLLECTIONS'
        );
    }

    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::VERSION);
    }

    public function testComposerJsonIsValidAndRequiresPhp81(): void
    {
        $composer = json_decode(
            (string) file_get_contents(__DIR__ . '/../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('broadcast/broadcast-php', $composer['name']);
        self::assertStringContainsString('8.1', $composer['require']['php']);
        self::assertSame(['Broadcast\\' => 'src/'], $composer['autoload']['psr-4']);
    }

    public function testEverySourceFileDeclaresStrictTypes(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            self::assertStringContainsString(
                'declare(strict_types=1)',
                $contents,
                $file->getFilename() . ' is missing declare(strict_types=1)'
            );
        }
    }
}
