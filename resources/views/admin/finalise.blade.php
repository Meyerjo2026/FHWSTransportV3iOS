@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
$totalStudents = $groups->sum(fn ($g) => $g['items']->count());
@endphp
<x-shell :user="$user" :active="'/admin/finalise'" :tabs="$tabs">
    <div class="card">
        <h2>Finalise trips</h2>
        <p class="muted" style="font-size:13px;">Lock approved trip groups so they're ready for quoting. Finalised trips can no longer be rejected or un-approved — they move into the <a href="/admin/quotes">RFQ pool</a>.</p>
        @if ($groups->isEmpty())
            <div class="empty">Nothing left to finalise.</div>
        @else
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:16px;">
                <div class="field" style="margin:0;">
                    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em;">Trip groups</div>
                    <div style="font-size:22px;font-weight:700;">{{ $groups->count() }}</div>
                </div>
                <div class="field" style="margin:0;">
                    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.03em;">Students to finalise</div>
                    <div style="font-size:22px;font-weight:700;">{{ $totalStudents }}</div>
                </div>
            </div>
            <form method="POST" action="/admin/finalise" id="finalise-form">
                @csrf
                <table>
                    <thead><tr><th style="width:36px;"><input type="checkbox" id="select-all-finalise" checked></th><th>Date</th><th>Site</th><th>Time</th><th>Trip</th><th>Count</th></tr></thead>
                    <tbody>
                        @foreach ($groups as $g)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $g['items']->pluck('id')->implode(',') }}" class="finalise-check" checked></td>
                                <td>{{ \Carbon\Carbon::parse($g['date'])->format('d M Y') }}</td>
                                <td>{{ $g['site'] }}</td>
                                <td>{{ $g['time'] }}</td>
                                <td>{{ $g['tripParts'] > 1 ? "Trip {$g['tripPart']} of {$g['tripParts']}" : '—' }}</td>
                                <td>{{ $g['items']->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="display:flex;align-items:center;gap:12px;margin-top:14px;">
                    <button class="btn" type="submit">Finalise selected</button>
                    <span class="muted" style="font-size:12px;" id="finalise-count">All {{ $groups->count() }} groups selected.</span>
                </div>
            </form>
        @endif
    </div>

    <script>
        const selectAll = document.getElementById('select-all-finalise');
        if (selectAll) {
            const boxes = document.querySelectorAll('.finalise-check');
            const countLabel = document.getElementById('finalise-count');
            function update() {
                const n = document.querySelectorAll('.finalise-check:checked').length;
                countLabel.textContent = n
                    ? n + ' of ' + boxes.length + ' groups selected.'
                    : 'No groups selected.';
            }
            selectAll.addEventListener('change', () => {
                boxes.forEach(b => b.checked = selectAll.checked);
                update();
            });
            boxes.forEach(b => b.addEventListener('change', update));
        }
    </script>
</x-shell>