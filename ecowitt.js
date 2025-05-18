document.addEventListener('DOMContentLoaded', function () {
    // Wind Rose Chart
    const windRoseContainer = document.getElementById('windRoseChart');
    if (windRoseContainer && typeof ecowittData !== 'undefined' && ecowittData.windDirection !== null) {
        Highcharts.chart('windRoseChart', {
            chart: {
                polar: true,
                type: 'column'
            },
            title: {
                text: 'Wind Direction'
            },
            xAxis: {
                categories: ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'],
                tickmarkPlacement: 'on',
                lineWidth: 0
            },
            yAxis: {
                gridLineInterpolation: 'circle',
                lineWidth: 0,
                min: 0,
                labels: {
                    enabled: false
                }
            },
            series: [{
                name: 'Wind Direction',
                data: (function () {
                    const direction = ecowittData.windDirection;
                    const data = [0, 0, 0, 0, 0, 0, 0, 0];
                    const index = Math.round(direction / 45) % 8;
                    data[index] = 1;
                    return data;
                })(),
                pointPlacement: 'on'
            }],
            tooltip: {
                pointFormat: '<b>{point.y}</b>'
            }
        });
    }

    // Chart Buttons
    const chartButtons = document.querySelectorAll('.chart-btn');
    chartButtons.forEach(button => {
        button.addEventListener('click', function () {
            const chartType = this.getAttribute('data-chart');
            const historicalData = ecowittData?.historical;
            if (!historicalData?.length) return;

            let data, title, yAxisTitle, valueSuffix = '';
            switch (chartType) {
                case 'outdoor_temperature':
                    data = historicalData.map(entry => entry.outdoor?.temperature ? parseFloat(entry.outdoor.temperature.value) : null);
                    title = 'Outdoor Temperature (7 Days)';
                    yAxisTitle = 'Temperature (°C)';
                    valueSuffix = ' °C';
                    break;
                case 'outdoor_humidity':
                    data = historicalData.map(entry => entry.outdoor?.humidity ? parseFloat(entry.outdoor.humidity.value) : null);
                    title = 'Outdoor Humidity (7 Days)';
                    yAxisTitle = 'Humidity (%)';
                    valueSuffix = ' %';
                    break;
                case 'rainfall_rain_rate':
                    data = historicalData.map(entry => entry.rainfall?.rain_rate ? parseFloat(entry.rainfall.rain_rate.value) : null);
                    title = 'Rain Rate (7 Days)';
                    yAxisTitle = 'Rain Rate (mm)';
                    valueSuffix = ' mm';
                    break;
                case 'wind_speed':
                    data = historicalData.map(entry => entry.wind?.wind_speed ? parseFloat(entry.wind.wind_speed.value) : null);
                    title = 'Wind Speed (7 Days)';
                    yAxisTitle = 'Wind Speed (km/h)';
                    valueSuffix = ' km/h';
                    break;
                case 'solar_and_uvi_solar':
                    data = historicalData.map(entry => entry.solar_and_uvi?.solar ? parseFloat(entry.solar_and_uvi.solar.value) : null);
                    title = 'Solar Radiation (7 Days)';
                    yAxisTitle = 'Solar (W/m²)';
                    valueSuffix = ' W/m²';
                    break;
                case 'solar_and_uvi_uvi':
                    data = historicalData.map(entry => entry.solar_and_uvi?.uvi ? parseFloat(entry.solar_and_uvi.uvi.value) : null);
                    title = 'UV Index (7 Days)';
                    yAxisTitle = 'UVI';
                    break;
                case 'pressure_absolute':
                    data = historicalData.map(entry => entry.pressure?.absolute ? parseFloat(entry.pressure.absolute.value) : null);
                    title = 'Absolute Pressure (7 Days)';
                    yAxisTitle = 'Pressure (hPa)';
                    valueSuffix = ' hPa';
                    break;
                default:
                    return;
            }

            const times = historicalData.map(entry => entry.time);
            if (windRoseContainer) {
                Highcharts.chart('windRoseChart', {
                    chart: {
                        type: 'line',
                        zoomType: 'x'
                    },
                    title: {
                        text: title
                    },
                    xAxis: {
                        categories: times,
                        title: {
                            text: 'Time'
                        }
                    },
                    yAxis: {
                        title: {
                            text: yAxisTitle
                        }
                    },
                    series: [{
                        name: yAxisTitle,
                        data: data
                    }],
                    tooltip: {
                        valueSuffix: valueSuffix
                    }
                });
            }
        });
    });
});