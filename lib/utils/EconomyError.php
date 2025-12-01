<?php
declare(strict_types=1);

/**
 * Standard economy error codes for trades/aid/minting.
 */
class EconomyError
{
    public const ERR_CAP = 'ERR_CAP';           // capacity/availability exceeded
    public const ERR_TAX = 'ERR_TAX';           // rejected due to tax/ratio constraints
    public const ERR_ALT_BLOCK = 'ERR_ALT_BLOCK'; // blocked by anti-pushing/alt rules
    public const ERR_RATE_LIMIT = 'ERR_RATE_LIMIT'; // throttled due to rate limits
    public const ERR_VALIDATION = 'ERR_VALIDATION'; // invalid payload/inputs
}
