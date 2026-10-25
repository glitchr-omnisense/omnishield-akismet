# omnishield/akismet

Akismet (Automattic's spam service) as an Omnishield classifier: is this content spam - ham, spam,
or spam flagrant enough to drop unseen - and the moderator's corrections sent back. The gateway
calls Akismet's REST API 1.1 on `https://rest.akismet.com/1.1/`: `comment-check` (`classify()`),
`submit-spam` and `submit-ham` (`report()`), `verify-key` (`verifyKey()`), each a form-encoded
`POST` with the key as the `api_key` parameter. There is no sandbox: the `test` option sends
`is_test`, which Akismet answers without learning from it.

## Installation

```sh
composer require omnishield/akismet
```

PHP 8.2 or later, `omniscient/omnishield` and `symfony/http-client` (give the factory your
application's client, a `MockHttpClient` in a test). An API key from akismet.com.

## Credentials

- An **API key** of an Akismet account (akismet.com), for the site it is used on: `api_key`.
- The site's **home page** (`site`, Akismet's `blog`), unless every submission carries its own.

`verifyKey(?string $site = null)` asks Akismet whether it takes the key for the site - once, when
the key is typed in (a back office), not before every call; true or false.

## The sources

Written from Akismet's documentation as it read on 2026-10-07:
[comment check](https://akismet.com/developers/detailed-docs/comment-check/),
[submit spam](https://akismet.com/developers/detailed-docs/submit-spam-missed-spam/),
submit ham, [key verification](https://akismet.com/developers/detailed-docs/key-verification/) and
the [response error codes](https://akismet.com/developers/detailed-docs/errors/).

**The address**: the key travels as the `api_key` parameter, on `https://rest.akismet.com/1.1/`.
The older form - the key as a subdomain, `https://<key>.rest.akismet.com/1.1/comment-check`, which
omnibase's `SpamChecker` and blog's `AkismetReporter` used - is no longer the documented one.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the account's key |
| `site` | string | `null` | no | the site's home page (`blog`), when a submission does not carry its own |
| `language` | string | `null` | no | the site's language (`blog_lang`), when a submission does not carry its own: `fr`, `fr_FR` |
| `test` | bool (or `"1"`, `"true"`, `"yes"`, `"on"`) | `false` | no | `is_test`: Akismet answers and learns nothing - for development |
| `base_uri` | string | `https://rest.akismet.com/1.1/` | no | |

`new AkismetGatewayFactory(?HttpClientInterface $http = null)`: the application's HTTP client
(`HttpClient::create()` when none is given).

## Capabilities

| | |
|---|---|
| is this token valid (`ChallengeInterface`) | no |
| is this content spam (`ClassifierInterface::classify()`) | yes: `ham`, `spam`, `flagrant` |
| reports (`ClassifierInterface::report()`) | yes: `submit-spam`, `submit-ham` |
| is this identity known (`ReputationInterface`) | no |
| third party, cookies | Akismet; no cookie (nothing in the browser) |

## What Akismet is told

| Akismet's field | From the `Submission` |
|---|---|
| `api_key` | the option |
| `blog` | `site` (else the option): **the home page**, not the current page - required |
| `user_ip` | `ip` - required |
| `user_agent`, `referrer` | `userAgent`, `referrer` |
| `permalink` | `permalink`: the page the content was submitted on |
| `comment_type` | `type`: `comment`, `reply`, `forum-post`, `blog-post`, `contact-form`, `signup`, `message` |
| `comment_author`, `comment_author_email`, `comment_author_url` | `author`, `email`, `url` - as text |
| `comment_content` | `content` |
| `comment_date_gmt` | `date`, in UTC, ISO 8601: `2026-10-07T08:00:00Z` |
| `blog_lang` | `language` (else the option) |
| `blog_charset` | `UTF-8` |
| `is_test` | `1` when `test` |

Empty fields are left out. A submission without the visitor's address or the site's home page is
not sent: `InvalidConfigException`.

## The answers

| Akismet answers | |
|---|---|
| `false` | `Label::HAM` |
| `true` | `Label::SPAM` |
| `true` and `X-akismet-pro-tip: discard` | `Label::FLAGRANT`: spam beyond doubt, which may be dropped unseen |
| `X-akismet-alert-code`, `X-akismet-alert-msg` | kept in the classification's `reasons`: `alert 10011: ...` (an account to upgrade, suspended...) |
| `X-akismet-recheck-after` | kept in its `data` as `recheck_after` (seconds) |
| `invalid`, with nothing else or a word of the key in `X-akismet-debug-help` | `InvalidKeyException` |
| `invalid` with another debug help (`Empty "blog" value`), anything else | `ProviderException` |
| no answer, HTTP 5xx, 429 | `UnreachableException` |
| `submit-spam`, `submit-ham`: `Thanks for making the web a better place.` | reported; anything else is thrown |
| `verify-key`: `valid` / `invalid` | `verifyKey()`: true / false |

Akismet's own testing values: the author or the content `akismet-guaranteed-spam` is always spam;
`user_role: administrator` never is (this package does not send a role).

## What changed from omnibase's two clients

The core's `SpamChecker` and blog's `AkismetReporter` built their requests each their own way.
Here, one gateway, and their defects left behind: the author and the date are sent as text (they
went as a `User` and a `DateTime`); `blog` is the home page (it was the current page in one and the
host in the other); an error is thrown instead of being taken for "not spam"; the key travels as
`api_key`.

## Example

A comment classified three times and reported, Akismet's answers given by a `MockHttpClient`
(run in omnishield's workspace on 2026-10-26, offline):

```php
<?php

require 'vendor/autoload.php';

use Omnishield\Akismet\AkismetGatewayFactory;
use Omnishield\Model\Submission;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// Akismet's three answers to comment-check, then "Thanks" to submit-spam: answered here, no network.
$http = new MockHttpClient([
    new MockResponse('false'),
    new MockResponse('true'),
    new MockResponse('true', ['response_headers' => ['X-akismet-pro-tip' => 'discard']]),
    new MockResponse('Thanks for making the web a better place.'),
]);
$akismet = (new AkismetGatewayFactory($http))->create(['api_key' => 'abc123def456', 'site' => 'https://example.org', 'language' => 'fr']);

$comment = new Submission(
    content: 'Lovely post, thank you.', author: 'Ada', email: 'ada@example.org',
    ip: '192.0.2.10', userAgent: 'Mozilla/5.0', permalink: 'https://example.org/blog/a-post',
    type: Submission::COMMENT, date: new DateTimeImmutable('2026-10-25 08:00:00 UTC'),
);
foreach ([1, 2, 3] as $answer) {
    $classification = $akismet->classify($comment);
    echo $classification->label->value, ', spam: ', var_export($classification->isSpam(), true), "\n";
}
$akismet->report($comment, spam: true);                       // a moderator says it was spam after all
echo "report taken; requests sent: ", $http->getRequestsCount(), "\n";
echo implode(', ', array_keys($akismet->fields($comment))), "\n";
```

```
ham, spam: false
spam, spam: true
flagrant, spam: true
report taken; requests sent: 4
api_key, blog, user_ip, user_agent, permalink, comment_type, comment_author, comment_author_email, comment_content, comment_date_gmt, blog_lang, blog_charset
```

Without the mock, give the factory the application's client and the real key. In Symfony:

```yaml
omnishield:
    gateways:
        comments:
            factory: akismet
            options: { api_key: '%env(AKISMET_API_KEY)%', site: 'https://example.org', language: fr }
```

## Errors

| Exception | When |
|---|---|
| `InvalidKeyException` (`[akismet] ...`) | HTTP 401 or 403; `invalid` with no debug help, or one that names the key |
| `ProviderException` | `invalid` with another debug help (`Empty "blog" value`), any other body to `comment-check` or `verify-key`, a report answered otherwise than "Thanks"; `providerCode` is the alert code when there is one |
| `UnreachableException` | no answer, HTTP 5xx, 429 |
| `InvalidConfigException` | `api_key` missing; a submission without `ip`, or without a site (its own or the option); `verifyKey()` with no site |

An error is never taken for ham: whether to publish the content then is the application's call
(hold it for moderation).

## Verified, and not

| | |
|---|---|
| The family's conformance suite (`Omnishield\Testing\GatewayContractTests`) | **green on 2026-10-25**, offline: `Tests/AkismetContractTest.php` on the documented answers; HTTP 401/403 now an `InvalidKeyException` |
| Against Akismet itself | **not verified in real: no key.** The answers (`Tests/Fixtures/*.json`: a body and its headers) are written from the documentation |
| The fields sent | by the tests, against the documentation's list |
| `verify-key` | by the tests only |

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Automattic Inc. (Akismet). It reaches their service on behalf of whoever runs it, and
it does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Akismet's terms
([akismet.com/tos](https://akismet.com/tos/)), for obtaining whatever they require (an account, a
key, a licence or subscription, an attribution or a citation, a rate limit to respect), and for any
use made of what it returns. The authors publish it free of charge, make no commercial use of it,
and accept no liability for how it is used (see the package's LICENSE).
