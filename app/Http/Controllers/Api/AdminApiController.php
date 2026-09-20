<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StaffController;
use App\Models\ClinicalSite;
use App\Models\GroupAssignment;
use App\Models\TripRequest;
use App\Models\User;
use App\Support\TransportOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminApiController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'finalised'];

    public function dashboard(): JsonResponse
    {
        $counts = TripRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'total' => TripRequest::count(),
            'status_counts' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) $counts->get($s, 0)]),
            'awaiting_quote' => TripRequest::where('status', 'finalised')->whereNull('quote_id')->count(),
            'by_department' => $this->breakdown('department'),
            'recent' => TripRequest::orderByDesc('created_at')->limit(10)->get()->map(fn ($r) => self::trip($r)),
        ]);
    }

    public function review(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $query = TripRequest::query()
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('date');

        return response()->json($query->limit(300)->get()->map(fn ($r) => self::trip($r)));
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending,finalised'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Finalised trips are locked; finalising is only allowed from approved.
        $query = TripRequest::whereIn('id', $data['ids'])->where('status', '!=', 'finalised');
        if ($data['status'] === 'finalised') {
            $query->where('status', 'approved');
        }
        $count = $query->update(['status' => $data['status']]);

        return response()->json(['updated' => $count]);
    }

    public function sites(): JsonResponse
    {
        return response()->json([
            'sites' => ClinicalSite::orderBy('name')->get(['id', 'name', 'address', 'type', 'lat', 'lng', 'active']),
            'types' => ClinicalSite::whereNotNull('type')->distinct()->pluck('type')->merge(TransportOptions::TYPE_OPTIONS)->unique()->sort()->values(),
        ]);
    }

    public function storeSite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clinical_sites,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(ClinicalSite::create($data), 201);
    }

    public function updateSite(Request $request, ClinicalSite $site): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clinical_sites,name,'.$site->id],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $site->update($data);

        return response()->json($site->fresh());
    }

    public function staff(): JsonResponse
    {
        $assignments = GroupAssignment::whereNotNull('staff_id')->get()->groupBy('staff_id');

        return response()->json(
            User::where('role', 'staff')->orderBy('name')->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'active' => (bool) $u->active,
                'assignments' => ($assignments[$u->id] ?? collect())->pluck('value')->values(),
            ])
        );
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $temp = StaffController::generateTempPassword();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'staff',
            'password' => Hash::make($temp),
            'must_change_password' => true,
            'active' => true,
        ]);

        return response()->json(['id' => $user->id, 'temporary_password' => $temp], 201);
    }

    public function resetStaffPassword(User $staff): JsonResponse
    {
        abort_unless($staff->isStaff(), 404);
        $temp = StaffController::generateTempPassword();
        $staff->forceFill(['password' => Hash::make($temp), 'must_change_password' => true])->save();
        $this->revokeAccess($staff);

        return response()->json(['temporary_password' => $temp]);
    }

    public function toggleStaff(User $staff): JsonResponse
    {
        abort_unless($staff->isStaff(), 404);
        $staff->active = ! $staff->active;
        $staff->save();
        if (! $staff->active) {
            $this->revokeAccess($staff);
        }

        return response()->json(['active' => (bool) $staff->active]);
    }

    public function destroyStaff(User $staff): JsonResponse
    {
        abort_unless($staff->isStaff(), 404);
        $this->revokeAccess($staff);
        GroupAssignment::where('staff_id', $staff->id)->delete();
        $staff->delete();

        return response()->json(['ok' => true]);
    }

    private function revokeAccess(User $u): void
    {
        DB::table('sessions')->where('user_id', $u->id)->delete();
        $u->tokens()->delete();
    }

    private function breakdown(string $column)
    {
        return TripRequest::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("$column as label, count(*) as total")
            ->groupBy($column)->orderByDesc('total')->limit(10)->get();
    }

    public static function trip(TripRequest $r): array
    {
        return [
            'id' => $r->id,
            'student_name' => $r->student_name,
            'student_number' => $r->student_number,
            'site' => $r->site,
            'date' => substr((string) $r->date, 0, 10),
            'time' => $r->time,
            'department' => $r->department,
            'qualification' => $r->qualification,
            'year' => $r->year,
            'notes' => $r->notes,
            'status' => $r->status,
        ];
    }
}
