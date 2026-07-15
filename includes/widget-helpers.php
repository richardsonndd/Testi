<?php
/**
 * Widget System Helper Functions
 * Provides functions for integrating GridStack.js widget system
 */

/**
 * Enqueue widget-related assets (CSS and JS)
 */
function enqueue_widget_assets() {
    ?>
    <!-- GridStack CSS -->
    <link rel="stylesheet" href="assets/css/gridstack.min.css">
    
    <!-- Station Widgets CSS -->
    <link rel="stylesheet" href="assets/css/station-widgets.css">
    
    <!-- GridStack JS -->
    <script src="assets/js/gridstack.min.js"></script>
    
    <!-- Floorplan Widgets JS -->
    <script src="assets/js/floorplan-widgets.js"></script>
    <?php
}

/**
 * Prepare station widget data for front-end
 * 
 * @param mysqli $conn Database connection
 * @param array $stations Array of station records
 * @param array $settings Site settings
 * @return array Widget data ready for JSON encoding
 */
function prepare_station_widget_data($conn, $stations, $settings) {
    $widgets = [];
    
    foreach ($stations as $station) {
        $isActive = in_array($station['status'], ['BUSY', 'PAUSED'], true);
        $isPaused = $station['status'] === 'PAUSED';
        
        $elapsedMin = 0;
        $stationBudget = 0.0;
        $customerName = '';
        $gameTitle = '';
        
        if ($isActive && $station['session_start']) {
            $nowMs = (int) round(microtime(true) * 1000);
            $startMs = (int) $station['session_start'];
            $pausedMs = isset($station['paused_duration_ms']) ? (int) $station['paused_duration_ms'] : 0;
            
            if ($isPaused && !empty($station['pause_started_at'])) {
                $pausedMs += max(0, $nowMs - (int) $station['pause_started_at']);
            }
            
            $elapsedMin = (int) floor(max(0, $nowMs - $startMs - $pausedMs) / 60000);
            
            // Calculate budget
            $currentRate = isset($station['current_rate_per_hour']) && (float) $station['current_rate_per_hour'] > 0
                ? (float) $station['current_rate_per_hour']
                : station_price_for_player_count($station, 2);
            
            $tileSessionFee = isset($station['current_session_fee']) ? (float) $station['current_session_fee'] : 0.0;
            $snackTotal = station_active_orders_total($conn, (int) $station['id']);
            $stationBudget = ($currentRate * ($elapsedMin / 60)) + $snackTotal + $tileSessionFee;
            
            // Get customer and game info from active session
            $stmt = mysqli_prepare($conn, '
                SELECT customer_name, game_title 
                FROM sessions 
                WHERE station_id = ? AND status = "ACTIVE" 
                ORDER BY created_at DESC 
                LIMIT 1
            ');
            mysqli_stmt_bind_param($stmt, 'i', $station['id']);
            mysqli_stmt_execute($stmt);
            $sessionResult = mysqli_stmt_get_result($stmt);
            if ($sessionRow = mysqli_fetch_assoc($sessionResult)) {
                $customerName = $sessionRow['customer_name'] ?? '';
                $gameTitle = $sessionRow['game_title'] ?? '';
            }
        }
        
        $widgets[] = [
            'id' => 'widget-' . (int) $station['id'],
            'stationId' => (int) $station['id'],
            'name' => $station['name'] ?? 'Station',
            'console' => $station['console_type'] ?? 'Console',
            'status' => $station['status'] ?? 'FREE',
            'posX' => (int) ($station['pos_x'] ?? 0),
            'posY' => (int) ($station['pos_y'] ?? 0),
            'width' => (int) ($station['width'] ?? 250),
            'height' => (int) ($station['height'] ?? 150),
            'isActive' => $isActive,
            'isPaused' => $isPaused,
            'elapsedMin' => $elapsedMin,
            'budget' => round($stationBudget, 2),
            'customerName' => $customerName,
            'gameTitle' => $gameTitle,
            'currencySymbol' => $settings['currency_symbol'] ?? '€',
            'sessionStart' => $station['session_start'] ?? null,
            'pausedDuration' => $station['paused_duration_ms'] ?? 0,
        ];
    }
    
    return $widgets;
}

/**
 * Output widget data as a JavaScript variable
 * 
 * @param array $widgetData Widget data array
 */
function output_station_widget_data($widgetData) {
    ?>
    <script>
        // Widget data passed from PHP
        window.WIDGET_DATA = <?php echo json_encode($widgetData, JSON_THROW_ON_ERROR); ?>;
    </script>
    <?php
}

/**
 * Get active orders total for a station
 * Helper function for budget calculations
 * 
 * @param mysqli $conn Database connection
 * @param int $stationId Station ID
 * @return float Total price of active orders
 */
function station_active_orders_total($conn, $stationId) {
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(price * quantity), 0) AS total FROM active_orders WHERE station_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $stationId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float) ($row['total'] ?? 0);
}

/**
 * Save widget layout to database
 * Called via AJAX from floorplan-widgets.js
 * 
 * @param mysqli $conn Database connection
 * @param int $zoneId Zone ID
 * @param array $layout Layout array
 * @return bool Success
 */
function save_zone_widget_layout($conn, $zoneId, $layout) {
    $layoutJson = json_encode($layout, JSON_THROW_ON_ERROR);
    $stmt = mysqli_prepare($conn, 'UPDATE zones SET widget_layouts = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $layoutJson, $zoneId);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get saved widget layout from database
 * 
 * @param mysqli $conn Database connection
 * @param int $zoneId Zone ID
 * @return array Layout array or empty array
 */
function get_zone_widget_layout($conn, $zoneId) {
    $stmt = mysqli_prepare($conn, 'SELECT widget_layouts FROM zones WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $zoneId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($row && !empty($row['widget_layouts'])) {
        return json_decode($row['widget_layouts'], true) ?? [];
    }
    
    return [];
}
