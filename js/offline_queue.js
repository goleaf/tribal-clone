/**
 * Lightweight offline draft queue for weak/no-connection scenarios.
 * Stores pending commands in localStorage with TTL and status metadata.
 *
 * Exposes:
 *   window.OfflineDraftQueue
 *   window.OFFLINE_ERROR_CODES
 */
(function () {
    const OFFLINE_ERROR_CODES = {
        OFFLINE: 'ERR_OFFLINE',
        STALE_STATE: 'ERR_STALE_STATE',
        CONFLICT: 'ERR_CONFLICT',
        REPLAYED: 'ERR_REPLAYED'
    };

    function getStorage() {
        try {
            return window.localStorage;
        } catch (e) {
            return null;
        }
    }

    function emitTelemetry(event, detail = {}) {
        if (typeof window === 'undefined' || !window.dispatchEvent) return;
        try {
            window.dispatchEvent(new CustomEvent('telemetry', { detail: { event, ...detail } }));
        } catch (e) {
            // noop
        }
    }

    class OfflineDraftQueue {
        constructor(options = {}) {
            this.storage = getStorage();
            this.storageKey = options.storageKey || 'tw_offline_drafts';
            this.defaultTtlMs = options.ttlMs || 15 * 60 * 1000; // 15 minutes
            this.drafts = [];
            this.load();
            this.attachOfflineListeners();
        }

        attachOfflineListeners() {
            if (typeof window === 'undefined') return;
            window.addEventListener('offline', () => emitTelemetry('offline_start'));
            window.addEventListener('online', () => emitTelemetry('offline_end'));
        }

        load() {
            if (!this.storage) return;
            try {
                const raw = this.storage.getItem(this.storageKey);
                this.drafts = raw ? JSON.parse(raw) : [];
            } catch (e) {
                this.drafts = [];
            }
            this.dropExpired();
        }

        persist() {
            if (!this.storage) return;
            try {
                this.storage.setItem(this.storageKey, JSON.stringify(this.drafts));
            } catch (e) {
                // If storage is full, drop oldest draft to make room.
                this.drafts.shift();
            }
        }

        dropExpired(now = Date.now()) {
            const before = this.drafts.length;
            this.drafts = this.drafts.filter((d) => (d.created_at + d.ttl_ms) > now);
            if (this.drafts.length !== before) {
                this.persist();
                emitTelemetry('drafts_evicted', { count: before - this.drafts.length });
            }
        }

        enqueue(type, payload, ttlMs) {
            const now = Date.now();
            const draft = {
                id: `${now}-${Math.random().toString(16).slice(2, 8)}`,
                type,
                payload,
                created_at: now,
                ttl_ms: ttlMs || this.defaultTtlMs,
                status: 'queued'
            };
            this.drafts.push(draft);
            this.persist();
            emitTelemetry('draft_enqueued', { type });
            return draft;
        }

        list() {
            this.dropExpired();
            return [...this.drafts].sort((a, b) => a.created_at - b.created_at);
        }

        _update(id, updater) {
            const next = [];
            let changed = false;
            for (const draft of this.drafts) {
                if (draft.id === id) {
                    const updated = updater({ ...draft });
                    if (updated) next.push(updated);
                    changed = true;
                } else {
                    next.push(draft);
                }
            }
            if (changed) {
                this.drafts = next;
                this.persist();
            }
            return changed;
        }

        markSent(id) {
            return this._update(id, () => null); // remove on success
        }

        markFailed(id, errorCode = OFFLINE_ERROR_CODES.OFFLINE, message = '') {
            return this._update(id, (draft) => {
                draft.status = 'failed';
                draft.error_code = errorCode;
                draft.message = message;
                return draft;
            });
        }

        markConflict(id, message = 'Conflict detected') {
            return this._update(id, (draft) => {
                draft.status = 'conflict';
                draft.error_code = OFFLINE_ERROR_CODES.CONFLICT;
                draft.message = message;
                return draft;
            });
        }

        nextBatch(limit = 20) {
            return this.list().filter((d) => d.status === 'queued').slice(0, limit);
        }

        recordResult(id, result) {
            if (result && result.success) {
                const ok = this.markSent(id);
                if (ok) emitTelemetry('draft_sync_success');
                return ok;
            }
            const code = result?.error_code || OFFLINE_ERROR_CODES.OFFLINE;
            const msg = result?.message || 'Sync failed';
            const conflict = code === OFFLINE_ERROR_CODES.CONFLICT || code === OFFLINE_ERROR_CODES.STALE_STATE;
            const updated = conflict ? this.markConflict(id, msg) : this.markFailed(id, code, msg);
            if (updated) {
                emitTelemetry(conflict ? 'draft_conflict' : 'draft_sync_failure', { code });
            }
            return updated;
        }

        clear() {
            const count = this.drafts.length;
            this.drafts = [];
            this.persist();
            emitTelemetry('drafts_cleared', { count });
            return count;
        }
    }

    window.OfflineDraftQueue = OfflineDraftQueue;
    window.OFFLINE_ERROR_CODES = OFFLINE_ERROR_CODES;
})();
