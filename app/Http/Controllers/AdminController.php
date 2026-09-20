<?php

namespace App\Http\Controllers;

use App\Models\ClinicalSite;
use App\Models\GroupAssignment;
use App\Models\Quote;
use App\Models\TripRequest;
use App\Models\User;
use App\Support\TransportOptions;
use App\Support\TripGrouper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Value options per assignment type, in the order shown on the
     * Group Assignments page.
     */
    private const ASSIGNMENT_TYPES = [
        'year' => ['label' => 'Year group', 'options' => TransportOptions::YEAR_OPTIONS],
        'department' => ['label' => 'Department'],
        'qualification' => ['label' => 'Qualification'],
    ];

    private function assignmentOptions(string $type): array
    {
        return match ($type) {
            'year' => TransportOptions::YEAR_OPTIONS,
            'department' => TransportOptions::departments(),
            'qualification' => TransportOptions::allQualifications(),
            default => [],
        };
    }

    public function groupAssignments()
    {
        $staffMembers = User::where('role', 'staff')->orderBy('name')->get();

        // Staff members with their assigned year/department/qualification
        // groups, for the "Staff members" list on the same page.
        $staffList = $staffMembers->map(function ($staff) {
            return [
                'staff' => $staff,
                'assignments' => GroupAssignment::where('staff_id', $staff->id)
                    ->orderBy('type')
                    ->get()
                    ->map(fn ($a) => [
                        'type' => $a->type,
                        'label' => self::ASSIGNMENT_TYPES[$a->type]['label'] ?? ucfirst($a->type),
                        'value' => $a->value,
                    ]),
            ];
        });

        $sections = collect(array_keys(self::ASSIGNMENT_TYPES))->map(function ($type) {
            $options = $this->assignmentOptions($type);
            $existing = GroupAssignment::with('staff')->where('type', $type)->get()->keyBy('value');

            return [
                'type' => $type,
                'label' => self::ASSIGNMENT_TYPES[$type]['label'],
                'assignments' => collect($options)->mapWithKeys(fn ($value) => [$value => $existing->get($value)]),
            ];
        });

        return view('admin.group-assignments', [
            'user' => Auth::user(),
            'sections' => $sections,
            'staffMembers' => $staffMembers,
            'staffList' => $staffList,
            'qualifications' => TransportOptions::allQualifications(),
            'years' => TransportOptions::YEAR_OPTIONS,
        ]);
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'qualification' => ['nullable', 'string', 'in:'.implode(',', TransportOptions::allQualifications())],
            'year' => ['nullable', 'string', 'in:'.implode(',', TransportOptions::YEAR_OPTIONS)],
        ]);

        $tempPassword = StaffController::generateTempPassword();

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'staff',
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
        ]);

        $assigned = [];
        foreach (['year' => $data['year'] ?? null, 'qualification' => $data['qualification'] ?? null] as $type => $value) {
            if (! $value) {
                continue;
            }
            GroupAssignment::updateOrCreate(
                ['type' => $type, 'value' => $value],
                ['staff_id' => $staff->id]
            );
            $assigned[] = "{$value} ({$type})";
        }

        $message = "Created staff member {$data['name']} ({$data['email']}).";
        if ($assigned) {
            $message .= ' Responsible for: '.implode(', ', $assigned).'.';
        }
        $message .= " Temporary password: {$tempPassword} — the staff member must change it on first login.";

        return back()->with('success', $message);
    }

    public function updateGroupAssignment(Request $request, string $type, string $value)
    {
        abort_unless(array_key_exists($type, self::ASSIGNMENT_TYPES), 404);
        abort_unless(in_array($value, $this->assignmentOptions($type), true), 404);

        $data = $request->validate([
            'staff_id' => ['nullable', 'exists:users,id'],
        ]);

        GroupAssignment::updateOrCreate(
            ['type' => $type, 'value' => $value],
            ['staff_id' => $data['staff_id'] ?: null]
        );

        return back()->with('success', "Updated staff assignment for {$value}.");
    }

    public function sites(Request $request)
    {
        $type = $request->query('type');

        $sites = ClinicalSite::orderBy('name')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->get();

        // Sites sharing the exact same coordinates are usually a sign the
        // location was approximated at suburb level (see
        // TransportOptions::SITE_SEED) rather than geocoded individually —
        // flagged here so an admin knows which pins most need a manual
        // Google Maps check.
        $duplicateCoordKeys = $sites
            ->filter(fn ($s) => $s->lat !== null)
            ->groupBy(fn ($s) => round($s->lat, 5).','.round($s->lng, 5))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        // Types actually in use, plus the starter list — lets the filter
        // and datalist include custom types an admin has typed in, not
        // just the ones TransportOptions ships with.
        $typeOptions = ClinicalSite::whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->merge(TransportOptions::TYPE_OPTIONS)
            ->unique()
            ->sort()
            ->values();

        return view('admin.sites', [
            'user' => Auth::user(),
            'sites' => $sites,
            'duplicateCoordKeys' => $duplicateCoordKeys,
            'typeOptions' => $typeOptions,
            'selectedType' => $type,
        ]);
    }

    public function storeSite(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clinical_sites,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        ClinicalSite::create($data);

        return back()->with('success', "Added clinical site: {$data['name']}.");
    }

    public function toggleSite(ClinicalSite $site)
    {
        $site->update(['active' => ! $site->active]);

        return back();
    }

    public function updateSite(Request $request, ClinicalSite $site)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clinical_sites,name,'.$site->id],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $site->update($data);

        return back()->with('success', "Updated clinical site: {$data['name']}.");
    }

    public function bulkUploadSites(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows) ?? []);

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            if (! $row || count($row) < count($header)) {
                continue;
            }
            $assoc = array_combine($header, array_map('trim', $row));
            if (empty($assoc['name'])) {
                continue;
            }

            $site = ClinicalSite::updateOrCreate(
                ['name' => $assoc['name']],
                [
                    'address' => ($assoc['address'] ?? '') ?: null,
                    'type' => ($assoc['type'] ?? '') ?: null,
                    'lat' => is_numeric($assoc['lat'] ?? null) ? (float) $assoc['lat'] : null,
                    'lng' => is_numeric($assoc['lng'] ?? null) ? (float) $assoc['lng'] : null,
                ]
            );

            $site->wasRecentlyCreated ? $created++ : $updated++;
        }

        return back()->with('success', "Uploaded clinical sites: {$created} added, {$updated} updated.");
    }

    public function consolidate()
    {
        $approved = TripRequest::whereIn('status', ['approved', 'finalised'])->get();
        $groups = TripGrouper::group($approved);

        return view('admin.consolidate', [
            'user' => Auth::user(),
            'groups' => $groups,
        ]);
    }

    public function review(Request $request)
    {
        $filter = in_array($request->query('filter'), ['pending', 'approved', 'rejected', ''], true)
            ? $request->query('filter', '')
            : '';

        $pending = TripRequest::where('status', 'pending')->orderBy('date')->get();
        $approved = TripRequest::where('status', 'approved')->orderBy('date')->get();
        $rejected = TripRequest::where('status', 'rejected')->orderByDesc('updated_at')->get();

        return view('admin.review', [
            'user' => Auth::user(),
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'filter' => $filter,
        ]);
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = TripRequest::whereIn('id', $data['ids'])
            ->where('status', '!=', 'finalised')
            ->count();

        TripRequest::whereIn('id', $data['ids'])
            ->where('status', '!=', 'finalised')
            ->update(['status' => $data['status']]);

        if ($count) {
            return back()->with('success', "Updated {$count} trip(s) to \"{$data['status']}\".");
        }

        return back()->with('error', 'No eligible trips were selected.');
    }

    public function finaliseForm()
    {
        $approved = TripRequest::whereIn('status', ['approved', 'finalised'])->get();
        $groups = TripGrouper::group($approved)->filter(fn ($g) => ! $g['allFinal'])->values();

        return view('admin.finalise', [
            'user' => Auth::user(),
            'groups' => $groups,
        ]);
    }

    public function finalise(Request $request)
    {
        $ids = collect($request->input('ids', []))
            ->flatMap(fn ($csv) => explode(',', $csv))
            ->filter()
            ->unique();

        TripRequest::whereIn('id', $ids)->update(['status' => 'finalised']);

        return redirect('/admin/finalise');
    }

    public function quotesIndex()
    {
        $finalisedUnquoted = TripRequest::where('status', 'finalised')->whereNull('quote_id')->get();
        $groups = TripGrouper::group($finalisedUnquoted);
        $quotes = Quote::orderByDesc('created_at')->get();

        return view('admin.quotes', [
            'user' => Auth::user(),
            'groups' => $groups,
            'quotes' => $quotes,
            'defaultRate' => TransportOptions::DEFAULT_RATE,
        ]);
    }

    public function generateQuote(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string'],
            'pricing' => ['required', 'in:rate,tbc'],
            'rate' => ['required_if:pricing,rate', 'numeric'],
        ]);

        $finalised = TripRequest::where('status', 'finalised')->whereNull('quote_id')->get();
        if ($finalised->isEmpty()) {
            return back()->with('error', 'No finalised trips awaiting a quote.');
        }

        $isTbc = $data['pricing'] === 'tbc';
        $groups = TripGrouper::group($finalised);

        if ($isTbc) {
            // "Without money" variant: the rate is deliberately left open
            // ("to be calculated / confirmed") rather than billed at a
            // fixed per-trip amount. The RFQ still lists every trip line
            // so the supplier can price it.
            $quote = Quote::create([
                'ref' => 'RFQ'.(1000 + Quote::count() + 1),
                'period' => $data['period'],
                'rate' => null,
                'total' => null,
                'is_tbc' => true,
                'created_by' => Auth::user()->name,
            ]);
        } else {
            $rate = $data['rate'];
            $total = $groups->sum(fn ($g) => $rate * $g['items']->count());

            $quote = Quote::create([
                'ref' => 'RFQ'.(1000 + Quote::count() + 1),
                'period' => $data['period'],
                'rate' => $rate,
                'total' => $total,
                'is_tbc' => false,
                'created_by' => Auth::user()->name,
            ]);
        }

        TripRequest::whereIn('id', $finalised->pluck('id'))->update(['quote_id' => $quote->id]);

        return redirect("/admin/quotes/{$quote->id}");
    }

    public function quoteShow(Quote $quote)
    {
        $items = TripRequest::where('quote_id', $quote->id)->get();
        $groups = TripGrouper::group($items);

        return view('admin.quote-show', [
            'user' => Auth::user(),
            'quote' => $quote,
            'groups' => $groups,
        ]);
    }
}
