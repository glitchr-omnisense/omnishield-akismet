<?php

namespace Omniguard\Akismet\Tests;

use Omniguard\Akismet\AkismetGateway;
use Omniguard\Akismet\AkismetGatewayFactory;
use Omniguard\Exception\InvalidConfigException;
use Omniguard\Exception\InvalidKeyException;
use Omniguard\Exception\ProviderException;
use Omniguard\Exception\UnreachableException;
use Omniguard\Model\Label;
use Omniguard\Model\Submission;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers written from Akismet's documentation (comment-check, submit-spam,
 * submit-ham, verify-key, errors), as of 2026-10-07: Akismet answers in
 * plain text and headers. Not verified against Akismet itself - no key.
 */
final class AkismetGatewayTest extends TestCase
{
    /** @var list<array{string, string, array<string, string>}> */
    private array $calls = [];

    /** @param list<string|MockResponse> $answers fixtures' names: a body and its headers each */
    private function gateway(array $answers, array $options = []): AkismetGateway
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            parse_str((string) $options['body'], $body);
            $this->calls[] = [$method, $url, $body];
            $answer = array_shift($answers) ?? throw new \LogicException('No answer left.');

            if ($answer instanceof MockResponse) {
                return $answer;
            }
            $fixture = json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$answer.'.json'), true);

            return new MockResponse($fixture['body'], ['response_headers' => ['content-type' => 'text/plain; charset=utf-8'] + $fixture['headers']]);
        });
        $gateway = (new AkismetGatewayFactory($http))->create($options + ['api_key' => '123YourAPIKey', 'site' => 'https://example.org']);
        self::assertInstanceOf(AkismetGateway::class, $gateway);

        return $gateway;
    }

    private static function submission(string $content = 'Thank you, this helped.', string $author = 'Camille Érable'): Submission
    {
        return new Submission(
            content: $content,
            author: $author,
            email: 'camille@example.org',
            url: 'https://camille.example.org',
            ip: '192.0.2.10',
            userAgent: 'Mozilla/5.0 (X11; Linux x86_64)',
            referrer: 'https://example.org/blog',
            permalink: 'https://example.org/blog/a-post',
            language: 'fr',
            date: new \DateTimeImmutable('2026-10-07 10:00:00', new \DateTimeZone('Europe/Paris')),
        );
    }

    public function testWhatAkismetIsToldOfASubmission(): void
    {
        $classification = $this->gateway(['comment-check-ham'])->classify(self::submission());

        self::assertSame(Label::HAM, $classification->label);
        self::assertFalse($classification->isSpam());
        self::assertSame(['POST', 'https://rest.akismet.com/1.1/comment-check'], [$this->calls[0][0], $this->calls[0][1]]);
        self::assertSame([
            'api_key' => '123YourAPIKey',
            'blog' => 'https://example.org',
            'user_ip' => '192.0.2.10',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
            'referrer' => 'https://example.org/blog',
            'permalink' => 'https://example.org/blog/a-post',
            'comment_type' => 'comment',
            'comment_author' => 'Camille Érable',
            'comment_author_email' => 'camille@example.org',
            'comment_author_url' => 'https://camille.example.org',
            'comment_content' => 'Thank you, this helped.',
            'comment_date_gmt' => '2026-10-07T08:00:00Z',
            'blog_lang' => 'fr',
            'blog_charset' => 'UTF-8',
        ], $this->calls[0][2], 'the home page as blog, the author and the date as text, in UTC');
    }

    public function testSpamAndSpamToDiscard(): void
    {
        $spam = $this->gateway(['comment-check-spam'])->classify(self::submission('Buy now', 'akismet-guaranteed-spam'));
        self::assertSame(Label::SPAM, $spam->label);
        self::assertFalse($spam->isFlagrant());

        $flagrant = $this->gateway(['comment-check-discard'])->classify(self::submission('Buy now'));
        self::assertSame(Label::FLAGRANT, $flagrant->label);
        self::assertTrue($flagrant->isSpam());
    }

    public function testAnAlertAndARecheckAreKept(): void
    {
        $classification = $this->gateway(['comment-check-alert'])->classify(self::submission());

        self::assertSame(['alert 10011: Your site is using Akismet more than your plan allows.'], $classification->reasons);
        self::assertSame(['recheck_after' => 3600], $classification->data);
    }

    public function testTheSubmissionsOwnSiteAndLanguageWinAndATestIsSaidSo(): void
    {
        $this->gateway(['comment-check-ham'], ['test' => true, 'language' => 'de'])->classify(new Submission('Hallo', ip: '192.0.2.10', site: 'https://other.example.org', type: Submission::CONTACT_FORM));

        self::assertSame(['api_key' => '123YourAPIKey', 'blog' => 'https://other.example.org', 'user_ip' => '192.0.2.10', 'comment_type' => 'contact-form', 'comment_content' => 'Hallo', 'blog_lang' => 'de', 'blog_charset' => 'UTF-8', 'is_test' => '1'], $this->calls[0][2]);
    }

    public function testAnInvalidKeyIsThrownNeverTakenForHam(): void
    {
        try {
            $this->gateway(['comment-check-invalid-key'])->classify(self::submission());
            self::fail();
        } catch (InvalidKeyException $e) {
            self::assertSame('[akismet] comment-check answered "invalid": We were unable to parse your API key..', $e->getMessage());
        }

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Empty "blog" value');
        $this->gateway(['comment-check-empty-blog'])->classify(self::submission());
    }

    public function testNoAnswerIsUnreachable(): void
    {
        $this->expectException(UnreachableException::class);
        $this->gateway([new MockResponse('', ['error' => 'Could not resolve host: rest.akismet.com'])])->classify(self::submission());
    }

    public function testASubmissionWithoutTheVisitorsAddressOrTheSiteCannotBeSent(): void
    {
        foreach ([[new Submission('Hello', site: 'https://example.org'), [], 'IP address'], [new Submission('Hello', ip: '192.0.2.10'), ['site' => null], 'home page']] as [$submission, $options, $message]) {
            try {
                $this->gateway([], $options)->classify($submission);
                self::fail($message);
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
        self::assertSame([], $this->calls);
    }

    public function testASpamLetThroughAndAHamHeldAreReported(): void
    {
        $gateway = $this->gateway(['submit-thanks', 'submit-thanks']);

        $gateway->report(self::submission('Buy now'), true);
        $gateway->report(self::submission(), false);
        self::assertSame(['https://rest.akismet.com/1.1/submit-spam', 'https://rest.akismet.com/1.1/submit-ham'], array_column($this->calls, 1));
        self::assertSame('Buy now', $this->calls[0][2]['comment_content']);

        $this->expectException(InvalidKeyException::class);
        $this->gateway([new MockResponse('invalid')])->report(self::submission(), true);
    }

    public function testAKeyIsVerifiedForTheSite(): void
    {
        self::assertTrue($this->gateway(['verify-key-valid'])->verifyKey());
        self::assertSame(['api_key' => '123YourAPIKey', 'blog' => 'https://example.org'], $this->calls[0][2]);
        self::assertFalse($this->gateway(['verify-key-invalid'])->verifyKey('https://other.example.org'));
        self::assertSame(['classifier'], $this->gateway([])->capabilities()->questions());
        self::assertTrue($this->gateway([])->capabilities()->reports);
    }
}
