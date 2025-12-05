<?php
declare(strict_types=1);

$hookBusPath = __DIR__ . '/../lib/hooks/HookBus.php';
if (!class_exists('HookBus') && file_exists($hookBusPath)) {
    require_once $hookBusPath;
}

if (!class_exists('HookBus')) {
    return;
}

$logEvent = static function (string $event, array $payload): void {
    HookBus::logEvent($event, $payload);
};

HookBus::addListener('village.building.completed', static function (array $payload) use ($logEvent) {
    $logEvent('village.building.completed', $payload);
});

HookBus::addListener('village.recruitment.completed', static function (array $payload) use ($logEvent) {
    $logEvent('village.recruitment.completed', $payload);
});

HookBus::addListener('village.research.completed', static function (array $payload) use ($logEvent) {
    $logEvent('village.research.completed', $payload);
});

HookBus::addListener('village.trade.delivered', static function (array $payload) use ($logEvent) {
    $logEvent('village.trade.delivered', $payload);
});

HookBus::addListener('village.created', static function (array $payload) use ($logEvent) {
    $logEvent('village.created', $payload);
});

HookBus::addListener('message.sent', static function (array $payload) use ($logEvent) {
    $logEvent('message.sent', $payload);
});
