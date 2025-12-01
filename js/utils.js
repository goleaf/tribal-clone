// JavaScript helper functions

/**
 * Format a number with thousand separators
 * @param {number} number Number to format
 * @returns {string} Formatted number
 */
function formatNumber(number) {
    return new Intl.NumberFormat('en-US').format(number);
}

/**
 * Format seconds into HH:MM:SS
 * @param {number} seconds Time in seconds
 * @returns {string} Formatted time
 */
function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Format remaining time until task finishes.
 * @param {number} finishTime Finish timestamp (seconds).
 * @returns {string} Formatted remaining time (e.g., "1h 30m 15s").
 */
function getRemainingTimeText(finishTime) {
    if (finishTime === null) return '';
    const finishTimeMillis = finishTime * 1000;
    const currentTimeMillis = new Date().getTime();
    const remainingMillis = finishTimeMillis - currentTimeMillis;

    if (remainingMillis <= 0) return 'Completed!';

    const seconds = Math.floor((remainingMillis / 1000) % 60);
    const minutes = Math.floor((remainingMillis / (1000 * 60)) % 60);
    const hours = Math.floor((remainingMillis / (1000 * 60 * 60)) % 24);
    const days = Math.floor(remainingMillis / (1000 * 60 * 60 * 24));

    let timeString = '';
    if (days > 0) timeString += days + 'd ';
    if (hours > 0 || days > 0) timeString += hours + 'h ';
    timeString += minutes + 'm ' + seconds + 's';

    return timeString.trim();
}

// Add more shared JS functions here
