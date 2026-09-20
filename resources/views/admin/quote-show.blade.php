@php
$tabs = ['/admin/dashboard' => 'Dashboard', '/admin' => 'Consolidate Trips', '/admin/review' => 'Approve / Reject', '/admin/journeys' => 'AI Trip Planner', '/admin/finalise' => 'Finalise Trips', '/admin/quotes' => 'Create RFQ', '/admin/sites' => 'Clinical Sites', '/admin/map' => 'Map', '/admin/group-assignments' => 'Staff Assignments'];
@endphp
<x-shell :user="$user" :active="'/admin/quotes'" :tabs="$tabs">
    <div class="card" id="quotePreview">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;">Request for Quote (RFQ)</h2>
                <div class="muted">HG Travelling Services &middot; Bellville Campus</div>
            </div>
            <div style="text-align:right;font-size:13px;">
                <div><strong>Ref:</strong> {{ $quote->ref }}</div>
                <div><strong>Date:</strong> {{ $quote->created_at->format('Y/m/d') }}</div>
                <div><strong>Period:</strong> {{ $quote->period }} @if ($quote->is_tbc) <span class="pill pending">rate TBC</span> @endif</div>
            </div>
        </div>
        <table style="margin-top:16px;">
            <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit price</th><th>Extended price</th></tr></thead>
            <tbody>
                @foreach ($groups as $i => $g)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($g['date'])->format('d M Y') }} {{ $g['time'] }} - {{ $g['site'] }} and return - 7-Seater{{ $g['tripParts'] > 1 ? " (Trip {$g['tripPart']} of {$g['tripParts']})" : '' }}</td>
                        <td>{{ $g['items']->count() }},00 TRIP</td>
                        @if ($quote->isPriced())
                            <td>R {{ number_format($quote->rate, 2) }}</td>
                            <td>R {{ number_format($quote->rate * $g['items']->count(), 2) }}</td>
                        @else
                            <td colspan="2" class="muted" style="text-align:center;">To be calculated</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($quote->isPriced())
            <div class="invoice-total">Total (ZAR): R {{ number_format($quote->total, 2) }}</div>
        @else
            <div class="invoice-total">Total (ZAR): To be calculated <span class="muted" style="font-weight:400;font-size:13px;">&mdash; rate to be confirmed by supplier</span></div>
        @endif
        <div class="no-print" style="margin-top:14px;">
            <button class="btn secondary" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>
</x-shell>
