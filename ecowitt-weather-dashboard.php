<?php
/*
Plugin Name: Ecowitt Weather Dashboard
Version: 4.00
Description: Displays Ecowitt 7-day weather data.
Author: xAI Grok
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Ecowitt_Weather_Dashboard {
    private $app_key;
    private $api_key;
    private $mac;
    private $transient_duration = HOUR_IN_SECONDS * 6;

    public function __construct() {
        $this->app_key = get_option('ecowitt_app_key', '');
        $this->api_key = get_option('ecowitt_api_key', '');
        $this->mac = get_option('ecowitt_mac', '');
        if (empty($this->app_key) || empty($this->api_key) || empty($this->mac)) {
            return;
        }
        add_shortcode('ecowitt_dashboard', [$this, 'render_dashboard']);
    }

    private function fetch_historical_data() {
        $transient_key = 'ecowitt_historical_' . md5($this->mac);
        $data = get_transient($transient_key);
        if ($data !== false) {
            return $data;
        }

        $end_time = time();
        $start_time = $end_time - (7 * DAY_IN_SECONDS);
        $params = [
            'application_key' => $this->app_key,
            'api_key' => $this->api_key,
            'mac' => $this->mac,
            'start_date' => gmdate('Y-m-d H:i:s', $start_time),
            'end_date' => gmdate('Y-m-d H:i:s', $end_time),
            'call_back' => 'outdoor.temperature,outdoor.humidity,solar_and_uvi.solar,solar_and_uvi.uvi,rainfall.rain_rate,wind.wind_speed,pressure.absolute,soil_ch1.soilmoisture',
            'cycle_type' => '1h',
            'temp_unitid' => 1,
            'humidity_unitid' => 1,
            'solar_unitid' => 1,
            'uvi_unitid' => 1,
            'rainfall_unitid' => 12,
            'wind_speed_unitid' => 7,
            'pressure_unitid' => 3,
            'soilmoisture_unitid' => 1
        ];
        $url = add_query_arg($params, 'https://api.ecowitt.net/api/v3/device/history');
        $response = wp_remote_get($url, ['timeout' => 15]);
        $data = [];

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($body) && isset($body['code']) && $body['code'] === 0) {
                $data = !empty($body['data']) && is_array($body['data']) ? $body['data'] : [];
            }
        }
        set_transient($transient_key, $data, $this->transient_duration);
        return $data;
    }

    public function render_dashboard() {
        $historical_data = $this->fetch_historical_data();
        ob_start();
        ?>
        <div class="ecowitt-dashboard">
            <h2>7-Day Weather Dashboard</h2>
            <?php if (!empty($historical_data) && isset($historical_data['outdoor'])): ?>
                <div class="weather-section">
                    <h3>Temperature (°C)</h3>
                    <canvas id="tempChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Humidity (%)</h3>
                    <canvas id="humidityChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Solar Radiation (W/m²)</h3>
                    <canvas id="solarChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>UV Index</h3>
                    <canvas id="uviChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Rain Rate (mm)</h3>
                    <canvas id="rainChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Wind Speed (mph)</h3>
                    <canvas id="windChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Pressure (inHg)</h3>
                    <canvas id="pressureChart"></canvas>
                </div>
                <div class="weather-section">
                    <h3>Soil Moisture (%)</h3>
                    <canvas id="soilChart"></canvas>
                </div>
            <?php else: ?>
                <p>No data available or API error occurred.</p>
            <?php endif; ?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const charts = [
                    {
                        id: 'tempChart',
                        label: 'Temperature (°C)',
                        data: <?php echo json_encode(array_values($historical_data['outdoor']['temperature']['list'] ?? [])); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        beginAtZero: false
                    },
                    {
                        id: 'humidityChart',
                        label: 'Humidity (%)',
                        data: <?php echo json_encode(array_values($historical_data['outdoor']['humidity']['list'] ?? [])); ?>,
                        borderColor: 'rgb(255, 99, 132)',
                        beginAtZero: true
                    },
                    {
                        id: 'solarChart',
                        label: 'Solar Radiation (W/m²)',
                        data: <?php echo json_encode(array_values($historical_data['solar_and_uvi']['solar']['list'] ?? [])); ?>,
                        borderColor: 'rgb(255, 205, 86)',
                        beginAtZero: true
                    },
                    {
                        id: 'uviChart',
                        label: 'UV Index',
                        data: <?php echo json_encode(array_values($historical_data['solar_and_uvi']['uvi']['list'] ?? [])); ?>,
                        borderColor: 'rgb(54, 162, 235)',
                        beginAtZero: true
                    },
                    {
                        id: 'rainChart',
                        label: 'Rain Rate (mm)',
                        data: <?php echo json_encode(array_values($historical_data['rainfall']['rain_rate']['list'] ?? [])); ?>,
                        borderColor: 'rgb(54, 162, 235)',
                        beginAtZero: true
                    },
                    {
                        id: 'windChart',
                        label: 'Wind Speed (mph)',
                        data: <?php echo json_encode(array_values($historical_data['wind']['wind_speed']['list'] ?? [])); ?>,
                        borderColor: 'rgb 'rgb(153, 102, 255)',
                        beginAtZero: true
                    },
                    {
                        id: 'pressureChart',
                        label: 'Pressure (inHg)',
                        data: <?php echo json_encode(array_values($historical_data['pressure']['absolute']['list'] ?? [])); ?>,
                        borderColor: 'rgb(255, 159, 64)',
                        beginAtZero: false
                    },
                    {
                        id: 'soilChart',
                        label: 'Soil Moisture (%)',
                        data: <?php echo json_encode(array_values($historical_data['soil_ch1']['soilmoisture']['list'] ?? [])); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        beginAtZero: true
                    }
                ];

                charts.forEach(chart => {
                    const ctx = document.getElementById(chart.id).getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_keys($historical_data['outdoor']['temperature']['list'] ?? [])); ?>,
                            datasets: [{
                                label: chart.label,
                                data: chart.data,
                                borderColor: chart.borderColor,
                                tension: 0.1
                            }]
                        },
                        options: { responsive: true, scales: { y: { beginAtZero: chart.beginAtZero } } }
                    });
                });
            });
        </script>
        <style>
            .ecowitt-dashboard { max-width: 100%; margin: 20px auto; padding: 20px; }
            .weather-section { margin-bottom: 20px; }
            canvas { max-width: 100%; }
        </style>
        <?php
        return ob_get_clean();
    }
}

new Ecowitt_Weather_Dashboard();