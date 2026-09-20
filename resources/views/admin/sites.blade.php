@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin/sites'" :tabs="$tabs">
    <div class="stat-tiles" style="margin-bottom:20px;">
        <div class="stat-tile total">
            <div class="kicker">Clinical sites</div>
            <div class="number">{{ $sites->count() }}</div>
            <div class="cta">{{ $typeOptions->count() }} categories</div>
        </div>
        <div class="stat-tile approved">
            <div class="kicker">Active</div>
            <div class="number">{{ $sites->where('active', true)->count() }}</div>
            <div class="cta">Offerable to students</div>
        </div>
        <div class="stat-tile finalised">
            <div class="kicker">With coordinates</div>
            <div class="number">{{ $sites->whereNotNull('lat')->count() }}</div>
            <div class="cta">Ready for the map</div>
        </div>
    </div>
    <div class="card" style="max-width:640px;">
        <h2>Add a clinical site</h2>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/admin/sites">
            @csrf
            <div class="field">
                <label>Site name</label>
                <input name="name" placeholder="e.g. Tygerberg" required>
            </div>
            <div class="field">
                <label>Address</label>
                <input name="address" placeholder="e.g. Francie van Zijl Dr, Parow, Cape Town">
            </div>
            <div class="field">
                <label>Type</label>
                <input name="type" list="site-type-options" placeholder="e.g. CHC, or type a new category">
            </div>
            <button class="btn" type="submit">Add site</button>
        </form>
    </div>
    <div class="card" style="max-width:640px;">
        <h2>Bulk upload clinical sites</h2>
        <p class="hint">CSV columns: <code>name, address, type, lat, lng</code>. Matches existing sites by name (updates them); new names are added. Only <code>name</code> is required.</p>
        <a class="btn secondary" href="data:text/csv;charset=utf-8,name%2Caddress%2Ctype%2Clat%2Clng%0ATygerberg%20Hospital%2C%22Francie%20van%20Zijl%20Dr%2C%20Parow%2C%20Cape%20Town%22%2CTertiary%20Hospital%2C-33.913661%2C18.614302%0A" download="clinical-sites-template.csv">Download template.csv</a>
        <form method="POST" action="/admin/sites/bulk-upload" enctype="multipart/form-data" style="margin-top:12px;">
            @csrf
            <div class="field">
                <label>CSV file</label>
                <input type="file" name="file" accept=".csv" required>
            </div>
            <button class="btn" type="submit">Upload</button>
        </form>
    </div>
    <datalist id="site-type-options">
        @foreach ($typeOptions as $option)
            <option value="{{ $option }}">
        @endforeach
    </datalist>
    <div class="card">
        <h2>Clinical sites <span class="badge-count">{{ $sites->count() }}</span></h2>
        <p class="hint">
            Coordinates come from OpenStreetMap geocoding, not manual entry — click "Verify" to open that exact pin in Google Maps and confirm it against street view / satellite imagery.
            <span style="color:var(--amber);">Shared estimate</span> means this site's coordinates were approximated at suburb level (no exact match found) and are shared with at least one other site — check these first.
        </p>
        <form method="GET" action="/admin/sites" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;">
            <div class="field" style="margin:0;">
                <label>Filter by type</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $option)
                        <option value="{{ $option }}" @selected($selectedType === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            @if ($selectedType)
                <a href="/admin/sites" class="btn small secondary">Clear filter</a>
            @endif
        </form>
        <table>
            <thead><tr><th>Site name</th><th>Type</th><th>Address</th><th>Coordinates</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($sites as $site)
                    @php
                        $coordKey = $site->lat !== null ? round($site->lat, 5).','.round($site->lng, 5) : null;
                        $isSharedEstimate = $coordKey && $duplicateCoordKeys->contains($coordKey);
                    @endphp
                    <tr id="site-row-{{ $site->id }}">
                        <td>{{ $site->name }}</td>
                        <td class="muted">{{ $site->type ?? '—' }}</td>
                        <td class="muted">{{ $site->address }}</td>
                        <td class="muted">
                            @if ($site->lat !== null)
                                {{ $site->lat }}, {{ $site->lng }}
                                <br>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $site->lat }},{{ $site->lng }}" target="_blank" rel="noopener">Verify on Google Maps &rarr;</a>
                                @if ($isSharedEstimate)
                                    <br><span style="color:var(--amber);font-size:11px;">Shared estimate</span>
                                @endif
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td><span class="pill {{ $site->active ? 'approved' : 'rejected' }}">{{ $site->active ? 'active' : 'inactive' }}</span></td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn small secondary" onclick="showSiteEdit({{ $site->id }}, {{ $site->lat ?? 'null' }}, {{ $site->lng ?? 'null' }})">Edit</button>
                            <form method="POST" action="/admin/sites/{{ $site->id }}/toggle" style="display:inline;">
                                @csrf
                                <button class="btn small secondary" type="submit">{{ $site->active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="site-edit-{{ $site->id }}" style="display:none;">
                        <td colspan="6">
                            <form method="POST" action="/admin/sites/{{ $site->id }}" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">
                                @csrf
                                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                                    <div class="field" style="margin:0;">
                                        <label>Site name</label>
                                        <input name="name" value="{{ $site->name }}" required>
                                    </div>
                                    <div class="field" style="margin:0;">
                                        <label>Address</label>
                                        <input name="address" value="{{ $site->address }}">
                                    </div>
                                    <div class="field" style="margin:0;">
                                        <label>Type</label>
                                        <input name="type" list="site-type-options" value="{{ $site->type }}" placeholder="e.g. CHC, or type a new category">
                                    </div>
                                    <div class="field" style="margin:0;width:120px;">
                                        <label>Latitude</label>
                                        <input name="lat" id="lat-{{ $site->id }}" value="{{ $site->lat }}">
                                    </div>
                                    <div class="field" style="margin:0;width:120px;">
                                        <label>Longitude</label>
                                        <input name="lng" id="lng-{{ $site->id }}" value="{{ $site->lng }}">
                                    </div>
                                    <button class="btn small" type="submit">Save</button>
                                    <button type="button" class="btn small secondary" onclick="document.getElementById('site-row-{{ $site->id }}').style.display='table-row'; document.getElementById('site-edit-{{ $site->id }}').style.display='none';">Cancel</button>
                                </div>
                                <div style="width:100%;max-width:480px;">
                                    <div class="field" style="margin:0 0 6px 0;">
                                        <label>Search a place to move the pin</label>
                                        <div style="display:flex;gap:6px;">
                                            <input type="text" id="search-{{ $site->id }}" placeholder="e.g. Tygerberg Hospital, Parow" style="flex:1;">
                                            <button type="button" class="btn small secondary" onclick="searchSitePlace({{ $site->id }})">Search</button>
                                        </div>
                                        <div id="search-results-{{ $site->id }}" class="muted" style="font-size:12px;"></div>
                                    </div>
                                    <div id="site-map-{{ $site->id }}" style="width:100%;height:260px;border:1px solid var(--border, #ddd);"></div>
                                    <p class="hint" style="margin-top:4px;">Drag the pin, or search above, to set the exact coordinates. Cross-check against satellite/street view before saving.</p>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const siteMaps = {};

        function showSiteEdit(id, lat, lng) {
            document.getElementById('site-row-' + id).style.display = 'none';
            const editRow = document.getElementById('site-edit-' + id);
            editRow.style.display = 'table-row';

            if (!siteMaps[id]) {
                const startLat = lat ?? {{ \App\Support\TransportOptions::PICKUP_LAT }};
                const startLng = lng ?? {{ \App\Support\TransportOptions::PICKUP_LNG }};

                const map = L.map('site-map-' + id).setView([startLat, startLng], lat ? 15 : 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
                marker.on('dragend', () => {
                    const pos = marker.getLatLng();
                    document.getElementById('lat-' + id).value = pos.lat.toFixed(6);
                    document.getElementById('lng-' + id).value = pos.lng.toFixed(6);
                });
                map.on('click', (e) => {
                    marker.setLatLng(e.latlng);
                    document.getElementById('lat-' + id).value = e.latlng.lat.toFixed(6);
                    document.getElementById('lng-' + id).value = e.latlng.lng.toFixed(6);
                });

                siteMaps[id] = { map, marker };

                // Leaflet needs a nudge once its container becomes visible.
                setTimeout(() => map.invalidateSize(), 50);
            } else {
                setTimeout(() => siteMaps[id].map.invalidateSize(), 50);
            }
        }

        async function searchSitePlace(id) {
            const query = document.getElementById('search-' + id).value.trim();
            const resultsEl = document.getElementById('search-results-' + id);
            if (!query) return;

            resultsEl.textContent = 'Searching…';
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(query + ', South Africa'));
                const results = await res.json();

                if (!results.length) {
                    resultsEl.textContent = 'No matches found.';
                    return;
                }

                resultsEl.innerHTML = '';
                results.forEach((r) => {
                    const link = document.createElement('a');
                    link.href = '#';
                    link.textContent = r.display_name;
                    link.style.display = 'block';
                    link.onclick = (e) => {
                        e.preventDefault();
                        const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
                        siteMaps[id].map.setView([lat, lng], 16);
                        siteMaps[id].marker.setLatLng([lat, lng]);
                        document.getElementById('lat-' + id).value = lat.toFixed(6);
                        document.getElementById('lng-' + id).value = lng.toFixed(6);
                        resultsEl.textContent = '';
                    };
                    resultsEl.appendChild(link);
                });
            } catch (e) {
                resultsEl.textContent = 'Search failed — try again.';
            }
        }
    </script>
</x-shell>
