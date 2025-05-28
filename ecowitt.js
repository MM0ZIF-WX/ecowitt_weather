document.addEventListener('DOMContentLoaded', function() {
    // Error logging
    function logError(message) {
        console.error('[Ecowitt Dashboard] ' + message);
    }

    // Confirm script version
    console.log('[Ecowitt Dashboard] Loading SVG Wind Rose version 4.2.1');

    // Check if ecowittData is loaded
    if (typeof ecowittData === 'undefined' || !ecowittData.real_time) {
        logError('ecowittData is undefined or missing real_time. Check wp_localize_script in PHP. Data: ' + JSON.stringify(ecowittData));
        return;
    }

    // Wind Rose using SVG
    try {
        const windRoseElement = document.getElementById('windRoseChart');
        if (!windRoseElement) {
            logError('Wind Rose container not found');
            return;
        }

        const windDir = ecowittData.real_time.wind?.wind_direction?.value;
        if (windDir === undefined || isNaN(windDir)) {
            logError('Invalid or missing wind direction data: ' + JSON.stringify(ecowittData.real_time.wind));
            return;
        }

        const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        const index = Math.floor(((windDir % 360) + 11.25) / 22.5) % 16;
        const angleStep = 360 / 16; // 22.5 degrees per segment

        // Create SVG
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '300');
        svg.setAttribute('height', '300');
        svg.setAttribute('viewBox', '-150 -150 300 300');

        // Background circle
        const bgCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        bgCircle.setAttribute('cx', '0');
        bgCircle.setAttribute('cy', '0');
        bgCircle.setAttribute('r', '140');
        bgCircle.setAttribute('fill', '#f8f9fa');
        svg.appendChild(bgCircle);

        // Draw 16 segments
        for (let i = 0; i < 16; i++) {
            const angle = i * angleStep - 90; // -90 to start from North
            const radians = (angle * Math.PI) / 180;
            const isActive = i === index;

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const x1 = 0;
            const y1 = 0;
            const x2 = 140 * Math.cos(radians);
            const y2 = 140 * Math.sin(radians);
            const x3 = 140 * Math.cos(radians + (angleStep * Math.PI) / 180);
            const y3 = 140 * Math.sin(radians + (angleStep * Math.PI) / 180);
            path.setAttribute('d', `M ${x1} ${y1} L ${x2} ${y2} A 140 140 0 0 1 ${x3} ${y3} Z`);
            path.setAttribute('fill', isActive ? '#acc236' : '#d3d3d3');
            path.setAttribute('stroke', '#000');
            path.setAttribute('stroke-width', '1');
            svg.appendChild(path);

            // Label
            const labelAngle = (i * angleStep - 90) * (Math.PI / 180);
            const labelX = 120 * Math.cos(labelAngle);
            const labelY = 120 * Math.sin(labelAngle);
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', labelX);
            text.setAttribute('y', labelY);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dy', '0.35em');
            text.setAttribute('font-size', '12');
            text.textContent = directions[i];
            svg.appendChild(text);
        }

        // Center circle
        const centerCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        centerCircle.setAttribute('cx', '0');
        centerCircle.setAttribute('cy', '0');
        centerCircle.setAttribute('r', '20');
        centerCircle.setAttribute('fill', '#fff');
        centerCircle.setAttribute('stroke', '#000');
        centerCircle.setAttribute('stroke-width', '1');
        svg.appendChild(centerCircle);

        windRoseElement.appendChild(svg);
    } catch (e) {
        logError('Wind Rose SVG error: ' + e.message);
    }

    // Return to Synopsis navigation
    try {
        document.querySelectorAll('.return-synopsis-btn').forEach(button => {
            button.addEventListener('click', () => {
                const synopsisElement = document.getElementById('weather-synopsis');
                if (synopsisElement) {
                    synopsisElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    synopsisElement.classList.add('highlight');
                    setTimeout(() => synopsisElement.classList.remove('highlight'), 2000);
                } else {
                    logError('Synopsis table not found');
                }
            });
        });
    } catch (e) {
        logError('Return to synopsis navigation error: ' + e.message);
    }
});