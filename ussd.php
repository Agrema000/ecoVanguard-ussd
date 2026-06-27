<?php
header('Content-type: text/plain');

// 1. Include our database connection
require_once 'db.php';

$sessionId   = $_POST["sessionId"]   ?? "";
$serviceCode = $_POST["serviceCode"] ?? "";
$phoneNumber = $_POST["phoneNumber"] ?? "";
$text        = $_POST["text"]        ?? "";

// Helper Logic: Auto-register the user with a default score of 50 if they don't exist yet!
$stmt = $pdo->prepare("INSERT IGNORE INTO users (phone_number, reputation_score) VALUES (?, 50)");
$stmt->execute([$phoneNumber]);

// Break down the USSD string into steps to easily handle nested flows Dynamically
$parts = explode("*", $text);
$firstOption = $parts[0] ?? "";
$stepCount = count($parts);

if ($text == "") {
    // Main Menu
    $response  = "CON Welcome to EcoVanguard\n";
    $response .= "1. Report Wildlife Incident\n";
    $response .= "2. Eco-Tourism & Guides\n";
    $response .= "3. Check My Reputation Score";
} 
else if ($firstOption == "1") {
    // ========================================================
    // FLOW 1: REPORT WILDLIFE INCIDENT (DYNAMIC STEP TRACKING)
    // ========================================================
    
    if ($stepCount == 1) {
        // Level 1: Choose incident
        $response  = "CON Select Incident Type:\n";
        $response .= "1. Poaching / Suspicious Activity\n";
        $response .= "2. Wildlife Movement (Crop Threat)\n";
        $response .= "3. Illegal Logging";
    } 
    else if ($stepCount == 2) {
        // Level 2: Get Location
        $response  = "CON Enter nearest landmark or village name:";
    } 
    else if ($stepCount == 3) {
        // Level 3: Process and Save Report to Database
        $incidentOption = $parts[1]; // 1, 2, or 3
        $location = $parts[2];       // Text user typed for location
        
        // Map the incident numbers to actual words
        $incidentMapping = ["1" => "Poaching", "2" => "Wildlife Movement", "3" => "Illegal Logging"];
        $incidentType = $incidentMapping[$incidentOption] ?? "Unknown Incident";

        // SAVE INTO MYSQL DATABASE
        $stmt = $pdo->prepare("INSERT INTO reports (phone_number, incident_type, location) VALUES (?, ?, ?)");
        $stmt->execute([$phoneNumber, $incidentType, $location]);

        $response = "END Thank you! Your report on [" . $incidentType . "] at " . $location . " has been securely logged.\nOur team will verify it to update your score.";
    } 
    else {
        $response = "END Invalid option. Please try again.";
    }
} 
else if ($firstOption == "2") {
    // ========================================================
    // FLOW 2: ECO-TOURISM & GUIDES (DYNAMIC STEP TRACKING)
    // ========================================================
    
    if ($stepCount == 1) {
        // Level 1: Eco-Tourism Main Option
        $response  = "CON Kilimanjaro Track Marketplace:\n";
        $response .= "1. Book Highest-Rated Local Guide\n";
        $response .= "2. Cancel Active Booking";
    } 
    else if ($parts[1] == "1") {
        // Nested Route 1: Booking a Guide
        if ($stepCount == 2) {
            // Level 2: Select Wildlife Sector in Kaduna Region
            $response  = "CON Select Kaduna Wildlife Sector:\n";
            $response .= "1. Kamuku National Park\n";
            $response .= "2. Kajuru Castle & Hills\n";
            $response .= "3. Matsirga Waterfalls Route\n";
            $response .= "4. Buruku Sector";
        } 
        else if ($stepCount == 3) {
            // Level 3: Process Sector Selection and Allocate Guide
            $sectorOption = $parts[2];
            
            // Map options to your database strings inside the assigned_region column
            $sectorMapping = [
                "1" => "Kamuku National Park",
                "2" => "Kajuru Castle",
                "3" => "Matsirga Waterfalls",
                "4" => "Buruku"
            ];
            
            $selectedSector = $sectorMapping[$sectorOption] ?? "";

            if (!empty($selectedSector)) {
                // Query the database matching the user's specific sector selection
                $stmt = $pdo->prepare("SELECT * FROM guides WHERE assigned_region = ? ORDER BY reputation_score DESC LIMIT 1");
                $stmt->execute([$selectedSector]);
                $bestGuide = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bestGuide) {
                    // Generate an offline cryptographic ticket token
                    $ticketToken = "EV-" . rand(100, 999) . "-" . strtoupper(substr(md5(time()), 0, 3));
                    
                    // Save the booking into the database
                    $insertBooking = $pdo->prepare("INSERT INTO bookings (tourist_phone, guide_id, ticket_token) VALUES (?, ?, ?)");
                    $insertBooking->execute([$phoneNumber, $bestGuide['id'], $ticketToken]);

                    // Send confirmation text via USSD
                    $response  = "END Booking Confirmed!\n";
                    $response .= "Sector: " . $selectedSector . "\n";
                    $response .= "Guide Assigned: " . $bestGuide['name'] . " (Trust Rating: " . $bestGuide['reputation_score'] . "/100)\n";
                    $response .= "Your Offline Ticket Pass: " . $ticketToken . "\n";
                    $response .= "Present this pass code to your guide when the tour begins.";
                } else {
                    $response = "END Sorry, no certified eco-guides are currently active in the [" . $selectedSector . "] sector.";
                }
            } else {
                $response = "END Invalid sector option choice. Please try again.";
            }
        }
    } 
    else if ($parts[1] == "2") {
        // Nested Route 2: Cancel Active Booking
        $response = "END Under construction. Your active token cancellations can be processed by contacting support.";
    } 
    else {
        $response = "END Invalid option. Please try again.";
    }
} 
else if ($text == "3") {
    // DYNAMIC DATABASE LOOKUP FOR REPUTATION SCORE
    $stmt = $pdo->prepare("SELECT reputation_score FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $currentScore = $user['reputation_score'] ?? 50; 
    
    // Determine tier based on dynamic score
    $tier = "Standard Contributor";
    if ($currentScore >= 80) $tier = "Trusted Informant 🌟";
    if ($currentScore < 40) $tier = "Restricted Account ⚠️";

    $response  = "END Your EcoVanguard Status:\n";
    $response .= "Reputation Score: " . $currentScore . "/100\n";
    $response .= "Tier: " . $tier . "\n";
    $response .= "Perks: Reports from high-tier scorers receive fastest validation.";
} 
else {
    $response = "END Invalid option. Please try again.";
}

echo $response;
?>