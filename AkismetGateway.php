<?php

namespace Omnishield\Akismet;

use Omnishield\ClassifierInterface;
use Omnishield\Exception\InvalidConfigException;
use Omnishield\Exception\InvalidKeyException;
use Omnishield\Exception\ProviderException;
use Omnishield\Http\Answer;
use Omnishield\Model\Capabilities;
use Omnishield\Model\Classification;
use Omnishield\Model\Label;
use Omnishield\Model\Submission;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Akismet's REST API (https://akismet.com/developers/detailed-docs/): the
 * key as the api_key parameter on https://rest.akismet.com/1.1/ - the
 * <key>.rest.akismet.com form is the older one.
 *
 *   - comment-check: "false" is ham, "true" spam - flagrant when the
 *     X-akismet-pro-tip header says "discard" -, "invalid" an error;
 *   - submit-spam, submit-ham: "Thanks for making the web a better place.";
 *   - verify-key: "valid" or "invalid".
 *
 * An error is never taken for ham: a key refused is an InvalidKeyException,
 * a request Akismet could not read a ProviderException, no answer an
 * UnreachableException. Whether to let the content through then is the
 * application's call.
 */
final class AkismetGateway implements ClassifierInterface
{
    public const BASE_URI = 'https://rest.akismet.com/1.1/';
    private const THANKS = 'Thanks for making the web a better place.';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $site = null,
        private readonly ?string $language = null,
        private readonly bool $test = false,
        private readonly string $baseUri = self::BASE_URI,
    ) {
    }

    public function getName(): string
    {
        return 'akismet';
    }

    public function getTitle(): string
    {
        return 'Akismet';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(classifier: true, thirdParty: true, cookies: false, reports: true);
    }

    public function classify(Submission $submission): Classification
    {
        $answer = $this->call('comment-check', $this->fields($submission));
        $body = trim($answer->body);
        $reasons = [];
        if (null !== $code = $answer->header('x-akismet-alert-code')) {
            $reasons[] = \sprintf('alert %s: %s', $code, $answer->header('x-akismet-alert-msg') ?? '');
        }
        // Akismet may ask to be asked again later (recheck_reason=recheck): the delay, in seconds.
        $data = null !== $answer->header('x-akismet-recheck-after') ? ['recheck_after' => (int) $answer->header('x-akismet-recheck-after')] : [];

        return match ($body) {
            'false' => new Classification(Label::HAM, $reasons, $data),
            'true' => new Classification('discard' === strtolower((string) $answer->header('x-akismet-pro-tip')) ? Label::FLAGRANT : Label::SPAM, $reasons, $data),
            default => throw $this->error('comment-check', $answer),
        };
    }

    public function report(Submission $submission, bool $spam): void
    {
        $call = $spam ? 'submit-spam' : 'submit-ham';
        $answer = $this->call($call, $this->fields($submission));
        if (self::THANKS !== trim($answer->body)) {
            throw $this->error($call, $answer);
        }
    }

    /**
     * Whether Akismet takes the key for the site - once, when the key is
     * typed in, not before every call.
     */
    public function verifyKey(?string $site = null): bool
    {
        $answer = $this->call('verify-key', ['api_key' => $this->apiKey, 'blog' => $site ?? $this->site ?? throw new InvalidConfigException('The "akismet" gateway needs the site\'s home page to verify its key: the site option.')]);

        return match (trim($answer->body)) {
            'valid' => true,
            'invalid' => false,
            default => throw $this->error('verify-key', $answer),
        };
    }

    /** @return array<string, string> what Akismet is told of a submission */
    public function fields(Submission $submission): array
    {
        $site = $submission->site ?? $this->site ?? throw new InvalidConfigException('The "akismet" gateway needs the site\'s home page: the submission\'s site, or the site option.');
        if (null === $submission->ip || '' === $submission->ip) {
            throw new InvalidConfigException('The "akismet" gateway needs the visitor\'s IP address (the submission\'s ip).');
        }

        return array_filter([
            'api_key' => $this->apiKey,
            'blog' => $site,
            'user_ip' => $submission->ip,
            'user_agent' => $submission->userAgent,
            'referrer' => $submission->referrer,
            'permalink' => $submission->permalink,
            'comment_type' => $submission->type,
            'comment_author' => $submission->author,
            'comment_author_email' => $submission->email,
            'comment_author_url' => $submission->url,
            'comment_content' => $submission->content,
            'comment_date_gmt' => $submission->date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'blog_lang' => $submission->language ?? $this->language,
            'blog_charset' => 'UTF-8',
            'is_test' => $this->test ? '1' : null,
        ], static fn (?string $value) => null !== $value && '' !== $value);
    }

    /** @param array<string, string> $fields */
    private function call(string $call, array $fields): Answer
    {
        $answer = Answer::send($this->http, 'akismet', 'POST', rtrim($this->baseUri, '/').'/'.$call, ['body' => $fields]);
        if (\in_array($answer->status, [401, 403], true)) {
            throw new InvalidKeyException('akismet', \sprintf('%s: HTTP %d, the key was refused.', $call, $answer->status), (string) $answer->status);
        }

        return $answer;
    }

    private function error(string $call, Answer $answer): ProviderException
    {
        $help = $answer->header('x-akismet-debug-help');
        $alert = $answer->header('x-akismet-alert-code');
        $message = \sprintf('%s answered "%s"%s%s.', $call, mb_substr(trim($answer->body), 0, 100), null !== $help ? ': '.$help : '', null !== $alert ? \sprintf(' (alert %s: %s)', $alert, $answer->header('x-akismet-alert-msg')) : '');

        // "invalid" with nothing else, or a word of the key: the key. Anything else: the request.
        return 'invalid' === trim($answer->body) && (null === $help || str_contains(strtolower($help), 'key'))
            ? new InvalidKeyException('akismet', $message, $alert ?? 'invalid')
            : new ProviderException('akismet', $message, $alert);
    }
}
