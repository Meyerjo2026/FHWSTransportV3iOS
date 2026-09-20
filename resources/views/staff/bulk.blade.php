@php
$tabs = $tabs ?? ['/staff' => 'Approve Trips', '/staff/bulk' => 'Bulk Upload Trips', '/staff/students' => 'Bulk Upload Students'];
$active = $active ?? '/staff/bulk';
@endphp
<x-shell :user="$user" :active="$active" :tabs="$tabs">
    <div class="card" style="max-width:720px;">
        <h2>Bulk upload trips</h2>
        <p class="muted" style="font-size:13px;">Upload a CSV with columns: <code>name,email,number,site,date,time,department,qualification,year,notes</code> (header row required). <code>site</code> must match an existing clinical site name exactly. Date format: YYYY-MM-DD. Rows are added as <strong>approved</strong> trips.</p>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ $active === '/staff/bulk' ? '/staff/bulk' : '/admin/bulk-trips' }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <input type="file" name="file" accept=".csv" required>
            </div>
            <button class="btn" type="submit">Upload &amp; approve</button>
        </form>
        <div class="hint">Example row: <code>Thandi Nkosi,thandi@mycput.ac.za,0821234567,Khayelitsha,2026-05-04,06:00 - 18:00,Emergency Medical Sciences,Diploma in Emergency Care,Year 2,</code></div>
    </div>
    <div class="card">
        <h3>Download template or sample data</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn secondary" href="data:text/csv;charset=utf-8,name%2Cemail%2Cnumber%2Csite%2Cdate%2Ctime%2Cdepartment%2Cqualification%2Cyear%2Cnotes%0AThandi%20Nkosi%2Cthandi%40mycput.ac.za%2C0821234567%2CKhayelitsha%2C2026-05-04%2C06%3A00%20-%2018%3A00%2CEmergency%20Medical%20Sciences%2CDiploma%20in%20Emergency%20Care%2CYear%202%2C" download="template.csv">Download template.csv</a>
            <a class="btn" href="{{ asset('October-2026-simulated-trips.csv') }}" download="October-2026-simulated-trips.csv">Download sample data — 1,000 simulated students, Oct 2026</a>
        </div>
        <p class="hint" style="margin-top:10px;">The sample contains 1,000 simulated placements spread across October 2026 (weekdays): most clinical sites host 2&ndash;5 students per shift, with larger ~15-student cohorts at Tygerberg Hospital and Groote Schuur Hospital. Names and emails are fictional and unique. Upload it as-is to load a full month of approved trips for testing the dashboard, finalisation and RFQ flows.</p>
    </div>
</x-shell>