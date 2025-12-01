# Vision KPIs (Live Ops / Product Health)

## Fairness & Safety
- **Protection blocks:** `BEGINNER_BLOCK`, `ERR_PROTECTED` counts per day; alert if spike >2x baseline.
- **Rate-limit hits:** command/map/recruit rate-limit events per user/day; 95th percentile hit-rate < 3%.
- **Abuse flags:** friendly-fire override usage, attack-cap denials, push/alt detections (if enabled).

## Social Engagement
- **Ops participation:** % of active players joining tribe ops per week; average commands tagged to ops.
- **Support volume:** support commands sent/received per active user; ratio of support to attacks.
- **Chat/Forum activity:** posts per active day; tribe forum thread/post counts; mentor pings.

## Progression & Pacing
- **Time to 2nd village:** median and p75 hours from registration to first conquest/found; track per world preset.
- **Queue uptime:** % time with active build/recruit/research queues; idle queue rate per segment (casual/mid/hardcore).
- **Village growth:** average building levels (Town Hall, resource fields) at day 3/7/14; troop counts per archetype.

## Combat Health
- **Attack/defense balance:** K/D by archetype; average wall change per battle; overstack penalty frequency and average multiplier.
- **Intel freshness:** average age of intel on targets attacked; scout success/failure rates.
- **Conquest funnel:** conquest attempts vs successes; top failure reason codes (e.g., ERR_PROTECTED, no nobles alive).

## Economy & Monetization (Fair-First)
- **Resource flow:** plunder vs production vs sinks (builds, research, troop training); storage overcap rate.
- **Speed/QoL usage:** speed token usage rate, queue slot usage; ensure parity across spend tiers (spend vs non-spend delta < target).
- **Cosmetic adoption:** skin/theme usage; battle pass progression; zero pay-for-power validation.

## Retention & Engagement
- **D1/D7/D30 retention:** segmented by world preset and tribe membership.
- **Sessions per day:** by segment; median session length; quiet-hours usage.
- **Task/quest completion:** daily/weekly task start/finish; reroll counts; claim rate.

## Alert Threshold Examples
- Protection blocks +200% day-over-day → review bullying/targeting.
- Rate-limit hits >5% users in 24h → tune limits or client polling.
- Time to 2nd village p75 > target by +20% → review pacing/economy.
- Overstack penalty triggered in >10% battles on a world → consider threshold tuning.
