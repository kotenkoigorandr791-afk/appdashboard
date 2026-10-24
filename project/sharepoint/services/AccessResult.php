<?php
final class AccessResult
{
    public bool $allowed;
    public string $reason;
    public ?Resource $resource;

    private function __construct(bool $allowed, string $reason, ?Resource $resource = null)
    {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->resource = $resource;
    }

    public static function allow(?Resource $resource = null): self
    {
        return new self(true, 'ok', $resource);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public static function httpStatus(string $reason): int
    {
        return match ($reason) {
            'not_found', 'resource_unavailable' => 404,
            'expired' => 410,
            'revoked', 'max_views_exceeded', 'permission_denied', 'path_traversal' => 403,
            'password_required', 'login_required' => 401,
            'user_not_allowed' => 403,
            default => 400,
        };
    }
}
