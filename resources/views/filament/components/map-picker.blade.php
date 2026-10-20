<style>
    #complain-map {
        position: relative;
        z-index: 0 !important;
    }

    #complain-map .leaflet-pane,
    #complain-map .leaflet-control {
        z-index: 1 !important;
    }

    .fi-dropdown-panel,
    .fi-select-dropdown,
    [data-radix-popper-content-wrapper] {
        z-index: 9999 !important;
    }

    .map-edit-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .map-edit-button {
        padding: 9px 16px;
        border-radius: 8px;
        background: #2563eb;
        color: white;
        font-weight: 600;
        cursor: pointer;
        border: none;
    }

    .map-edit-button:disabled {
        opacity: .6;
        cursor: not-allowed;
    }

    .map-edit-status {
        font-size: 13px;
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">

<div wire:ignore class="space-y-3">
    <div class="map-edit-toolbar">
        <button
            type="button"
            id="enable-map-edit"
            class="map-edit-button">
            Enable Location Editing
        </button>

        <span id="map-edit-status" class="map-edit-status">
            Location editing is locked.
        </span>
    </div>

    <x-filament::modal id="confirm-location-edit" width="md">
        <x-slot name="heading">
            Enable Location Editing?
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            You are about to change the customer's saved location.
            Latitude, longitude, and Google Map Location will be updated
            when you select a point or drag the marker.
        </p>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-filament::button
                    color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-location-edit' })">
                    Cancel
                </x-filament::button>

                <x-filament::button
                    id="confirm-enable-map-edit"
                    color="primary">
                    Yes, Enable Editing
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <div id="complain-map" style="height:400px;border-radius:10px;"></div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    (function() {
        function initMap() {
            const mapElement = document.getElementById('complain-map');
            const editButton = document.getElementById('enable-map-edit');
            const status = document.getElementById('map-edit-status');

            const confirmEditButton = document.getElementById(
                'confirm-enable-map-edit'
            );

            const locationInputs = [
                document.querySelector('[data-location-input="latitude"]'),
                document.querySelector('[data-location-input="longitude"]'),
                document.querySelector('[data-location-input="google_map_location"]'),
            ].filter(Boolean);


            function setInputsLocked(locked) {
                locationInputs.forEach((input) => {
                    input.readOnly = locked;

                    input.classList.toggle('opacity-60', locked);
                    input.classList.toggle('cursor-not-allowed', locked);

                    input.setAttribute(
                        'aria-readonly',
                        locked ? 'true' : 'false'
                    );
                });
            }

            if (!mapElement || !editButton || !status ||
                mapElement.dataset.initialized === '1' ||
                typeof L === 'undefined') {
                return;
            }

            mapElement.dataset.initialized = '1';

            const latInput = document.getElementById('data.latitude');
            const lngInput = document.getElementById('data.longitude');

            const latitude = parseFloat(latInput?.value || '');
            const longitude = parseFloat(lngInput?.value || '');

            const validLocation =
                Number.isFinite(latitude) &&
                Number.isFinite(longitude) &&
                latitude !== 0 &&
                longitude !== 0;

            const defaultLat = 23.1832696;
            const defaultLng = 79.9393147;

            const map = L.map(mapElement, {
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false,
                boxZoom: false,
                keyboard: false,
            }).setView(
                validLocation ? [latitude, longitude] : [defaultLat, defaultLng],
                17
            );

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            let marker = null;
            let editingEnabled = false;

            if (validLocation) {
                marker = L.marker([latitude, longitude], {
                    draggable: false,
                }).addTo(map);
            }

            function setEditing(enabled) {
                editingEnabled = enabled;

                map.dragging[enabled ? 'enable' : 'disable']();
                map.scrollWheelZoom[enabled ? 'enable' : 'disable']();
                map.doubleClickZoom[enabled ? 'enable' : 'disable']();
                map.touchZoom[enabled ? 'enable' : 'disable']();
                map.boxZoom[enabled ? 'enable' : 'disable']();
                map.keyboard[enabled ? 'enable' : 'disable']();

                if (marker) {
                    if (enabled) {
                        marker.dragging.enable();
                    } else {
                        marker.dragging.disable();
                    }
                }

                setInputsLocked(!enabled);

                editButton.textContent = enabled ?
                    'Lock Location Editing' :
                    'Enable Location Editing';

                status.textContent = enabled ?
                    'Editing enabled. Select the map or drag the marker.' :
                    'Location editing is locked.';
            }

            function updateFields(lat, lng) {
                if (!editingEnabled) return;

                const roundedLat = Number(lat.toFixed(7));
                const roundedLng = Number(lng.toFixed(7));
                const url = `https://www.google.com/maps?q=${roundedLat},${roundedLng}`;

                if (latInput) {
                    latInput.value = roundedLat;
                    latInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    latInput.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                if (lngInput) {
                    lngInput.value = roundedLng;
                    lngInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    lngInput.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                const mapInput = document.getElementById('data.google_map_location');

                if (mapInput) {
                    mapInput.value = url;
                    mapInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    mapInput.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }

            function handleMarkerDrag() {
                if (!marker) return;

                marker.off('dragend');

                marker.on('dragend', function(event) {
                    if (!editingEnabled) return;

                    const position = event.target.getLatLng();
                    updateFields(position.lat, position.lng);
                });
            }

            map.on('click', function(event) {
                if (!editingEnabled) return;

                const lat = event.latlng.lat;
                const lng = event.latlng.lng;

                if (!marker) {
                    marker = L.marker([lat, lng], {
                        draggable: true,
                    }).addTo(map);
                } else {
                    marker.setLatLng([lat, lng]);
                }

                handleMarkerDrag();
                updateFields(lat, lng);
            });

            editButton.addEventListener('click', function() {
                if (editingEnabled) {
                    setEditing(false);
                    return;
                }

                // Open Filament's confirmation modal.
                window.dispatchEvent(
                    new CustomEvent('open-modal', {
                        detail: {
                            id: 'confirm-location-edit'
                        },
                    })
                );
            });

            confirmEditButton?.addEventListener('click', function() {
                setEditing(true);

                window.dispatchEvent(
                    new CustomEvent('close-modal', {
                        detail: {
                            id: 'confirm-location-edit'
                        },
                    })
                );
            });

            handleMarkerDrag();
            setEditing(false);

            window.addEventListener('complain-map-updated', function(event) {
                const {
                    lat,
                    lng
                } = event.detail || {};

                if (!Number.isFinite(Number(lat)) ||
                    !Number.isFinite(Number(lng))) {
                    return;
                }

                map.setView([Number(lat), Number(lng)], 17);

                if (marker) {
                    marker.setLatLng([Number(lat), Number(lng)]);
                } else {
                    marker = L.marker([Number(lat), Number(lng)], {
                        draggable: false,
                    }).addTo(map);
                }

                if (!editingEnabled) {
                    marker.dragging.disable();
                }

                handleMarkerDrag();
            });

            setTimeout(() => map.invalidateSize(), 300);
        }

        document.addEventListener('livewire:init', () => {
            setTimeout(initMap, 1000);
        });

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initMap, 1000);
        });

        // Also retry if the map view is rendered after Livewire navigation.
        document.addEventListener('livewire:navigated', () => {
            setTimeout(initMap, 500);
        });
    })();
</script>