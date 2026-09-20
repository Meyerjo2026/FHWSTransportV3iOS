@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/bulk-trips' => 'Bulk Upload Trips', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin/journeys'" :tabs="$tabs">
    <div class="card">
        <h2>AI trip planner</h2>
        <p class="muted" style="font-size:13px;">
            Looks at approved trips that aren't finalised or combined yet, groups the ones on the <strong>same date and time slot</strong> whose clinical sites are close together, and recommends combining them into one multi-stop vehicle journey instead of dispatching a separate vehicle per site.
        </p>
        <p class="hint">
            This is a deterministic distance-clustering algorithm (not a hosted AI/LLM call — no external API key is configured for this app), so every recommendation is explainable: two sites are grouped if they're within the chosen distance of each other, directly or via a chain of nearby stops.
        </p>
        <p class="hint" style="color:var(--amber);">
            Run this <strong>before</strong> finalising trips — once a trip is finalised its price is locked in for invoicing, so it's no longer eligible to be combined here.
        </p>
        <form method="GET" action="/admin/journeys" class="grid" style="max-width:360px;align-items:end;">
            <div class="field" style="margin:0;">
                <label>Group sites within (km)</label>
                <input type="number" name="threshold" min="0.5" max="100" step="0.5" value="{{ $threshold }}">
            </div>
            <div class="field" style="margin:0;">
                <button class="btn secondary" type="submit">Recalculate</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Recommended journeys <span class="badge-count">{{ $suggestions->count() }}</span></h2>
        @if ($suggestions->isEmpty())
            <div class="empty">
                No nearby-site groupings found within {{ $threshold }}km right now.
                @if ($eligibleCount === 0)
                    <div style="margin-top:8px;">
                        @if ($finalisedCount > 0 || $pendingCount > 0)
                            There are no <strong>approved, not-yet-finalised</strong> trips to combine —
                            @if ($pendingCount > 0)
                                {{ $pendingCount }} trip{{ $pendingCount === 1 ? ' is' : 's are' }} still <a href="/admin/review">awaiting approval</a>
                                @if ($finalisedCount > 0), and @endif
                            @endif
                            @if ($finalisedCount > 0)
                                {{ $finalisedCount }} {{ $finalisedCount === 1 ? 'has' : 'have' }} already been <a href="/admin/finalise">finalised</a> (too late to combine).
                            @else
                                .
                            @endif
                        @else
                            There are no approved trips at all yet.
                        @endif
                    </div>
                @else
                    <div style="margin-top:8px;">{{ $eligibleCount }} trip{{ $eligibleCount === 1 ? '' : 's' }} eligible, but none share a date/time/nearby-site match within {{ $threshold }}km — try a larger radius.</div>
                @endif
            </div>
        @else
            @foreach ($suggestions as $s)
                <div style="border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                        <div>
                            <strong>{{ \Carbon\Carbon::parse($s['date'])->format('d M Y') }}</strong> &middot; {{ $s['time'] }}
                            <div class="muted" style="font-size:12px;margin-top:2px;">
                                {{ $s['sites']->count() }} sites within {{ $s['maxSpanKm'] }}km of each other &middot; {{ $s['studentCount'] }} student{{ $s['studentCount'] === 1 ? '' : 's' }}
                                @if ($s['studentCount'] > \App\Support\TransportOptions::MAX_STUDENTS_PER_TRIP)
                                    &middot; <span style="color:var(--amber);">exceeds {{ \App\Support\TransportOptions::MAX_STUDENTS_PER_TRIP }}/vehicle, will auto-split when finalised</span>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="/admin/journeys">
                            @csrf
                            <input type="hidden" name="trip_request_ids" value="{{ $s['items']->pluck('id')->implode(',') }}">
                            <input type="hidden" name="date" value="{{ $s['date'] }}">
                            <input type="hidden" name="time" value="{{ $s['time'] }}">
                            <input type="hidden" name="threshold" value="{{ $threshold }}">
                            <input type="hidden" name="label" value="{{ $s['sites']->pluck('name')->implode(' + ') }}">
                            <button class="btn small" type="submit">Combine into one journey</button>
                        </form>
                    </div>
                    <table style="margin-top:10px;">
                        <thead><tr><th>Site</th><th>Students</th><th>Department(s)</th></tr></thead>
                        <tbody>
                            @foreach ($s['sites'] as $site)
                                @php $siteItems = $s['items']->where('clinical_site_id', $site->id); @endphp
                                <tr>
                                    <td>{{ $site->name }}</td>
                                    <td>{{ $siteItems->count() }}</td>
                                    <td class="muted">{{ $siteItems->pluck('department')->filter()->unique()->implode(', ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    <div class="card">
        <h2>Active journeys <span class="badge-count">{{ $active->count() }}</span></h2>
        @if ($active->isEmpty())
            <div class="empty">No combined journeys yet.</div>
        @else
            <table>
                <thead><tr><th>Journey</th><th>Date</th><th>Time</th><th>Students</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($active as $j)
                        <tr>
                            <td>{{ $j->label }}</td>
                            <td>{{ \Carbon\Carbon::parse($j->date)->format('d M Y') }}</td>
                            <td>{{ $j->time }}</td>
                            <td>{{ $j->tripRequests->count() }}</td>
                            <td>
                                @if ($j->tripRequests->every(fn ($r) => $r->status === 'finalised'))
                                    <span class="pill finalised">finalised</span>
                                @else
                                    <span class="pill approved">approved</span>
                                @endif
                            </td>
                            <td>
                                @unless ($j->tripRequests->contains('status', 'finalised'))
                                    <form method="POST" action="/admin/journeys/{{ $j->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn small secondary" type="submit">Cancel</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-shell>
