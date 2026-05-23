<?php

namespace forumaker\Steam\OAuth2;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class SteamResourceOwner implements ResourceOwnerInterface
{
    public function __construct(protected array $response = [])
    {
    }

    public function getId(): ?string
    {
        return isset($this->response['steamid']) ? (string) $this->response['steamid'] : null;
    }

    public function getPersonaName(): ?string
    {
        return $this->response['personaname'] ?? null;
    }

    public function getRealName(): ?string
    {
        $realName = $this->response['realname'] ?? null;

        return is_string($realName) && trim($realName) !== '' ? $realName : null;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->response['avatarfull']
            ?? $this->response['avatarmedium']
            ?? $this->response['avatar']
            ?? null;
    }

    public function getProfileUrl(): ?string
    {
        return $this->response['profileurl'] ?? null;
    }

    public function toArray(): array
    {
        return $this->response;
    }
}
