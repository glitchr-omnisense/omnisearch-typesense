<?php

namespace Omnisearch\Typesense\Tests;

use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Its usage policy, as data: the engine is the site's (self-hosted, or a
 * Typesense Cloud cluster whose terms publish no limit), so nothing is
 * limited; the key is the server's.
 */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresItsPolicy(): void
    {
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('typesense', $policy->provider, 'named after the driver');
        self::assertSame([], $policy->rateLimits, 'self-hosted: none; Typesense Cloud publishes none');
        self::assertTrue($policy->requirements->key);
        self::assertSame(Permission::Allowed, $policy->rights->storage, 'the documents are the site\'s own');
        self::assertSame('GPL-3.0', $policy->rights->licence, 'the server\'s, when the site runs it');
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
    }

    public function testASiteMayAddALimitButNotLoosenTheRights(): void
    {
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json')->tighten(['rateLimits' => [['max' => 100, 'window' => 'second']]]);

        self::assertCount(1, (array) $policy->rateLimits);
        self::assertTrue($policy->requirements->key, 'the key stays required');
    }
}
