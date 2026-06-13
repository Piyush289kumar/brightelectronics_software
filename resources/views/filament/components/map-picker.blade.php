<div wire:ignore class="space-y-3">
    <div id="complain-map" style="height:400px;border-radius:10px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapElement = document.getElementById('complain-map');
        if (!mapElement || mapElement.dataset.initialized === '1') {
            return;
        }

        mapElement.dataset.initialized = '1';

        const map = L.map(mapElement).setView([22.7196, 75.8577], 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        let marker = null;

        function updateFields(lat, lng) {
            const url = `https://www.google.com/maps?q=${lat},${lng}`;

            const mapInput = document.querySelector('input[name="data[google_map_location]"]');
            const latInput = document.querySelector('input[name="data[latitude]"]');
            const lngInput = document.querySelector('input[name="data[longitude]"]');

            if (mapInput) {
                mapInput.value = url;
                mapInput.dispatchEvent(new Event('input', { bubbles: true }));
                mapInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (latInput) {
                latInput.value = lat;
                latInput.dispatchEvent(new Event('input', { bubbles: true }));
                latInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (lngInput) {
                lngInput.value = lng;
                lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                lngInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        map.on('click', function (e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            if (marker) {
                map.removeLayer(marker);
            }

            marker = L.marker([lat, lng]).addTo(map);
            updateFields(lat, lng);
        });

        window.addEventListener('complain-map-updated', function (event) {
            const { lat, lng } = event.detail;

            map.setView([lat, lng], 17);

            if (marker) {
                map.removeLayer(marker);
            }

            marker = L.marker([lat, lng]).addTo(map);
        });

        setTimeout(() => map.invalidateSize(), 300);
    });
</script>