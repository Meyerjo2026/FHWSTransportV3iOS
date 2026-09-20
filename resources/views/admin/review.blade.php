@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/bulk-trips' => 'Bulk Upload Trips', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
$show = in_array($filter, ['pending', 'approved', 'rejected'], true) ? $filter : 'pending';
@endphp
<x-shell :user="$user" :active="'/admin/review'" :tabs="$tabs">
    <div class="tabbar" id="review-tabs" style="max-width:560px;margin-bottom:20px;">
        <a data-tab="pending" class="{{ $show === 'pending' ? 'active' : '' }}">Pending ({{ $pending->count() }})</a>
        <a data-tab="approved" class="{{ $show === 'approved' ? 'active' : '' }}">Approved ({{ $approved->count() }})</a>
        <a data-tab="rejected" class="{{ $show === 'rejected' ? 'active' : '' }}">Rejected ({{ $rejected->count() }})</a>
    </div>

    <div class="card review-panel" data-panel="pending" style="{{ $show === 'pending' ? '' : 'display:none;' }}">
        <h2>Pending requests <span class="badge-count">{{ $pending->count() }}</span></h2>
        @if ($pending->isEmpty())
            <div class="empty">Nothing pending. Approve and reject buttons appear here as students request trips.</div>
        @else
            <form method="POST" action="/admin/review/bulk" id="bulk-form">
                @csrf
                <input type="hidden" name="status" value="approved">
                <table>
                    <thead><tr><th style="width:36px;"><input type="checkbox" id="select-all-pending" checked></th><th>Student</th><th>Date</th><th>Time</th><th>Site</th><th>Department</th><th>Year</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pending as $r)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $r->id }}" class="pending-check" checked></td>
                                <td>{{ $r->student_name }}</td>
                                <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                <td>{{ $r->time }}</td>
                                <td>{{ $r->site }}</td>
                                <td class="muted">{{ $r->department }}</td>
                                <td class="muted">{{ $r->year }}</td>
                                <td class="row-actions">
                                    <form method="POST" action="/requests/{{ $r->id }}/status">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn small green" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="/requests/{{ $r->id }}/status">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn small red" type="submit">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="display:flex;align-items:center;gap:12px;margin-top:14px;">
                    <button class="btn small green" type="submit">Approve selected ({{ $pending->count() }})</button>
                    <span class="muted" style="font-size:12px;" id="bulk-count">All pending requests are selected.</span>
                </div>
            </form>
        @endif
    </div>

    <div class="card review-panel" data-panel="approved" style="{{ $show === 'approved' ? '' : 'display:none;' }}">
        <h2>Approved &middot; not yet finalised <span class="badge-count">{{ $approved->count() }}</span></h2>
        @if ($approved->isEmpty())
            <div class="empty">None.</div>
        @else
            <table>
                <thead><tr><th>Student</th><th>Date</th><th>Time</th><th>Site</th><th>Department</th><th>Year</th><th></th></tr></thead>
                <tbody>
                    @foreach ($approved as $r)
                        <tr>
                            <td>{{ $r->student_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                            <td>{{ $r->time }}</td>
                            <td>{{ $r->site }}</td>
                            <td class="muted">{{ $r->department }}</td>
                            <td class="muted">{{ $r->year }}</td>
                            <td class="row-actions">
                                <a class="btn small secondary" href="/admin/finalise">Finalise</a>
                                <form method="POST" action="/requests/{{ $r->id }}/status">
                                    @csrf
                                    <input type="hidden" name="status" value="pending">
                                    <button class="btn small secondary" type="submit">Un-approve</button>
                                </form>
                                <form method="POST" action="/requests/{{ $r->id }}/status">
                                    @csrf
                                    <input type="hidden" name="status" value="rejected">
                                    <button class="btn small red" type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card review-panel" data-panel="rejected" style="{{ $show === 'rejected' ? '' : 'display:none;' }}">
        <h2>Rejected <span class="badge-count">{{ $rejected->count() }}</span></h2>
        @if ($rejected->isEmpty())
            <div class="empty">Nothing rejected.</div>
        @else
            <table>
                <thead><tr><th>Student</th><th>Date</th><th>Time</th><th>Site</th><th>Rejected</th><th></th></tr></thead>
                <tbody>
                    @foreach ($rejected as $r)
                        <tr>
                            <td>{{ $r->student_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                            <td>{{ $r->time }}</td>
                            <td>{{ $r->site }}</td>
                            <td class="muted">{{ $r->updated_at->format('d M Y H:i') }}</td>
                            <td class="row-actions">
                                <form method="POST" action="/requests/{{ $r->id }}/status">
                                    @csrf
                                    <input type="hidden" name="status" value="pending">
                                    <button class="btn small secondary" type="submit">Reopen as pending</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        const tabs = document.querySelectorAll('#review-tabs a');
        const panels = document.querySelectorAll('.review-panel');
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const name = tab.dataset.tab;
                tabs.forEach(t => t.classList.toggle('active', t === tab));
                panels.forEach(p => p.style.display = p.dataset.panel === name ? '' : 'none');
                history.replaceState(null, '', '?filter=' + name);
            });
        });

        const selectAll = document.getElementById('select-all-pending');
        const countLabel = document.getElementById('bulk-count');
        if (selectAll) {
            const boxes = document.querySelectorAll('.pending-check');
            selectAll.addEventListener('change', () => {
                boxes.forEach(b => b.checked = selectAll.checked);
                updateCount();
            });
            boxes.forEach(b => b.addEventListener('change', updateCount));
            function updateCount() {
                const n = document.querySelectorAll('.pending-check:checked').length;
                countLabel.textContent = n
                    ? n + ' of ' + boxes.length + ' pending selected.'
                    : 'No requests selected.';
            }
        }
    </script>
</x-shell>