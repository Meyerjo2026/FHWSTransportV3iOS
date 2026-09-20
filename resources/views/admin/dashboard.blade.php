@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
$pending = $statusCounts->get('pending', 0);
$approved = $statusCounts->get('approved', 0);
$finalised = $statusCounts->get('finalised', 0);
$rejected = $statusCounts->get('rejected', 0);
@endphp
<x-shell :user="$user" :active="'/admin/dashboard'" :tabs="$tabs">
    <div class="stat-tiles">
        <a class="stat-tile total" href="/admin">
            <div class="kicker">All requests</div>
            <div class="number">{{ $totalRequests }}</div>
            <div class="cta">Consolidate trips &rarr;</div>
        </a>
        <a class="stat-tile pending" href="/admin/review">
            <div class="kicker">Pending approval</div>
            <div class="number">{{ $pending }}</div>
            <div class="cta">Approve / reject &rarr;</div>
        </a>
        <a class="stat-tile approved" href="/admin/finalise">
            <div class="kicker">Approved · awaiting finalise</div>
            <div class="number">{{ $approved }}</div>
            <div class="cta">Finalise trips &rarr;</div>
        </a>
        <a class="stat-tile finalised" href="/admin/quotes">
            <div class="kicker">Finalised · awaiting RFQ</div>
            <div class="number">{{ $awaitingQuote }}</div>
            <div class="cta">Create RFQ &rarr;</div>
        </a>
        <a class="stat-tile rejected" href="/admin/review?filter=rejected">
            <div class="kicker">Rejected</div>
            <div class="number">{{ $rejected }}</div>
            <div class="cta">Review history</div>
        </a>
    </div>

    <div class="card">
        <h2>Recent requests</h2>
        @if ($recent->isEmpty())
            <div class="empty">No requests yet.</div>
        @else
            <table>
                <thead><tr><th>Student</th><th>Site</th><th>Date</th><th>Time</th><th>Department</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($recent as $r)
                        <tr>
                            <td>{{ $r->student_name }}</td>
                            <td class="muted">{{ $r->site }}</td>
                            <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                            <td class="muted">{{ $r->time }}</td>
                            <td class="muted">{{ $r->department }}</td>
                            <td><span class="pill {{ $r->status }}">{{ $r->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Department usage</h2>
        @if ($departmentStats->isEmpty())
            <div class="empty">No requests with a department recorded yet.</div>
        @else
            <table>
                <thead><tr><th>Department</th><th>Usage</th><th>Total</th><th>Pending</th><th>Approved</th><th>Rejected</th><th>Finalised</th></tr></thead>
                <tbody>
                    @foreach ($departmentStats as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td style="min-width:160px;">
                                <div style="background:var(--border);border-radius:4px;overflow:hidden;height:8px;">
                                    <div style="background:var(--primary);width:{{ $row['pct'] }}%;height:8px;"></div>
                                </div>
                            </td>
                            <td>{{ $row['total'] }}</td>
                            <td class="muted">{{ $row['pending'] }}</td>
                            <td class="muted">{{ $row['approved'] }}</td>
                            <td class="muted">{{ $row['rejected'] }}</td>
                            <td class="muted">{{ $row['finalised'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Qualification usage</h2>
        @if ($qualificationStats->isEmpty())
            <div class="empty">No requests with a qualification recorded yet.</div>
        @else
            <table>
                <thead><tr><th>Qualification</th><th>Usage</th><th>Total</th><th>Pending</th><th>Approved</th><th>Rejected</th><th>Finalised</th></tr></thead>
                <tbody>
                    @foreach ($qualificationStats as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td style="min-width:160px;">
                                <div style="background:var(--border);border-radius:4px;overflow:hidden;height:8px;">
                                    <div style="background:var(--green);width:{{ $row['pct'] }}%;height:8px;"></div>
                                </div>
                            </td>
                            <td>{{ $row['total'] }}</td>
                            <td class="muted">{{ $row['pending'] }}</td>
                            <td class="muted">{{ $row['approved'] }}</td>
                            <td class="muted">{{ $row['rejected'] }}</td>
                            <td class="muted">{{ $row['finalised'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Export trip requests</h2>
        <p class="muted" style="font-size:13px;">Download a CSV of trip requests for the statuses you select below.</p>
        <form method="GET" action="/admin/export">
            <div class="checkbox-row" style="margin-bottom:6px;">
                <input type="checkbox" name="status[]" value="approved" id="st-approved" checked>
                <label for="st-approved" style="margin:0;">Approved</label>
            </div>
            <div class="checkbox-row" style="margin-bottom:6px;">
                <input type="checkbox" name="status[]" value="rejected" id="st-rejected" checked>
                <label for="st-rejected" style="margin:0;">Rejected</label>
            </div>
            <div class="checkbox-row" style="margin-bottom:6px;">
                <input type="checkbox" name="status[]" value="pending" id="st-pending">
                <label for="st-pending" style="margin:0;">Pending</label>
            </div>
            <div class="checkbox-row" style="margin-bottom:12px;">
                <input type="checkbox" name="status[]" value="finalised" id="st-finalised">
                <label for="st-finalised" style="margin:0;">Finalised</label>
            </div>
            <button class="btn" type="submit">Export CSV</button>
        </form>
    </div>
</x-shell>