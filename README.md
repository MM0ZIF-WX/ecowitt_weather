Ecowitt Weather Dashboard

A WordPress plugin that displays a 7-day weather dashboard using data from Ecowitt weather stations. Visualizes temperature, humidity, solar radiation, UV index, rain rate, wind speed, pressure, soil moisture, and wind direction through interactive charts.

Features
  Interactive line charts for 7-day weather metrics using Chart.js.
  Polar wind rose chart for wind direction using Highcharts.
  Hourly data aggregation via Ecowitt API.
  6-hour API response caching for performance.
  Responsive design with customizable CSS.

Easy integration with [ecowitt_dashboard] shortcode.

Requirements

WordPress 5.0 or higher
PHP 7.4 or higher
Ecowitt API credentials (App Key, API Key, Device MAC)
Internet connection for API and CDN (Chart.js, Highcharts)

Installation
Clone or download this repository.
Upload the plugin folder to /wp-content/plugins/.
Activate the plugin via the WordPress admin panel.
Configure Ecowitt API credentials (App Key, API Key, MAC) in the plugin settings.
Add the [ecowitt_dashboard] shortcode to a page or post.

Usage

Ensure API credentials are set in WordPress settings.
Place the [ecowitt_dashboard] shortcode on any page or post to display the dashboard.
The plugin loads Chart.js and Highcharts via CDN for chart rendering.
Customize styles by editing ecowitt.css if needed.

Configuration

API Credentials: Set App Key, API Key, and Device MAC in the WordPress admin under plugin settings.
Caching: Data is cached for 6 hours to reduce API calls.
Units: Configured for °C, %, mm, mph, inHg, W/m², and UVI (modifiable in ecowitt-weather-dashboard.php).

Contributing
Contributions are welcome! To contribute:
Fork the repository.
Create a feature branch (git checkout -b feature/your-feature).
Commit changes (git commit -m 'Add your feature').
Push to the branch (git push origin feature/your-feature).
Open a pull request.

Please report bugs or suggest features via GitHub Issues.

License

This plugin is licensed under the GPL-3.0 License.

Credits
Developed by Marcus Hazel-McGown, MM0ZIF. https://mm0zif.radio/ Uses Chart.js and Highcharts for data visualization.
