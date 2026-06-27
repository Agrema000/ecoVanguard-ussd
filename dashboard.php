<?php
require_once 'db.php';

// Handle Action Requests (Verify or Mark as Fake) safely using POST variables
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $reportId = intval($_POST['report_id']);
    $action   = $_POST['action'];

    try {
        // Fetch report details
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($report && $report['status'] === 'Pending') {
            $reporterPhone = $report['phone_number'];

            if ($action === 'verify') {
                // 1. Update report status to Verified
                $update = $pdo->prepare("UPDATE reports SET status = 'Verified' WHERE id = ?");
                $update->execute([$reportId]);

                // 2. Increase user reputation score (+5) up to max 100
                $updateUser = $pdo->prepare("UPDATE users SET reputation_score = LEAST(reputation_score + 5, 100) WHERE phone_number = ?");
                $updateUser->execute([$reporterPhone]);

                // 3. Trigger Africa's Talking Airtime API Request 
                $atUsername = "sandbox"; 
                $atApiKey   = "atsk_2f3d20419150e49b472da1359af8fa31fa5e80f875987517e5f6065bb724eba991a663e5"; 
                $airtimeUrl = "https://api.sandbox.africastalking.com/version1/airtime/send";
                
                $recipients = [["phoneNumber" => $reporterPhone, "amount" => "NGN 100.00"]];
                $postData   = ["username" => $atUsername, "recipients" => json_encode($recipients)];

                $ch = curl_init($airtimeUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json", "apiKey: " . $atApiKey]);
                $apiResponse = curl_exec($ch);
                curl_close($ch);

                // ==========================================
                // 🚀 NEW: AFRICA'S TALKING SMS API INTEGRATION
                // ==========================================
                $smsUrl = "https://api.sandbox.africastalking.com/version1/messaging";
                $smsMessage = "Thank you for your report to EcoVanguard! Your input has been verified by the forest guards. An airtime reward of NGN 100.00 has been sent to your phone. Let's keep protecting our ecosystem together!";
                
                $smsData = [
                    "username" => $atUsername,
                    "to"       => $reporterPhone,
                    "message"  => $smsMessage
                ];

                $chSms = curl_init($smsUrl);
                curl_setopt($chSms, CURLOPT_POST, true);
                curl_setopt($chSms, CURLOPT_POSTFIELDS, http_build_query($smsData));
                curl_setopt($chSms, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chSms, CURLOPT_HTTPHEADER, ["Accept: application/json", "apiKey: " . $atApiKey]);
                $smsResponse = curl_exec($chSms);
                curl_close($chSms);
                // ==========================================

                $message = "<div class='p-4 mb-4 text-sm text-green-800 bg-green-100 rounded-lg'>Report #$reportId Verified! NGN 100 Airtime Dispatched & Appreciation SMS Sent to $reporterPhone.</div>";

            } elseif ($action === 'fake') {
                // 1. Update report status to Fake
                $update = $pdo->prepare("UPDATE reports SET status = 'Fake' WHERE id = ?");
                $update->execute([$reportId]);

                // 2. Heavily penalize User Reputation Score (-20) down to min 0
                $updateUser = $pdo->prepare("UPDATE users SET reputation_score = GREATEST(reputation_score - 20, 0) WHERE phone_number = ?");
                $updateUser->execute([$reporterPhone]);

                $message = "<div class='p-4 mb-4 text-sm text-red-800 bg-red-100 rounded-lg'>Report #$reportId flagged as FAKE. Reporter penalized -20 points.</div>";
            }
        }
    } catch (PDOException $e) {
        $message = "<div class='p-4 mb-4 text-sm text-yellow-800 bg-yellow-100 rounded-lg'>Error processing action: " . $e->getMessage() . "</div>";
    }
}

// Fetch general metrics
$totalReports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'")->fetchColumn();
$verifiedCount = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Verified'")->fetchColumn();
$averageScore = $pdo->query("SELECT AVG(reputation_score) FROM users")->fetchColumn() ?? 0;

// Fetch all reports to loop through our main table UI
$reportsStmt = $pdo->query("SELECT * FROM reports ORDER BY id DESC");
$allReports  = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoVanguard Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-glow {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
        }
        .pulse-critical { animation: pulse-glow 1.5s infinite ease-in-out; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">

    <!-- Top Navigation Bar -->
    <nav class="bg-emerald-800 text-white p-4 shadow-md">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold tracking-wide">🛡️ EcoVanguard Admin Dashboard</h1>
            <div class="flex items-center space-x-3">
                
                <!-- 🚨 RED CRITICAL ALERT BADGE -->
                <button id="navAlertBadge" onclick="clearAndRefreshAlerts()" class="hidden pulse-critical bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase px-3 py-1.5 rounded-lg flex items-center space-x-1.5 transition duration-150 cursor-pointer shadow-lg border border-red-500">
                    <span>🚨</span>
                    <span>NEW INPUT DETECTED</span>
                </button>

                <span class="bg-emerald-700 px-3 py-1 rounded text-sm font-medium">Kaduna Region Node</span>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Status Messages Alert -->
        <?php echo $message; ?>

        <!-- Analytical Cards Grid Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Incoming Inputs</p>
                <h3 id="metricTotal" class="text-3xl font-bold text-gray-800 mt-1"><?php echo $totalReports; ?></h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 border-l-4 border-l-amber-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Awaiting Verification</p>
                <h3 id="metricPending" class="text-3xl font-bold text-amber-600 mt-1"><?php echo $pendingCount; ?></h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 border-l-4 border-l-green-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Verified Logs</p>
                <h3 id="metricVerified" class="text-3xl font-bold text-green-600 mt-1"><?php echo $verifiedCount; ?></h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 border-l-4 border-l-blue-500">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Avg Network Reputation</p>
                <h3 id="metricReputation" class="text-3xl font-bold text-blue-600 mt-1"><?php echo round($averageScore, 1); ?>/100</h3>
            </div>
        </div>

        <!-- Main Incidents Management Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">Live Field Reports Tracking</h2>
                <span class="text-xs text-gray-500 font-mono">Auto-Updating Stream</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-xs tracking-wider">
                            <th class="p-4">ID</th>
                            <th class="p-4">Phone Number</th>
                            <th class="p-4">Incident Category</th>
                            <th class="p-4">Geographic Location</th>
                            <th class="p-4">Logged Time</th>
                            <th class="p-4">Current Status</th>
                            <th class="p-4 text-center">Operational Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reportsTableBody" class="divide-y divide-gray-100 text-sm">
                        <?php if (count($allReports) === 0): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-400">No field logs found inside the system database yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allReports as $row): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-gray-500">#<?php echo $row['id']; ?></td>
                                    <td class="p-4 font-medium"><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium 
                                            <?php 
                                                echo $row['incident_type'] === 'Poaching' ? 'bg-red-50 text-red-700 border border-red-100' : 
                                                    ($row['incident_type'] === 'Wildlife Movement' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-blue-50 text-blue-700 border border-blue-100');
                                            ?>">
                                            <?php echo htmlspecialchars($row['incident_type']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600 capitalize"><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td class="p-4 text-xs text-gray-400"><?php echo $row['created_at']; ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider
                                            <?php 
                                                echo $row['status'] === 'Pending' ? 'bg-amber-100 text-amber-800' : 
                                                    ($row['status'] === 'Verified' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700');
                                            ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <div class="flex justify-center space-x-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="verify">
                                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded transition shadow-sm">
                                                        Verify & Pay
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="fake">
                                                    <button type="submit" class="bg-white hover:bg-gray-100 text-red-600 border border-red-200 text-xs font-semibold px-3 py-1.5 rounded transition">
                                                        Mark Fake
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-xs text-gray-400 italic font-medium">Session Closed</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Eco-Tourism Guild Matching Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-8">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Kilimanjaro Track — Offline Guide Allocations</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-xs tracking-wider">
                            <th class="p-4">Ticket</th>
                            <th class="p-4">Tourist Wallet</th>
                            <th class="p-4">Allocated Local Guide</th>
                            <th class="p-4">Guide Merit Score</th>
                            <th class="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsTableBody" class="divide-y divide-gray-100 text-sm">
                        <?php
                        try {
                            $bookingsStmt = $pdo->query("SELECT b.*, g.name, g.reputation_score FROM bookings b JOIN guides g ON b.guide_id = g.id ORDER BY b.id DESC");
                            $allBookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $allBookings = [];
                        }
                        
                        if (count($allBookings) === 0): ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-400">No eco-tourism transactions initiated yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allBookings as $booking): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-4 font-mono font-bold text-emerald-700"><?php echo htmlspecialchars($booking['ticket_token']); ?></td>
                                    <td class="p-4"><?php echo htmlspecialchars($booking['tourist_phone']); ?></td>
                                    <td class="p-4 font-medium"><?php echo htmlspecialchars($booking['name']); ?></td>
                                    <td class="p-4">
                                        <div class="w-full bg-gray-200 rounded-full h-2 max-w-[100px] inline-block mr-2">
                                            <div class="bg-emerald-600 h-2 rounded-full" style="width: <?php echo $booking['reputation_score']; ?>%"></div>
                                        </div>
                                        <span class="font-bold text-xs"><?php echo $booking['reputation_score']; ?>/100</span>
                                    </td>
                                    <td class="p-4"><span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs font-bold uppercase"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Real-Time Background Interceptor Engine -->
    <script>
        const navAlertBadge = document.getElementById('navAlertBadge');

        function triggerSystemVisualAlarm() {
            navAlertBadge.classList.remove('hidden');
        }

        function clearAndRefreshAlerts() {
            fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    document.getElementById('reportsTableBody').innerHTML = doc.getElementById('reportsTableBody').innerHTML;
                    document.getElementById('bookingsTableBody').innerHTML = doc.getElementById('bookingsTableBody').innerHTML;
                    
                    document.getElementById('metricTotal').innerText = doc.getElementById('metricTotal').innerText;
                    document.getElementById('metricPending').innerText = doc.getElementById('metricPending').innerText;
                    document.getElementById('metricVerified').innerText = doc.getElementById('metricVerified').innerText;
                    document.getElementById('metricReputation').innerText = doc.getElementById('metricReputation').innerText;
                    
                    navAlertBadge.classList.add('hidden');
                })
                .catch(err => console.error('Error refreshing operational stream blocks:', err));
        }

        // Poll checker_new.php every 4 seconds for immediate incoming updates
        setInterval(() => {
            fetch('checker_new.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.trigger_alert === true) {
                        triggerSystemVisualAlarm();
                    }
                })
                .catch(err => console.error('Stream verification intercept fault:', err));
        }, 4000);
    </script>
</body>
</html>