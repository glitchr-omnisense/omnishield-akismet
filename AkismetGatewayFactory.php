<?php

namespace Omniguard\Akismet;

use Omniguard\Config;
use Omniguard\GatewayFactory;
use Omniguard\GatewayInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Akismet: is this content spam.
 *
 *   options:
 *     api_key: '%env(AKISMET_API_KEY)%'    # required
 *     site: https://example.org            # the site's home page ("blog"), when a submission does not carry one
 *     language: ~                          # the site's language ("blog_lang"), when a submission does not carry one: fr, fr_FR
 *     test: false                          # true: is_test - Akismet answers, and learns nothing
 *     base_uri: https://rest.akismet.com/1.1/
 */
final class AkismetGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniguard.factory_name' => 'akismet',
            'omniguard.factory_title' => 'Akismet',
            'omniguard.required_options' => ['api_key'],
            'site' => null,
            'language' => null,
            'test' => false,
            'base_uri' => AkismetGateway::BASE_URI,
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new AkismetGateway(
            $this->http ?? HttpClient::create(),
            (string) $c['api_key'],
            $c->string('site'),
            $c->string('language'),
            $c->bool('test'),
            $c->string('base_uri') ?? AkismetGateway::BASE_URI,
        );
    }
}
