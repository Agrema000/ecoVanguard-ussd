<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    // Look for ANY pending reports currently awaiting action in the database
    $stmtReport = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'");
    $pendingReports = $stmtReport->fetchColumn();

    // Look for any bookings created in the last 10 minutes to capture recent demo trials
    $stmtBooking = $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at >= NOW() - INTERVAL 10 MINUTE");
    $recentBookings = $stmtBooking->fetchColumn();

    // Trigger the alarm if there are pending reports OR very recent eco-tourism bookings
    $alertTrigger = ($pendingReports > 0 || $recentBookings > 0) ? true : false;

    echo json_encode([
        "status" => "success",
        "trigger_alert" => $alertTrigger,
        "pending_reports_count" => $pendingReports,
        "recent_bookings_count" => $recentBookings
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>