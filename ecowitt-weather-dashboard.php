<?php
/*
Plugin Name: Ecowitt Weather Dashboard
Description: Displays Ecowitt weather data, a wind rose, and Stormglass tide data via shortcode and provides an admin settings page.
Version: 4.2.1
Author: Marcus Hazel-McGown (MM0ZIF)
*/

if (!defined('ABSPATH')) exit;

class Ecowitt_Weather_Dashboard {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'conditionally_enqueue_assets']);
    }

    public function register_shortcodes() {
        add_shortcode('ecowitt_dashboard', [$this, 'dashboard_shortcode']);
    }

    public function conditionally_enqueue_assets() {
        if (is_singular() && has_shortcode(get_post(get_the_ID())->post_content, 'ecowitt_dashboard')) {
            wp_enqueue_script(
                'ecowitt-js',
                plugins_url('ecowitt.js', __FILE__),
                [],
                '4.2.1',
                true
            );
            wp_enqueue_style(
                'ecowitt-css',
                plugins_url('ecowitt.css', __FILE__),
                [],
                '4.2.1'
            );

            $weather_data = $this->get_cached_weather_data(
                get_option('ecowitt_app_key'),
                get_option('ecowitt_api_key'),
                get_option('ecowitt_mac')
            );

            wp_localize_script('ecowitt-js', 'ecowittData', [
                'real_time' => isset($weather_data['data']) ? $weather_data['data'] : [],
                'unit' => [
                    'temp_unit' => 'c',
                    'rain_unit' => 'mm',
                    'pressure_unit' => 'hpa',
                    'wind_speed_unit' => 'm/s'
                ]
            ]);
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'Ecowitt Weather Settings',
            'Ecowitt Weather',
            'manage_options',
            'ecowitt-weather-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('ecowitt_weather_settings_group', 'ecowitt_app_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('ecowitt_weather_settings_group', 'ecowitt_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('ecowitt_weather_settings_group', 'ecowitt_mac', [
            'sanitize_callback' => function($input) {
                // Remove colons, spaces, or other non-hex characters
                $clean_mac = strtoupper(preg_replace('/[^A-F0-9]/', '', $input));
                if (strlen($clean_mac) !== 12) {
                    add_settings_error(
                        'ecowitt_mac',
                        'invalid_mac',
                        'MAC address must be 12 hexadecimal characters (e.g., A0B1C2D3E4F5 or A0:B1:C2:D3:E4:F5). Got: ' . esc_html($input),
                        'error'
                    );
                    // Return the previous valid MAC to prevent saving an invalid one
                    return get_option('ecowitt_mac');
                }
                return $clean_mac;
            }
        ]);
        register_setting('ecowitt_weather_settings_group', 'ecowitt_lat', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('ecowitt_weather_settings_group', 'ecowitt_lon', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('ecowitt_weather_settings_group', 'stormglass_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Ecowitt Weather & Tide Settings</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('ecowitt_weather_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ecowitt_app_key">Ecowitt APP Key</label></th>
                        <td>
                            <input type="text" id="ecowitt_app_key" name="ecowitt_app_key" value="<?php echo esc_attr(get_option('ecowitt_app_key')); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ecowitt_api_key">Ecowitt API Key</label></th>
                        <td>
                            <input type="text" id="ecowitt_api_key" name="ecowitt_api_key" value="<?php echo esc_attr(get_option('ecowitt_api_key')); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ecowitt_mac">Device MAC Address</label></th>
                        <td>
                            <input type="text" id="ecowitt_mac" name="ecowitt_mac" value="<?php echo esc_attr(get_option('ecowitt_mac')); ?>" class="regular-text" required />
                            <p class="description">Enter with or without colons (e.g., <code>A0B1C2D3E4F5</code> or <code>A0:B1:C2:D3:E4:F5</code>)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ecowitt_lat">Latitude</label></th>
                        <td>
                            <input type="text" id="ecowitt_lat" name="ecowitt_lat" value="<?php echo esc_attr(get_option('ecowitt_lat')); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ecowitt_lon">Longitude</label></th>
                        <td>
                            <input type="text" id="ecowitt_lon" name="ecowitt_lon" value="<?php echo esc_attr(get_option('ecowitt_lon')); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="stormglass_api_key">Stormglass API Key</label></th>
                        <td>
                            <input type="text" id="stormglass_api_key" name="stormglass_api_key" value="<?php echo esc_attr(get_option('stormglass_api_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function dashboard_shortcode($atts) {
        $app_key = get_option('ecowitt_app_key');
        $api_key = get_option('ecowitt_api_key');
        $mac = get_option('ecowitt_mac');
        $lat = get_option('ecowitt_lat');
        $lon = get_option('ecowitt_lon');
        $stormglass_api_key = get_option('stormglass_api_key');

        $output = '<div class="ecowitt-dashboard">';

        // --- Weather Data Section ---
        $output .= '<section class="weather-section">';
        $output .= '<h2>Current Weather Conditions</h2>';

        if ($app_key && $api_key && $mac) {
            $weather_data = $this->get_cached_weather_data($app_key, $api_key, $mac);

            if (isset($weather_data['error'])) {
                $output .= $this->format_error($weather_data['error']);
            } elseif (!empty($weather_data['data'])) {
                $output .= $this->format_weather_data($weather_data);
            } else {
                $output .= $this->format_error('No weather data available');
            }
        } else {
            $output .= $this->format_error('Missing API configuration');
        }

        $output .= '</section>';

        // --- Wind Rose Section ---
        $output .= '<section class="weather-section">';
        $output .= '<h2>Wind Rose</h2>';
        $output .= '<div style="max-width: 500px; margin: 2em auto;"><div id="windRoseChart"></div></div>';
        $output .= '</section>';

        // --- Tide Data Section ---
        $output .= '<section class="tide-section">';
        $output .= '<h2>Tide Predictions</h2>';

        if ($stormglass_api_key && $lat && $lon) {
            $tide_data = $this->get_cached_tide_data($lat, $lon, $stormglass_api_key);

            if ($tide_data && !empty($tide_data['data'])) {
                $output .= $this->format_tide_table($tide_data);
            } else {
                $output .= $this->format_warning('No tide data available');
            }
        } else {
            $output .= $this->format_warning('Missing tide configuration');
        }

        $output .= '</section>';

        $output .= '<div class="mm0zif-donate"><a href="https://www.buymeacoffee.com/mm0zif"><img src="https://img.buymeacoffee.com/button-api/?text=Buy%20me%20a%20coffee&emoji=☕&slug=mm0zif&button_colour=FFDD00&font_colour=000000&font_family=Cookie&outline_colour=000000&coffee_colour=ffffff" /></a></div>';

        return $output;
    }

    private function get_cached_weather_data($app_key, $api_key, $mac) {
        $cache_key = 'ecowitt_weather_' . md5($mac);
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->fetch_ecowitt_data($app_key, $api_key, $mac);
            set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
        }

        return $data;
    }

    private function format_mac_for_api($mac) {
        // Convert stored MAC (e.g., 5C013B393707) to colon-separated format (e.g., 5C:01:3B:39:37:07)
        if (strlen($mac) === 12) {
            return implode(':', str_split($mac, 2));
        }
        return $mac; // Return unchanged if not 12 characters
    }

    private function fetch_ecowitt_data($app_key, $api_key, $mac) {
        $endpoint = 'https://api.ecowitt.net/api/v3/device/real_time';
        $formatted_mac = $this->format_mac_for_api($mac);
        $params = [
            'application_key' => $app_key,
            'api_key' => $api_key,
            'mac' => $formatted_mac,
            'call_back' => 'all',
            'temp_unitid' => 1,
            'pressure_unitid' => 3,
            'wind_speed_unitid' => 7,
            'rainfall_unitid' => 12,
            'solarradiation_unitid' => 10,
            'format' => 'json'
        ];
        $url = add_query_arg($params, $endpoint);
        error_log('Ecowitt API Request: MAC=' . $formatted_mac . ', URL=' . $url);
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Ecowitt API Error: ' . $error_message);
            return ['error' => 'API Connection Error: ' . $error_message];
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        error_log('Ecowitt API Response: Code=' . $response_code . ', Headers=' . print_r($response_headers, true) . ', Body=' . $body);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Ecowitt JSON Error: ' . json_last_error_msg());
            return ['error' => 'Invalid API Response: ' . json_last_error_msg()];
        }
        if (isset($data['code']) && $data['code'] !== 0) {
            return ['error' => 'API Error: ' . ($data['msg'] ?? 'Unknown error')];
        }
        return $data;
    }

    private function format_weather_data($data) {
        $d = $data['data'];
        $extract = function($section, $key) use ($d) {
            return isset($d[$section][$key]['value']) ? $d[$section][$key]['value'] : null;
        };

        $temperature = $extract('outdoor', 'temperature');
        $feels_like = $extract('outdoor', 'feels_like');
        $humidity = $extract('outdoor', 'humidity');
        $dew_point = $extract('outdoor', 'dew_point');
        $wind_speed = $extract('wind', 'wind_speed');
        $wind_gust = $extract('wind', 'wind_gust');
        $wind_dir = $extract('wind', 'wind_direction');
        $rain_rate = $extract('rainfall', 'rain_rate');
        $rain_daily = $extract('rainfall', 'daily');
        $pressure = $extract('pressure', 'absolute');
        $pressure_trend_val = $extract('pressure', 'trend') ?? 0;
        $solar = $extract('solar_and_uvi', 'solar');
        $uvi = $extract('solar_and_uvi', 'uvi');
        $lightning_count = $extract('lightning', 'count');
        $lightning_distance = $extract('lightning', 'distance');
        $soil_moisture_ch1 = $extract('soil_ch1', 'soilmoisture');

        return '
        <div class="weather-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
            ' . $this->weather_card('🌡️ Temperature', $temperature . '°C', 'Feels like ' . $feels_like . '°C') . '
            ' . $this->weather_card('💧 Humidity', $humidity . '%', 'Dew Point ' . $dew_point . '°C') . '
            ' . $this->weather_card('🌬️ Wind', $this->convert_kmh_to_ms($wind_speed) . ' m/s', $this->wind_direction($wind_dir) . ', Gusts ' . $this->convert_kmh_to_ms($wind_gust) . ' m/s') . '
            ' . $this->weather_card('🌧️ Rain', $rain_rate . ' mm/h', $rain_daily . ' mm today') . '
            ' . $this->weather_card('⏲️ Pressure', $pressure . ' hPa', $this->pressure_trend($pressure_trend_val)) . '
            ' . $this->weather_card('☀️ Solar', $solar . ' W/m²', 'UV Index ' . $uvi) . '
            ' . $this->weather_card('⚡ Lightning', $lightning_count . ' strikes', $lightning_distance ? 'Last at ' . $lightning_distance . ' km' : 'No recent strikes') . '
            ' . $this->weather_card('🌱 Soil Moisture (Ch1)', $soil_moisture_ch1 . '%', '') . '
        </div>';
    }

    private function convert_kmh_to_ms($value) {
        return is_numeric($value) ? number_format($value / 3.6, 1) : '0.0';
    }

    private function weather_card($title, $value, $subtext) {
        return '
        <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 1.1em;">' . esc_html($title) . '</h3>
            <div style="font-size: 1.75rem; font-weight: 600; color: #2c3338;">' . esc_html($value) . '</div>
            <div style="color: #50575e; font-size: 0.9em; margin-top: 8px;">' . esc_html($subtext) . '</div>
        </div>';
    }

    private function get_cached_tide_data($lat, $lon, $api_key) {
        $cache_key = 'ecowitt_tides_' . md5($lat . $lon);
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->fetch_stormglass_tides($lat, $lon, $api_key);
            if ($data) {
                set_transient($cache_key, $data, DAY_IN_SECONDS);
            }
        }

        return $data;
    }

    private function fetch_stormglass_tides($lat, $lon, $api_key) {
        $response = wp_remote_get(
            add_query_arg(['lat' => $lat, 'lng' => $lon], 'https://api.stormglass.io/v2/tide/extremes/point'),
            [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 15
            ]
        );

        if (is_wp_error($response)) {
            error_log('Stormglass API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data'])) {
            error_log('Stormglass API invalid response: ' . json_last_error_msg());
            return false;
        }

        return $data;
    }

    private function format_tide_table($data) {
        $now = new DateTime('now', new DateTimeZone('Europe/London'));
        $two_days_later = (clone $now)->modify('+2 days');

        $filtered_tides = array_filter($data['data'], function($tide) use ($now, $two_days_later) {
            $tide_time = new DateTime($tide['time']);
            return $tide['type'] === 'high' && 
                   $tide_time >= $now && 
                   $tide_time <= $two_days_later;
        });

        if (empty($filtered_tides)) {
            return '<p>No high tides in the next 48 hours</p>';
        }

        $html = '<table class="ecowitt-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Height</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($filtered_tides as $tide) {
            $time = (new DateTime($tide['time']))->setTimezone(new DateTimeZone('Europe/London'))->format('D, M j H:i');
            $html .= sprintf('
                <tr>
                    <td>%s</td>
                    <td>%.2f m</td>
                </tr>',
                esc_html($time),
                $tide['height']
            );
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function wind_direction($degrees) {
        $degrees = is_numeric($degrees) ? floatval($degrees) : 0.0;
        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($degrees / 22.5) % 16;
        return $directions[$index >= 0 ? $index : 16 + $index] ?? 'N/A';
    }

    private function pressure_trend($value) {
        if ($value === null || $value === '') return '';
        if ($value < -60) return 'Falling rapidly';
        if ($value < -20) return 'Falling';
        if ($value < 20) return 'Steady';
        return 'Rising';
    }

    private function format_error($message) {
        return '<div class="ecowitt-error">⚠️ ' . esc_html($message) . '</div>';
    }

    private function format_warning($message) {
        return '<div style="color: #dba617; background: #fff8e5; padding: 15px; border-radius: 4px; margin: 20px 0;">
            ⚠️ ' . esc_html($message) . '
        </div>';
    }
}

new Ecowitt_Weather_Dashboard();
?>