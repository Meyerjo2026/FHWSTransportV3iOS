@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/bulk-trips' => 'Bulk Upload Trips', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin'" :tabs="$tabs">
    <div class="card">
        <h2>Consolidated trips <span class="badge-count">{{ $groups->count() }} groups</span></h2>
        <p class="muted" style="font-size:13px;">Approved requests grouped by date, site and time slot — mirrors the trip roster used for scheduling and invoicing. Groups over {{ \App\Support\TransportOptions::MAX_STUDENTS_PER_TRIP }} students are automatically split into separate trips.</p>
        @if ($groups->isEmpty())
            <div class="empty">No approved trips yet.</div>
        @else
            <table>
                <thead><tr><th>Date</th><th>Site</th><th>Time</th><th>Trip</th><th>Students</th><th>Department(s)</th><th>Count</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($groups as $g)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($g['date'])->format('d M Y') }}</td>
                            <td>{{ $g['site'] }}</td>
                            <td>{{ $g['time'] }}</td>
                            <td>{{ $g['tripParts'] > 1 ? "Trip {$g['tripPart']} of {$g['tripParts']}" : '—' }}</td>
                            <td class="muted">{{ $g['items']->pluck('student_name')->implode(', ') }}</td>
                            <td class="muted">{{ $g['items']->pluck('department')->filter()->unique()->implode(', ') }}</td>
                            <td>{{ $g['items']->count() }}</td>
                            <td><span class="pill {{ $g['allFinal'] ? 'finalised' : 'approved' }}">{{ $g['allFinal'] ? 'finalised' : 'approved' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-shell>
