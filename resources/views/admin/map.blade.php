@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin/map'" :tabs="$tabs">
    <div class="card">
        <h2>Student placements</h2>
        <p class="muted" style="font-size:13px;">Approved and finalised trips, mapped by clinical site. Pickup point is always {{ $pickup['name'] }}. Trips already combined into a journey (via the <a href="/admin/journeys">AI Trip Planner</a>) are drawn as a single recommended travel route instead of separate pins.</p>
        <p class="hint">Routes are plotted at street level — actual roads the vehicle would drive, not straight lines — via <a href="https://project-osrm.org" target="_blank" rel="noopener">OSRM</a>, a free public routing service (no API key). If it's briefly unavailable, a thin dashed straight line shows in its place until it responds.</p>
        <form method="GET" action="/admin/map">
            <div class="grid">
                <div class="field">
                    <label>Department</label>
                    <select name="department" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" {{ $filters['department'] === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Date</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" onchange="this.form.submit()">
                </div>
                <div class="field">
                    <label>Shift</label>
                    <select name="shift" onchange="this.form.submit()">
                        <option value="">Day &amp; night</option>
                        <option value="day" {{ $filters['shift'] === 'day' ? 'selected' : '' }}>Day</option>
                        <option value="night" {{ $filters['shift'] === 'night' ? 'selected' : '' }}>Night</option>
                    </select>
                </div>
                <div class="field">
                    <label>Show</label>
                    <select name="view" onchange="this.form.submit()">
                        <option value="all" {{ $filters['view'] === 'all' ? 'selected' : '' }}>Everything</option>
                        <option value="journeys" {{ $filters['view'] === 'journeys' ? 'selected' : '' }}>Combined journey routes only</option>
                        <option value="individual" {{ $filters['view'] === 'individual' ? 'selected' : '' }}>Individual trips only</option>
                    </select>
                </div>
            </div>
            @if ($filters['department'] || $filters['date'] || $filters['shift'] || $filters['view'] !== 'all')
                <a class="btn secondary small" href="/admin/map">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card">
        <div class="grid" style="margin-bottom:16px;">
            <div>
                <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em;">Placements shown</div>
                <div style="font-size:22px;font-weight:700;">{{ $totalPlacements }}</div>
            </div>
            <div>
                <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em;">Individual sites</div>
                <div style="font-size:22px;font-weight:700;">{{ $totalSites }}</div>
            </div>
            <div>
                <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em;">Combined routes</div>
                <div style="font-size:22px;font-weight:700;">{{ $totalJourneys }}</div>
            </div>
        </div>
        <div id="placement-map" style="height:460px;border-radius:10px;overflow:hidden;border:1px solid var(--border);"></div>
    </div>

    @if ($journeys->isNotEmpty())
        <div class="card">
            <h3>Recommended travel routes for combined journeys</h3>
            <p class="hint">Stop order is a nearest-neighbour route from {{ $pickup['name'] }} — not necessarily the mathematically shortest possible route, but a good fast approximation, and matches the route drawn on the map. Leg distances below are straight-line estimates used to plan the order; the map itself shows the actual road route with real driving distance/time once it loads.</p>
            @foreach ($journeys as $j)
                <div style="border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                        <div>
                            <strong>{{ $j['label'] }}</strong>
                            <div class="muted" style="font-size:12px;margin-top:2px;">
                                {{ \Carbon\Carbon::parse($j['date'])->format('d M Y') }} &middot; {{ $j['time'] }} &middot; {{ $j['studentCount'] }} student{{ $j['studentCount'] === 1 ? '' : 's' }} &middot; ~{{ $j['totalKm'] }}km round trip
                            </div>
                        </div>
                        <span class="pill {{ $j['allFinalised'] ? 'finalised' : 'approved' }}">{{ $j['allFinalised'] ? 'finalised' : 'approved' }}</span>
                    </div>
                    <ol style="margin:10px 0 0;padding-left:20px;font-size:13px;">
                        <li class="muted">Depart {{ $pickup['name'] }}</li>
                        @foreach ($j['stops'] as $stop)
                            <li>{{ $stop['name'] }} <span class="muted">({{ $j['legsKm'][$stop['order'] - 1] }}km leg &middot; {{ $stop['count'] }} student{{ $stop['count'] === 1 ? '' : 's' }})</span></li>
                        @endforeach
                        <li class="muted">Return to {{ $pickup['name'] }} <span class="muted">({{ end($j['legsKm']) }}km leg)</span></li>
                    </ol>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <h3>Individual sites in view</h3>
        @if ($markers->isEmpty())
            <div class="empty">No individual (not-yet-combined) placements match these filters.</div>
        @else
            <table>
                <thead><tr><th>Site</th><th>Address</th><th>Students</th><th>Department(s)</th></tr></thead>
                <tbody>
                    @foreach ($markers->sortByDesc('count') as $m)
                        <tr>
                            <td>{{ $m['name'] }}</td>
                            <td class="muted">{{ $m['address'] }}</td>
                            <td>{{ $m['count'] }}</td>
                            <td class="muted">{{ $m['departments']->implode(', ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const pickup = @json($pickup);
        const markers = @json($markers);
        const journeys = @json($journeys);
        const routeColors = ['#993399', '#802b80', '#a44ca4', '#4a1fae', '#7d3f98', '#b44db4'];

        const map = L.map('placement-map').setView([pickup.lat, pickup.lng], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const pickupIcon = L.divIcon({
            className: '',
            html: '<div style="background:#241a2e;color:#fff;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:600;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.3);">' + pickup.name + '</div>',
            iconSize: [0, 0],
        });
        L.marker([pickup.lat, pickup.lng], {icon: pickupIcon}).addTo(map);

        const bounds = [[pickup.lat, pickup.lng]];

        // Street-level route between an ordered list of {lat,lng} points via
        // OSRM's free public routing API (no key required — same provider
        // as the OpenStreetMap tiles above). Follows the given point order
        // exactly (OSRM's /route endpoint doesn't reorder waypoints, unlike
        // its /trip endpoint) rather than re-optimising it, since the order
        // was already decided by the nearest-neighbour planner. Returns
        // null on any failure so callers can fall back to a straight line.
        async function fetchRoadRoute(points) {
            const coords = points.map(p => p.lng + ',' + p.lat).join(';');
            const url = 'https://router.project-osrm.org/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
            try {
                const res = await fetch(url);
                if (!res.ok) return null;
                const data = await res.json();
                if (data.code !== 'Ok' || !data.routes || !data.routes.length) return null;
                const route = data.routes[0];

                return {
                    latlngs: route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
                    distanceKm: Math.round(route.distance / 100) / 10,
                    durationMin: Math.round(route.duration / 60),
                };
            } catch (e) {
                return null;
            }
        }

        // A small sequential queue so we're a polite, considerate client of
        // OSRM's shared public demo server rather than firing every route
        // request at once.
        async function runSequentially(tasks) {
            for (const task of tasks) {
                await task();
                await new Promise(r => setTimeout(r, 300));
            }
        }

        const routeTasks = [];

        // Individual (not-yet-combined) placements: plain circle markers,
        // each linked to pickup by a road route (straight dashed line shown
        // immediately as a fallback while the real route loads).
        markers.forEach(m => {
            const radius = Math.min(10 + m.count * 2, 34);
            const circle = L.circleMarker([m.lat, m.lng], {
                radius,
                color: '#802b80',
                weight: 2,
                fillColor: '#993399',
                fillOpacity: 0.55,
            }).addTo(map);
            const popupBase = '<strong>' + m.name + '</strong><br>' +
                (m.address ? m.address + '<br>' : '') +
                m.count + ' student' + (m.count === 1 ? '' : 's') +
                (m.departments.length ? '<br><span style="color:#6b6478;">' + m.departments.join(', ') + '</span>' : '') +
                '<br><a href="https://www.google.com/maps/search/?api=1&query=' + m.lat + ',' + m.lng + '" target="_blank" rel="noopener">Verify on Google Maps &rarr;</a>';
            circle.bindPopup(popupBase);
            bounds.push([m.lat, m.lng]);

            const fallback = L.polyline([[pickup.lat, pickup.lng], [m.lat, m.lng]], {
                color: '#993399', weight: 1, opacity: 0.35, dashArray: '4,5',
            }).addTo(map);

            routeTasks.push(async () => {
                const route = await fetchRoadRoute([pickup, m]);
                if (!route) return;
                map.removeLayer(fallback);
                L.polyline(route.latlngs, { color: '#993399', weight: 2, opacity: 0.6 }).addTo(map);
                circle.bindPopup(popupBase + '<br><span style="color:#6b6478;">~' + route.distanceKm + 'km by road, ~' + route.durationMin + ' min</span>');
            });
        });

        // Combined journeys: a solid, numbered road route from pickup
        // through each stop in recommended order, and back. Straight lines
        // shown immediately as a fallback while the real route loads.
        journeys.forEach((journey, idx) => {
            const color = routeColors[idx % routeColors.length];
            const waypoints = [pickup, ...journey.stops, pickup];
            const path = waypoints.map(p => [p.lat, p.lng]);

            const fallback = L.polyline(path, { color, weight: 3, opacity: 0.85, dashArray: '4,5' }).addTo(map);

            journey.stops.forEach(stop => {
                const icon = L.divIcon({
                    className: '',
                    html: '<div style="background:' + color + ';color:#fff;border-radius:999px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;box-shadow:0 1px 4px rgba(0,0,0,.35);">' + stop.order + '</div>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                });
                const marker = L.marker([stop.lat, stop.lng], { icon }).addTo(map);
                marker.bindPopup(
                    '<strong>Stop ' + stop.order + ': ' + stop.name + '</strong><br>' +
                    (stop.address ? stop.address + '<br>' : '') +
                    stop.count + ' student' + (stop.count === 1 ? '' : 's') + ' &middot; journey: ' + journey.label +
                    '<br><a href="https://www.google.com/maps/search/?api=1&query=' + stop.lat + ',' + stop.lng + '" target="_blank" rel="noopener">Verify on Google Maps &rarr;</a>'
                );
                bounds.push([stop.lat, stop.lng]);
            });

            routeTasks.push(async () => {
                const route = await fetchRoadRoute(waypoints);
                if (!route) return;
                map.removeLayer(fallback);
                const roadLine = L.polyline(route.latlngs, { color, weight: 4, opacity: 0.85 }).addTo(map);
                roadLine.bindPopup('<strong>' + journey.label + '</strong><br>~' + route.distanceKm + 'km by road, ~' + route.durationMin + ' min driving (excl. stops)');
            });
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }

        runSequentially(routeTasks);
    </script>
</x-shell>
