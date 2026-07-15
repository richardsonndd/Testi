<?php
/**
 * Dashboard Integration for Resizable Widgets
 * This file includes the widget assets in the dashboard
 * 
 * Include this in your dashboard.php header section
 */

// Add widget CSS and JS to dashboard
function enqueue_widget_assets() {
    ?>
    <!-- GridStack Library -->
    <link rel="stylesheet" href="assets/css/gridstack.min.css">
    <link rel="stylesheet" href="assets/css/station-widgets.css">
    <script src="assets/js/gridstack.min.js"></script>
    <script src="assets/js/floorplan-widgets.js"></script>
    <?php
}

/**
 * Convert station data to widget format
 * Call this before rendering stations
 */
function prepare_station_widget_data($conn, $stations, $settings) {
    $widget_data = [];
    
    foreach ($stations as $station) {
        $isActive = in_array($station['status'], ['BUSY', 'PAUSED'], true);
        $isPaused = $station['status'] === 'PAUSED';
        
        $data = [
            'id' => (int) $station['id'],
            'name' => $station['name'],
            'console_type' => $station['console_type'],
            'status' => $station['status'],
            'pos_x' => (int) ($station['pos_x'] ?? 0),
            'pos_y' => (int) ($station['pos_y'] ?? 0),
            'session_start' => $station['session_start'] ?? null,
            'paused_duration_ms' => (int) ($station['paused_duration_ms'] ?? 0),
            'current_rate_per_hour' => (float) ($station['current_rate_per_hour'] ?? 0),
            'current_session_fee' => (float) ($station['current_session_fee'] ?? 0),
            'customer_name' => $station['customer_name'] ?? null,
        ];
        
        if ($isActive) {
            // Calculate elapsed time
            $nowMs = (int) round(microtime(true) * 1000);
            $startMs = (int) ($station['session_start'] ?? 0);
            $pausedMs = (int) ($station['paused_duration_ms'] ?? 0);
            if ($isPaused && !empty($station['pause_started_at'])) {
                $pausedMs += max(0, $nowMs - (int) $station['pause_started_at']);
            }
            $elapsedMin = (int) floor(max(0, $nowMs - $startMs - $pausedMs) / 60000);
            
            // Calculate budget
            $rate = (float) $station['current_rate_per_hour'] ?? 0;
            $ordersTotal = 0;
            
            $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(price * quantity), 0) AS total FROM active_orders WHERE station_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $station['id']);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $ordersTotal = (float) $row['total'];
            
            $sessionFee = (float) ($station['current_session_fee'] ?? 0);
            $budget = ($rate * ($elapsedMin / 60)) + $ordersTotal + $sessionFee;
            
            $data['orders_total'] = $ordersTotal;
            $data['current_budget'] = $budget;
        }
        
        $widget_data[] = $data;
    }
    
    return $widget_data;
}

/**
 * Output station widget data as JSON
 * Add this in your dashboard HTML to make data available to JS
 */
function output_station_widget_data($widget_data) {
    foreach ($widget_data as $data) {
        echo '<script type="application/json" class="station-widget-data">';
        echo json_encode($data);
        echo '</script>';
    }
}
?>