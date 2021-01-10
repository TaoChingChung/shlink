<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Rest\ApiKey\Model;

use Shlinkio\Shlink\Rest\ApiKey\Role;

final class RoleDefinition
{
    private string $roleName;
    private array $meta;

    private function __construct(string $roleName, array $meta)
    {
        $this->roleName = $roleName;
        $this->meta = $meta;
    }

    public static function forAuthoredShortUrls(): self
    {
        return new self(Role::AUTHORED_SHORT_URLS, []);
    }

    public static function forDomain(string $domainId): self
    {
        return new self(Role::DOMAIN_SPECIFIC, ['domain_id' => $domainId]);
    }

    public function roleName(): string
    {
        return $this->roleName;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}