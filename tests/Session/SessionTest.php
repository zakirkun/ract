<?php

declare(strict_types=1);

namespace Tests\Session;

use PHPUnit\Framework\TestCase;
use Ract\Http\Request;
use Ract\Session\FileSessionDriver;
use Ract\Session\Session;
use Ract\Session\SessionManager;

final class SessionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-session-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testItStoresNullValuesAndSupportsPullAndForget(): void
    {
        $session = new Session(Session::generateId());
        $session->put('nullable', null)->put('name', 'Ract');

        self::assertTrue($session->exists('nullable'));
        self::assertNull($session->get('nullable', 'fallback'));
        self::assertSame('Ract', $session->pull('name'));
        self::assertFalse($session->exists('name'));
    }

    public function testFlashDataLastsForTheFollowingRequestOnly(): void
    {
        $id = Session::generateId();
        $first = new Session($id);
        $first->flash('notice', 'Saved');
        $payload = $first->payload();

        $second = new Session($id, $payload['data'], $payload['flash']);
        self::assertSame('Saved', $second->get('notice'));
        $secondPayload = $second->payload();

        $third = new Session($id, $secondPayload['data'], $secondPayload['flash']);
        self::assertFalse($third->exists('notice'));
    }

    public function testFlashDataCanBeKeptForAnotherRequest(): void
    {
        $id = Session::generateId();
        $first = new Session($id);
        $first->flash('notice', 'Saved');
        $payload = $first->payload();
        $second = new Session($id, $payload['data'], $payload['flash']);

        $second->keep('notice');
        $kept = $second->payload();
        $third = new Session($id, $kept['data'], $kept['flash']);

        self::assertSame('Saved', $third->get('notice'));
    }

    public function testFileSessionsRoundTripThroughTheManagerAndRegenerateIds(): void
    {
        $driver = new FileSessionDriver($this->directory);
        $manager = new SessionManager($driver, secure: true, sameSite: 'Strict');
        $session = $manager->start(new Request('GET', '/'));
        $oldId = $session->id();
        $session->put('user_id', 42);
        $manager->save($session);

        $loaded = $manager->start(new Request('GET', '/', cookies: ['ract_session' => $oldId]));
        self::assertSame(42, $loaded->get('user_id'));

        $loaded->regenerate(true);
        $manager->save($loaded);

        self::assertNotSame($oldId, $loaded->id());
        self::assertFalse($driver->exists($oldId));
        self::assertStringContainsString('Secure', $manager->cookieHeader($loaded));
        self::assertStringContainsString('SameSite=Strict', $manager->cookieHeader($loaded));
    }

    public function testExpiredFileSessionsAreDiscarded(): void
    {
        $driver = new FileSessionDriver($this->directory);
        $id = Session::generateId();
        $driver->write($id, ['data' => ['stale' => true], 'flash' => []], time() - 1);

        self::assertSame([], $driver->read($id));
        self::assertFalse($driver->exists($id));
    }
}
