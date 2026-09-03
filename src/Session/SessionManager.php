<?php

declare(strict_types=1);

namespace Ract\Session;

use InvalidArgumentException;
use Ract\Http\Request;

final class SessionManager
{
    public function __construct(
        private readonly SessionDriver $driver,
        private readonly string $cookie = 'ract_session',
        private readonly int $lifetime = 120,
        private readonly string $path = '/',
        private readonly ?string $domain = null,
        private readonly bool $secure = false,
        private readonly bool $httpOnly = true,
        private readonly string $sameSite = 'Lax',
    ) {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/D', $this->cookie) !== 1) {
            throw new InvalidArgumentException('The session cookie name is invalid.');
        }

        if ($this->lifetime < 1) {
            throw new InvalidArgumentException('The session lifetime must be at least one minute.');
        }

        if (!str_starts_with($this->path, '/') || preg_match('/[;\x00-\x1F\x7F]/', $this->path) === 1) {
            throw new InvalidArgumentException('The session cookie path is invalid.');
        }

        if ($this->domain !== null && preg_match('/[;\x00-\x1F\x7F]/', $this->domain) === 1) {
            throw new InvalidArgumentException('The session cookie domain is invalid.');
        }

        if (!in_array($this->sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new InvalidArgumentException('Session SameSite must be Lax, Strict, or None.');
        }

        if ($this->sameSite === 'None' && !$this->secure) {
            throw new InvalidArgumentException('SameSite=None session cookies must be secure.');
        }
    }

    public function start(Request $request): Session
    {
        $id = $request->cookie($this->cookie);

        if (!is_string($id) || preg_match('/^[a-f0-9]{64}$/D', $id) !== 1 || !$this->driver->exists($id)) {
            return new Session(Session::generateId());
        }

        $payload = $this->driver->read($id);
        $data = $payload['data'] ?? [];
        $flash = $payload['flash'] ?? [];

        return new Session(
            $id,
            is_array($data) ? $data : [],
            is_array($flash) ? array_values(array_filter($flash, 'is_string')) : [],
        );
    }

    public function save(Session $session): void
    {
        $this->driver->write(
            $session->id(),
            $session->payload(),
            time() + ($this->lifetime * 60),
        );

        if ($session->previousId() !== null && $session->previousId() !== $session->id()) {
            $this->driver->destroy($session->previousId());
        }

        if (random_int(1, 100) <= 2) {
            $this->driver->garbageCollect(time());
        }
    }

    public function cookieHeader(Session $session): string
    {
        $expires = time() + ($this->lifetime * 60);
        $parts = [
            $this->cookie . '=' . $session->id(),
            'Path=' . $this->path,
            'Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT',
            'Max-Age=' . ($this->lifetime * 60),
            'SameSite=' . $this->sameSite,
        ];

        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        return implode('; ', $parts);
    }
}
