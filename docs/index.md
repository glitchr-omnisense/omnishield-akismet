---
title: omnishield/akismet
order: 1
---

# omnishield/akismet

## Installation

```sh
composer require omnishield/akismet
```

PHP 8.2 or later, `omniscient/omnishield` and `symfony/http-client` (give the factory your
application's client, a `MockHttpClient` in a test). An API key from akismet.com.

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

| Option | Default | |
|---|---|---|
| `api_key` | required | |
| `site` | | the site's home page (`blog`), when a submission does not carry its own |
| `language` | | the site's language (`blog_lang`), when a submission does not carry its own |
| `test` | `false` | `is_test`: Akismet answers and learns nothing - for development |
| `base_uri` | `https://rest.akismet.com/1.1/` | |

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

## Verified, and not

| | |
|---|---|
| The family's conformance suite (`Omnishield\Testing\GatewayContractTests`) | **green on 2026-10-25**, offline: `Tests/AkismetContractTest.php` on the documented answers; HTTP 401/403 now an `InvalidKeyException` |
| Against Akismet itself | **not verified in real: no key.** The answers (`Tests/Fixtures/*.json`: a body and its headers) are written from the documentation |
| The fields sent | by the tests, against the documentation's list |
| `verify-key` | by the tests only |
