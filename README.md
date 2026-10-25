# omnishield/akismet

**Akismet** for [omniscient/omnishield](https://github.com/glitchr-studio/omnishield): is this content
spam. `comment-check` answers ham, spam, or spam flagrant enough to discard; `submit-spam` and
`submit-ham` teach it; `verify-key` checks a key once, when it is typed in.

```php
use Omnishield\Akismet\AkismetGatewayFactory;
use Omnishield\Model\Submission;

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
omnishield:
    gateways:
        comments:
            factory: akismet
            options: { api_key: '%env(AKISMET_API_KEY)%', site: 'https://example.org' }
```

**An error is never taken for ham**: a key Akismet refuses is an `InvalidKeyException`, a request
it could not read a `ProviderException`, no answer an `UnreachableException` - whether the
content goes through then is the application's call. The text, the name, the e-mail and the
address of the visitor go to Akismet (Automattic): say so in the privacy policy.

[Documentation](docs/index.md): what it calls, the credentials, the capabilities, an example run offline, the errors, the fields sent, the answers, the options, what was verified -
nothing against Akismet itself: no key.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Automattic Inc. (Akismet). It reaches their service on behalf of whoever runs it, and
it does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Akismet's terms
([akismet.com/tos](https://akismet.com/tos/)), for obtaining whatever they require (an account, a
key, a licence or subscription, an attribution or a citation, a rate limit to respect), and for any
use made of what it returns. The authors publish it free of charge, make no commercial use of it,
and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.

Formerly `omniguard/akismet`, renamed on 2026-10-10 with its family (`glitchr/omnishield`).
