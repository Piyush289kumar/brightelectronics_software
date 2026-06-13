<div wire:ignore class="space-y-3">
    <div id="complain-map" style="height:400px;border-radius:10px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    (function() {

        function initMap() {

            const mapElement = document.getElementById('complain-map');

            if (!mapElement || mapElement.dataset.initialized === '1') {
                return;
            }

            mapElement.dataset.initialized = '1';

            // Wait until Filament has populated the form
            const latitude = parseFloat(
                document.getElementById('data.latitude')?.value || ''
            );

            const longitude = parseFloat(
                document.getElementById('data.longitude')?.value || ''
            );

            const defaultLat = 23.1832696;
            const defaultLng = 79.9393147;

            const startLat = !isNaN(latitude) && latitude !== 0 ?
                latitude :
                defaultLat;

            const startLng = !isNaN(longitude) && longitude !== 0 ?
                longitude :
                defaultLng;

            const map = L.map(mapElement).setView(
                [startLat, startLng],
                17
            );

            L.tileLayer(
                'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                }
            ).addTo(map);

            let marker = null;

            // Show existing marker on edit page
            if (!isNaN(latitude) &&
                !isNaN(longitude) &&
                latitude !== 0 &&
                longitude !== 0) {

                marker = L.marker([latitude, longitude]).addTo(map);
            }

            function updateFields(lat, lng) {

                const url = `https://www.google.com/maps?q=${lat},${lng}`;

                const mapInput = document.getElementById('data.google_map_location');
                const latInput = document.getElementById('data.latitude');
                const lngInput = document.getElementById('data.longitude');

                if (mapInput) {
                    mapInput.value = url;
                    mapInput.dispatchEvent(
                        new Event('input', {
                            bubbles: true
                        })
                    );
                }

                if (latInput) {
                    latInput.value = lat;
                    latInput.dispatchEvent(
                        new Event('input', {
                            bubbles: true
                        })
                    );
                }

                if (lngInput) {
                    lngInput.value = lng;
                    lngInput.dispatchEvent(
                        new Event('input', {
                            bubbles: true
                        })
                    );
                }
            }

            map.on('click', function(e) {

                const lat = e.latlng.lat;
                const lng = e.latlng.lng;

                updateFields(lat, lng);

                if (marker) {
                    map.removeLayer(marker);
                }

                marker = L.marker([lat, lng]).addTo(map);
            });

            window.addEventListener('complain-map-updated', function(event) {

                const {
                    lat,
                    lng
                } = event.detail;

                map.setView([lat, lng], 17);

                updateFields(lat, lng);

                if (marker) {
                    map.removeLayer(marker);
                }

                marker = L.marker([lat, lng]).addTo(map);
            });

            setTimeout(() => {
                map.invalidateSize();
            }, 300);
        }

        // Wait for Filament/Livewire hydration
        document.addEventListener('livewire:init', () => {
            setTimeout(initMap, 1000);
        });

        // Fallback
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initMap, 1000);
        });

    })();
</script>
