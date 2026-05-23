<?php

namespace forumaker\Steam\Providers;

use Flarum\Forum\Auth\Registration;
use FoF\OAuth\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use forumaker\Steam\OAuth2\SteamProvider;
use forumaker\Steam\OAuth2\SteamResourceOwner;

class Steam extends Provider
{
    public function name(): string
    {
        return 'steam';
    }

    public function link(): string
    {
        return 'https://steamcommunity.com/dev/apikey';
    }

    public function fields(): array
    {
        return [
            'api_key' => 'steam_api_key_label',
        ];
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        return new SteamProvider([
            'apiKey'      => $this->getSetting('api_key'),
            'redirectUri' => $redirectUri,
        ]);
    }

    public function pkceEnabled(): bool
    {
        return false;
    }

    public function suggestions(Registration $registration, mixed $user, string $token): void
    {
        if (! $user instanceof SteamResourceOwner) {
            return;
        }

        if ($user->getPersonaName()) {
            $registration->suggestUsername($this->sanitizeUsername($user->getPersonaName()));
        }

        $this->provideAvatar($registration, $user->getAvatarUrl());
    }

    protected function sanitizeUsername(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\pL\pN_\-]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return mb_substr($value !== '' ? $value : 'steam-user', 0, 30);
    }
}
