# omniguard/akismet

**Akismet** for [glitchr/omniguard](https://github.com/glitchr-studio/omniguard): is this content
spam. `comment-check` answers ham, spam, or spam flagrant enough to discard; `submit-spam` and
`submit-ham` teach it; `verify-key` checks a key once, when it is typed in.

```php
use Omniguard\Akismet\AkismetGatewayFactory;
use Omniguard\Model\Submission;

$akismet = (new AkismetGatewayFactory($httpClient))->create(['api_key' => getenv('AKISMET_API_KEY'), 'site' => 'https://example.org']);

$classification = $akismet->classify(new Submission(
    content: $text, author: $name, email: $email, url: $website,
    ip: $ip, userAgent: $userAgent, referrer: $referrer, permalink: $pageUrl,
    language: 'fr', type: Submission::COMMENT, date: new DateTimeImmutable(),
));
$classification->label;      // Label::HAM, Label::SPAM, Label::FLAGRANT (X-akismet-pro-tip: discard)

$akismet->report($submission, spam: true);   // a spam it let through
```

```yaml
omniguard:
    gateways:
        comments:
            factory: akismet
            options: { api_key: '%env(AKISMET_API_KEY)%', site: 'https://example.org' }
```

**An error is never taken for ham**: a key Akismet refuses is an `InvalidKeyException`, a request
it could not read a `ProviderException`, no answer an `UnreachableException` - whether the
content goes through then is the application's call. The text, the name, the e-mail and the
address of the visitor go to Akismet (Automattic): say so in the privacy policy.

[Documentation](docs/index.md): the fields sent, the answers, the options, what was verified -
nothing against Akismet itself: no key.

License: LGPL-3.0-or-later.
