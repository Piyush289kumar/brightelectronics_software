<div>
    <div id="map" style="height:400px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    document.addEventListener('livewire:navigated', function() {

        if (window.locationMapInitialized) {
            return;
        }

        window.locationMapInitialized = true;

        let map = L.map('map').setView([22.7196, 75.8577], 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        let marker;

        map.on('click', function(e) {

            let lat = e.latlng.lat;
            let lng = e.latlng.lng;

            if (marker) {
                map.removeLayer(marker);
            }

            marker = L.marker([lat, lng]).addTo(map);

            let url = `https://www.google.com/maps?q=${lat},${lng}`;

            console.log(url);

            window.Livewire.find(
                document.querySelector('[wire\\:id]').getAttribute('wire:id')
            ).set('data.google_map_location', url);
        });

    });
</script>
