@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/bulk-trips' => 'Bulk Upload Trips', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin/quotes'" :tabs="$tabs">
    <div class="card">
        <h2>Create Request for Quote (RFQ)</h2>
        <p class="muted" style="font-size:13px;">Builds an RFQ from finalised trips not yet quoted, in the HG Travelling Services invoice format (per trip, "and return"). Destinations, date and time are grouped together, capped at {{ \App\Support\TransportOptions::MAX_STUDENTS_PER_TRIP }} students per trip &mdash; larger groups are automatically split into additional trips.</p>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/admin/quotes">
            @csrf
            <div class="grid" style="grid-template-columns:1fr 1fr;">
                <div class="field">
                    <label>RFQ / period label</label>
                    <input name="period" placeholder="e.g. May 2026" required>
                </div>
                <div class="field">
                    <label>Pricing mode</label>
                    <div class="segmented" role="radiogroup" aria-label="Pricing mode">
                        <label class="segmented-option" data-mode="rate">
                            <input type="radio" name="pricing" value="rate" checked>
                            <span>With rate <span class="pill approved">money</span></span>
                        </label>
                        <label class="segmented-option" data-mode="tbc">
                            <input type="radio" name="pricing" value="tbc">
                            <span>Without rate <span class="pill pending">TBC</span></span>
                        </label>
                    </div>
                    <div class="hint">"Without rate" sends the trip list to the supplier with the price left open to be calculated &amp; confirmed.</div>
                </div>
            </div>
            <div class="field" id="rate-field" style="max-width:320px;">
                <label>Rate per trip (ZAR)</label>
                <input name="rate" type="number" step="0.01" value="{{ $defaultRate }}">
            </div>
            <button class="btn" type="submit" style="margin-top:10px;">Create RFQ</button>
        </form>
        @if ($groups->isEmpty())
            <div class="empty" style="margin-top:10px;">No finalised trips awaiting an RFQ.</div>
        @else
            <table style="margin-top:14px;">
                <thead><tr><th>Date</th><th>Site</th><th>Time</th><th>Trip</th><th>Students</th></tr></thead>
                <tbody>
                    @foreach ($groups as $g)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($g['date'])->format('d M Y') }}</td>
                            <td>{{ $g['site'] }}</td>
                            <td>{{ $g['time'] }}</td>
                            <td>{{ $g['tripParts'] > 1 ? "Trip {$g['tripPart']} of {$g['tripParts']}" : '—' }}</td>
                            <td>{{ $g['items']->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="card">
        <h3>Past RFQs</h3>
        @if ($quotes->isEmpty())
            <div class="empty">None generated yet.</div>
        @else
            <table>
                <thead><tr><th>Ref</th><th>Period</th><th>Date generated</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @foreach ($quotes as $q)
                        <tr>
                            <td>{{ $q->ref }}</td>
                            <td>{{ $q->period }} @if ($q->is_tbc) <span class="pill pending">TBC</span> @endif</td>
                            <td>{{ $q->created_at->format('d M Y') }}</td>
                            <td>{{ $q->isPriced() ? 'R '.number_format($q->total, 2) : 'To be calculated' }}</td>
                            <td><a class="btn small secondary" href="/admin/quotes/{{ $q->id }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <script>
        document.querySelectorAll('.segmented-option input[name=pricing]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const rateField = document.getElementById('rate-field');
                const rateInput = rateField.querySelector('input[name=rate]');
                if (radio.value === 'tbc') {
                    rateField.style.display = 'none';
                    rateInput.removeAttribute('required');
                    rateInput.value = '';
                } else {
                    rateField.style.display = '';
                    rateInput.setAttribute('required', 'required');
                }
            });
        });
    </script>
</x-shell>
