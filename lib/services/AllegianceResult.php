<?php
declare(strict_types=1);

/**
 * DTO describing allegiance resolution outcomes.
 */
class AllegianceResult
{
    public int $newAllegiance;
    public bool $captured;
    public int $dropApplied;
    public int $regenApplied;
    public ?int $floorApplied;
    public int $nextTickAt;
    public bool $antiSnipeActive;
    public ?int $antiSnipeUntil;
    public ?string $reason;

    public function __construct(
        int $newAllegiance,
        bool $captured,
        int $dropApplied,
        int $regenApplied,
        ?int $floorApplied,
        int $nextTickAt,
        bool $antiSnipeActive,
        ?int $antiSnipeUntil,
        ?string $reason = null
    ) {
        $this->newAllegiance = $newAllegiance;
        $this->captured = $captured;
        $this->dropApplied = $dropApplied;
        $this->regenApplied = $regenApplied;
        $this->floorApplied = $floorApplied;
        $this->nextTickAt = $nextTickAt;
        $this->antiSnipeActive = $antiSnipeActive;
        $this->antiSnipeUntil = $antiSnipeUntil;
        $this->reason = $reason;
    }
}
