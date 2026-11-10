# omnishield/akismet

**Akismet** for [omnisense/omnishield](https://github.com/glitchr-omnisense/omnishield): is this content
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

## Usage policy

[`policy.json`](policy.json) (omnisense/contracts' schema, read at the provider's terms on 2026-11-10; its `provenance.notes` says what was read where): Commercial sites need a paid plan (`commercial: contract`: say `usage: {commercial: true, contract: true}` once you have one); the free key is for personal sites. The limits depend on the plan and are declared unknown: give your plan's as a tightening. A 429 and its `Retry-After` hold the next calls (`UnreachableException::retryAfter()`). See the core's [usage policies](https://github.com/glitchr-omnisense/omnishield/blob/1.x/docs/policies.md).

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

Formerly `omniguard/akismet`, renamed on 2026-10-10 with its family (`glitchr/omniguard`, now `omnisense/omnishield`).
