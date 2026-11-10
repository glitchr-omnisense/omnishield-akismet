<?php

namespace Omnishield\Akismet\Tests;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnishield\Akismet\AkismetGateway;
use Omnishield\Akismet\AkismetGatewayFactory;
use Omnishield\Exception\UnreachableException;
use Omnishield\Model\Submission;
use Omnishield\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Its usage policy: declared as data, read by the gateway, enforced through the registry. */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresItsPolicy(): void
    {
        $policy = UsagePolicy::fromFile(AkismetGateway::POLICY);
        $gateway = (new AkismetGatewayFactory(new MockHttpClient()))->create(['api_key' => '123YourAPIKey', 'site' => 'https://example.org']);

        self::assertSame('akismet', $policy->provider, 'named after the factory');
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertSame('contract', $policy->rights->commercial->value);
        self::assertNull($policy->rateLimits, 'the limits depend on the site\'s plan: unknown');
        self::assertEquals($policy, $gateway->capabilities()->policy());
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
    }

    public function testA429AndItsRetryAfterHoldTheNextCallThroughTheRegistry(): void
    {
        $requests = 0;
        $http = new MockHttpClient(static function () use (&$requests) {
            ++$requests;

            return new MockResponse('Too Many Requests', ['http_code' => 429, 'response_headers' => ['retry-after' => '60']]);
        });
        $registry = new Registry([new AkismetGatewayFactory($http)], ['site' => ['factory' => 'akismet', 'options' => ['api_key' => '123YourAPIKey', 'site' => 'https://example.org']]]);
        foreach ([60, null] as $i => $wait) {
            try {
                $registry->classifier('site')->classify(new Submission('Thank you, this helped.', ip: '192.0.2.10'));
                self::fail('refused');
            } catch (UnreachableException $e) {
                null === $wait ? self::assertGreaterThan(0, $e->retryAfter()) : self::assertSame($wait, $e->retryAfter());
            }
        }
        self::assertSame(1, $requests, 'the second call was held, nothing sent');
    }
}
