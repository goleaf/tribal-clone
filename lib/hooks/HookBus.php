<?php
declare(strict_types=1);

/**
 * Minimal event bus for first-party hooks.
 * Allows lightweight instrumentation without coupling managers together.
 */
class HookBus
{
    /** @var array<string,array<int,callable[]>> */
    private static array $listeners = [];

    /**
     * Register a listener for an event name.
     *
     * @param string   $event     Identifier such as 'village.building.completed'
     * @param callable $listener  Signature: function(array $payload, string $event): mixed
     * @param int      $priority  Lower runs earlier; equal priority keeps insertion order.
     */
    public static function addListener(string $event, callable $listener, int $priority = 10): void
    {
        self::$listeners[$event][$priority][] = $listener;
    }

    /**
     * Dispatch an event to all listeners.
     *
     * @return array Collected listener return values
     */
    public static function dispatch(string $event, array $payload = []): array
    {
        if (empty(self::$listeners[$event])) {
            return [];
        }

        ksort(self::$listeners[$event]);

        $responses = [];
        foreach (self::$listeners[$event] as $listeners) {
            foreach ($listeners as $listener) {
                try {
                    $responses[] = $listener($payload, $event);
                } catch (\Throwable $e) {
                    self::logEvent('hook.error', [
                        'event' => $event,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        return $responses;
    }

    /**
     * Remove listeners (all or for a single event).
     */
    public static function clearListeners(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
            return;
        }

        unset(self::$listeners[$event]);
    }

    /**
     * Count registered listeners (all or for a single event).
     */
    public static function listenerCount(?string $event = null): int
    {
        if ($event === null) {
            $total = 0;
            foreach (self::$listeners as $byPriority) {
                foreach ($byPriority as $listeners) {
                    $total += count($listeners);
                }
            }
            return $total;
        }

        if (empty(self::$listeners[$event])) {
            return 0;
        }

        $count = 0;
        foreach (self::$listeners[$event] as $listeners) {
            $count += count($listeners);
        }

        return $count;
    }

    /**
     * Append an event line to logs/hooks.log (best-effort, non-fatal).
     */
    public static function logEvent(string $event, array $payload = [], ?string $logFile = null): void
    {
        $logFile = $logFile ?? __DIR__ . '/../../logs/hooks.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $line = sprintf(
            "[%s] %s %s%s",
            date('c'),
            $event,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            PHP_EOL
        );

        @error_log($line, 3, $logFile);
    }
}
