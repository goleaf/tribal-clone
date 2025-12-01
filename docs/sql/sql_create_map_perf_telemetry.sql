CREATE TABLE IF NOT EXISTS `map_perf_telemetry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `world_id` int(11) NOT NULL,
  `request_rate` float DEFAULT NULL,
  `cache_hit_pct` float DEFAULT NULL,
  `payload_bytes` int(11) DEFAULT NULL,
  `render_ms` float DEFAULT NULL,
  `dropped_frames` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpt_user_time` (`user_id`,`created_at`),
  KEY `idx_mpt_world_time` (`world_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
