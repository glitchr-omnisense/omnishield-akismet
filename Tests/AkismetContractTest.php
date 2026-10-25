<?php

namespace Omnishield\Akismet\Tests;

use Omnishield\Akismet\AkismetGatewayFactory;
use Omnishield\GatewayFactoryInterface;
use Omnishield\Model\Submission;
use Omnishield\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The family's contract, on comment-check's answers as Akismet's documentation gives them (Fixtures/). */
final class AkismetContractTest extends TestCase
{
    use GatewayContractTests;

    protected function factory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new AkismetGatewayFactory($http);
    }

    protected function options(): array
    {
        return ['api_key' => '123YourAPIKey', 'site' => 'https://example.org'];
    }

    protected function recordedCases(): iterable
    {
        $ham = new Submission('Thank you, this helped.', 'Camille', 'camille@example.org', ip: '192.0.2.10');
        $spam = new Submission('Buy now', 'akismet-guaranteed-spam', ip: '192.0.2.10');

        yield 'ham' => [$ham, true, [self::recorded('comment-check-ham')]];
        yield 'spam' => [$spam, false, [self::recorded('comment-check-spam')]];
        yield 'flagrant spam' => [$spam, false, [self::recorded('comment-check-discard')]];
    }

    private static function recorded(string $name): MockResponse
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$name.'.json'), true);

        return new MockResponse($fixture['body'], ['response_headers' => ['content-type' => 'text/plain; charset=utf-8'] + $fixture['headers']]);
    }
}
