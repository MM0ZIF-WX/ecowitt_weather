<?php
/**
 * Plugin Name:       Tide Times
 * Description:       Displays tide information from Stormglass API via a shortcode and caches data daily.
 * Version:           1.0.0
 * Author:            [Your Name/Alias, or derived from original plugin]
 * License:           GPLv2 or later
 * Text Domain:       tide-times
 */

if (!defined('ABSPATH')) exit;

class Tide_Times_Plugin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_admin_menu() {
        add_options_page(
            'Tide Times Settings',
            'Tide Times',
            'manage_options',
            'tide-times-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('tide_times_settings_group', 'tide_times_stormglass_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('tide_times_settings_group', 'tide_times_latitude', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('tide_times_settings_group', 'tide_times_longitude', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Tide Times Settings</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('tide_times_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tide_times_stormglass_api_key">Stormglass API Key</label></th>
                        <td>
                            <input type="text" id="tide_times_stormglass_api_key" name="tide_times_stormglass_api_key" value="<?php echo esc_attr(get_option('tide_times_stormglass_api_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tide_times_latitude">Latitude</label></th>
                        <td>
                            <input type="text" id="tide_times_latitude" name="tide_times_latitude" value="<?php echo esc_attr(get_option('tide_times_latitude')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tide_times_longitude">Longitude</label></th>
                        <td>
                            <input type="text" id="tide_times_longitude" name="tide_times_longitude" value="<?php echo esc_attr(get_option('tide_times_longitude')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function register_shortcodes() {
        add_shortcode('tide_times_display', [$this, 'tide_shortcode']);
    }

    public function tide_shortcode($atts) {
        $api_key = get_option('tide_times_stormglass_api_key');
        $lat = get_option('tide_times_latitude');
        $lon = get_option('tide_times_longitude');

        $output = '<div class="tide-times-container">';

        if ($api_key && $lat && $lon) {
            $tide_data = $this->get_cached_tide_data($lat, $lon, $api_key);

            if ($tide_data === false) {
                $output .= $this->format_error('Error fetching tide data. Please check logs.');
            } elseif (isset($tide_data['errors'])) {
                 $output .= $this->format_error('API Error: ' . esc_html(implode(', ', $tide_data['errors'])));
            } elseif (!empty($tide_data['data'])) {
                $output .= $this->format_tide_table($tide_data);
            } else {
                $output .= $this->format_warning('No tide data available.');
            }
        } else {
            $output .= $this->format_warning('Tide settings are not configured.');
        }

        $output .= '</div>';
        return $output;
    }

    public function enqueue_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'tide_times_display')) {
            wp_enqueue_style('tide-times-css', plugins_url('tide-times.css', __FILE__), [], '1.0.0');
        }
    }

    private function get_cached_tide_data($lat, $lon, $api_key) {
        $cache_key = 'tide_times_data_' . md5($lat . $lon);
        $data = get_transient($cache_key);

        if (false === $data) {
            $data = $this->fetch_stormglass_tides($lat, $lon, $api_key);
            if ($data !== false) { // Check if fetch was successful before setting transient
                set_transient($cache_key, $data, DAY_IN_SECONDS);
            }
        }
        return $data;
    }

    private function fetch_stormglass_tides($lat, $lon, $api_key) {
        $response = wp_remote_get(
            add_query_arg(['lat' => $lat, 'lng' => $lon, 'params' => 'extremes'], 'https://api.stormglass.io/v2/tide/extremes/point'),
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Stormglass API invalid JSON response: ' . json_last_error_msg() . ' Body: ' . $body);
            return false;
        }

        // Stormglass API returns errors in a specific format e.g. {"errors": {"key": ["API key is invalid"]}}
        if (isset($data['errors'])) {
            error_log('Stormglass API returned errors: ' . print_r($data['errors'], true));
            return ['errors' => $data['errors']]; // Return errors to be displayed
        }

        if (!isset($data['data'])) {
            error_log('Stormglass API response does not contain data key. Body: ' . $body);
            return false;
        }

        return $data;
    }

    private function format_tide_table($data) {
        // Use WordPress site's timezone
        $timezone_string = wp_timezone_string();
        try {
            $site_timezone = new DateTimeZone($timezone_string);
        } catch (Exception $e) {
            // Fallback to UTC if timezone string is invalid
            $site_timezone = new DateTimeZone('UTC');
            error_log('Invalid timezone string: ' . $timezone_string . '. Falling back to UTC.');
        }

        $now = new DateTime('now', $site_timezone);
        // Filter for tides up to 2 days from now.
        // Stormglass free tier provides data for up to 4 days, but 2 days is a reasonable default.
        $two_days_later = (clone $now)->modify('+2 days')->setTime(23, 59, 59);

        $filtered_tides = array_filter($data['data'], function($tide) use ($now, $two_days_later, $site_timezone) {
            try {
                $tide_time = new DateTime($tide['time'], new DateTimeZone('UTC')); // API returns UTC
                $tide_time->setTimezone($site_timezone); // Convert to site timezone
            } catch (Exception $e) {
                error_log('Error parsing tide time: ' . $tide['time'] . ' - ' . $e->getMessage());
                return false;
            }
            // Filter for high and low tides within the next 2 days.
            return ($tide['type'] === 'high' || $tide['type'] === 'low') &&
                   $tide_time >= $now &&
                   $tide_time <= $two_days_later;
        });

        if (empty($filtered_tides)) {
            return '<p>No tide predictions available for the next 48 hours.</p>';
        }

        // Sort tides by time
        usort($filtered_tides, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });

        $html = '<table class="tide-times-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Height (m)</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($filtered_tides as $tide) {
            try {
                $tide_date_time = new DateTime($tide['time'], new DateTimeZone('UTC'));
                $tide_date_time->setTimezone($site_timezone);
                $time_formatted = $tide_date_time->format('D, M j H:i');
            } catch (Exception $e) {
                $time_formatted = 'N/A';
                error_log('Error formatting tide time: ' . $tide['time'] . ' - ' . $e->getMessage());
            }

            $type_formatted = ucfirst(esc_html($tide['type']));

            $html .= sprintf('
                <tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%.2f</td>
                </tr>',
                esc_html($time_formatted),
                $type_formatted,
                floatval($tide['height'])
            );
        }

        $html .= '</tbody></table>';
        return $html;
    }

    // Basic error formatting (can be enhanced)
    private function format_error($message) {
        return '<div class="tide-times-error" style="color: red; padding: 10px; border: 1px solid red;">⚠️ Error: ' . esc_html($message) . '</div>';
    }

    // Basic warning formatting (can be enhanced)
    private function format_warning($message) {
        return '<div class="tide-times-warning" style="color: orange; padding: 10px; border: 1px solid orange;">⚠️ Warning: ' . esc_html($message) . '</div>';
    }
}

new Tide_Times_Plugin();
?>
