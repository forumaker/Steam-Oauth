<?php

namespace forumaker\Steam\OAuth2;

use GuzzleHttp\Psr7\Request;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class SteamProvider extends AbstractProvider
{
    protected string $apiKey;

    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->apiKey = $options['apiKey'] ?? '';
        parent::__construct($options, $collaborators);
    }

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://steamcommunity.com/openid/login';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://steamcommunity.com/openid/login';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/';
    }

    protected function getDefaultScopes(): array
    {
        return [];
    }

    protected function getAuthorizationParameters(array $options): array
    {
        if (empty($options['state'])) {
            $options['state'] = $this->getRandomState();
        }

        $this->state = $options['state'];

        $redirectUri = $this->redirectUri;
        $separator   = str_contains($redirectUri, '?') ? '&' : '?';
        $returnTo    = $redirectUri . $separator
            . 'state=' . urlencode($options['state'])
            . '&code=steam_openid_callback';

        $parsed = parse_url($redirectUri);
        $realm  = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (! empty($parsed['port'])) {
            $realm .= ':' . $parsed['port'];
        }

        return [
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $returnTo,
            'openid.realm'      => $realm,
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];
    }

    public function getAccessToken($grant, array $options = []): AccessToken
    {
        $rawParams    = resolve('fof-oauth-request')->getQueryParams();
        $openIdParams = $this->reconstructOpenIdParams($rawParams);

        if (empty($openIdParams)) {
            throw new IdentityProviderException('No Steam OpenID parameters found in callback request.', 0, $rawParams);
        }

        $this->verifyOpenIdAssertion($openIdParams);

        $claimedId = $rawParams['openid_claimed_id'] ?? '';

        if (! preg_match('/steamcommunity\.com\/openid\/id\/(\d+)/', $claimedId, $matches)) {
            throw new IdentityProviderException('Could not extract SteamID64 from OpenID claimed_id.', 0, $rawParams);
        }

        return new AccessToken(['access_token' => $matches[1]]);
    }

    public function getResourceOwner(AccessToken $token): SteamResourceOwner
    {
        $steamId = $token->getToken();

        $url = $this->getResourceOwnerDetailsUrl($token)
            . '?key='      . urlencode($this->apiKey)
            . '&steamids=' . urlencode($steamId);

        $request  = $this->getRequest(self::METHOD_GET, $url);
        $response = $this->getParsedResponse($request);

        $players = $response['response']['players'] ?? [];

        if (empty($players)) {
            throw new IdentityProviderException('Steam API returned no player data for SteamID: ' . $steamId, 0, $response);
        }

        $player            = $players[0];
        $player['steamid'] = $player['steamid'] ?? $steamId;

        return new SteamResourceOwner($player);
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (isset($data['error'])) {
            $message = $data['message'] ?? $data['error'] ?? 'Unknown Steam API error';
            throw new IdentityProviderException((string) $message, (int) $response->getStatusCode(), $data);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token): SteamResourceOwner
    {
        return new SteamResourceOwner($response);
    }

    private function reconstructOpenIdParams(array $phpParams): array
    {
        $openIdParams = [];

        foreach ($phpParams as $key => $value) {
            if (str_starts_with($key, 'openid_')) {
                $openIdParams['openid.' . substr($key, strlen('openid_'))] = $value;
            }
        }

        return $openIdParams;
    }

    private function verifyOpenIdAssertion(array $openIdParams): void
    {
        $verifyParams                = $openIdParams;
        $verifyParams['openid.mode'] = 'check_authentication';

        $body    = http_build_query($verifyParams);
        $request = new Request(
            'POST',
            'https://steamcommunity.com/openid/login',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $body
        );

        $response = $this->getHttpClient()->send($request, ['timeout' => 10]);
        $content  = (string) $response->getBody();

        if (! str_contains($content, 'is_valid:true')) {
            throw new IdentityProviderException('Steam OpenID verification failed.', (int) $response->getStatusCode(), $content);
        }
    }
}
