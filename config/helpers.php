<?php
// =========================================================================
// Shared helper functions used across patient/doctor/admin modules.
// =========================================================================

/**
 * Record an entry in the Recent Activity feed for a given user.
 */
function logActivity(PDO $pdo, int $userId, string $description, string $icon = 'circle-info'): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, icon, description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $icon, $description]);
    } catch (Exception $e) {
        // Activity logging is best-effort; never let it break the primary action.
    }
}

/**
 * Create a notification for a given user (shows up in the bell icon / notifications page).
 */
function notifyUser(PDO $pdo, int $userId, string $message, ?string $link = null): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $message, $link]);
    } catch (Exception $e) {
        // Notification creation is best-effort; never let it break the primary action.
    }
}
