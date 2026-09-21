<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalSite;
use App\Models\GroupAssignment;
use App\Models\TripRequest;
use App\Models\User;
use App\Support\TransportOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->active) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'ios')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        return response()->json(['ok' => true]);
    }

    public function options(): JsonResponse
    {
        $qualifications = TransportOptions::QUALIFICATIONS_BY_DEPARTMENT;
        $all = TransportOptions::allQualifications();

        return response()->json([
            'sites' => ClinicalSite::where('active', true)->orderBy('name')->get(['id', 'name', 'address', 'type']),
            'time_slots' => TransportOptions::TIME_SLOTS,
            'departments' => TransportOptions::departments(),
            'qualifications_by_department' => $qualifications,
            'years_by_qualification' => array_combine($all, array_map(fn ($q) => TransportOptions::yearsFor($q), $all)),
            'pickup_point' => TransportOptions::PICKUP_POINT,
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $this->requireRole($request, 'student');
        $assignments = GroupAssignment::whereNotNull('staff_id')->with('staff')->get();

        $list = TripRequest::where('student_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) use ($assignments) {
                $staff = GroupAssignment::resolve($assignments, $r->year, $r->department, $r->qualification);

                return $this->tripPayload($r) + ['responsible_staff' => collect($staff)->pluck('name')->values()];
            });

        return response()->json($list);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $this->requireRole($request, 'student');

        $data = $request->validate([
            'clinical_site_id' => ['required', 'exists:clinical_sites,id'],
            'date' => ['required', 'date'],
            'time' => ['required', 'string'],
            'department' => ['required', 'string', 'in:'.implode(',', TransportOptions::departments())],
            'qualification' => ['required', 'string'],
            'year' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! TransportOptions::isValidPair($data['department'], $data['qualification'])) {
            return response()->json(['message' => 'Qualification does not belong to the chosen department.', 'errors' => ['qualification' => ['Invalid qualification.']]], 422);
        }
        if (! TransportOptions::isValidYear($data['qualification'], $data['year'])) {
            return response()->json(['message' => 'Year does not apply to the chosen qualification.', 'errors' => ['year' => ['Invalid year.']]], 422);
        }

        $user = $request->user();
        $site = ClinicalSite::findOrFail($data['clinical_site_id']);

        $trip = TripRequest::create([
            'student_id' => $user->id,
            'student_name' => $user->name,
            'student_email' => $user->email,
            'student_number' => $user->number,
            'clinical_site_id' => $site->id,
            'site' => $site->name,
            'date' => $data['date'],
            'time' => $data['time'],
            'department' => $data['department'],
            'qualification' => $data['qualification'],
            'year' => $data['year'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'source' => 'manual',
        ]);

        return response()->json($this->tripPayload($trip), 201);
    }

    public function approvals(Request $request): JsonResponse
    {
        $user = $this->requireRole($request, 'staff', 'admin');

        $scope = function ($query) use ($user) {
            if ($user->isAdmin()) {
                return;
            }
            $mine = GroupAssignment::where('staff_id', $user->id)->get();
            $query->whereIn('year', $mine->where('type', 'year')->pluck('value'))
                ->orWhereIn('department', $mine->where('type', 'department')->pluck('value'))
                ->orWhereIn('qualification', $mine->where('type', 'qualification')->pluck('value'))
                ->orWhere(fn ($q) => $q->whereNull('year')->whereNull('department')->whereNull('qualification'));
        };

        return response()->json([
            'pending' => TripRequest::where('status', 'pending')->where($scope)->orderBy('date')->get()->map(fn ($r) => $this->tripPayload($r)),
            'recent' => TripRequest::where('status', '!=', 'pending')->where($scope)->orderByDesc('created_at')->limit(15)->get()->map(fn ($r) => $this->tripPayload($r)),
        ]);
    }

    public function setStatus(Request $request, TripRequest $tripRequest): JsonResponse
    {
        $this->requireRole($request, 'staff', 'admin');
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,pending']]);
        $tripRequest->update(['status' => $data['status']]);

        return response()->json($this->tripPayload($tripRequest));
    }

    public function calendarLink(Request $request): JsonResponse
    {
        return response()->json($this->calendarPayload($request, $this->requireRole($request, 'student')));
    }

    public function rotateCalendarLink(Request $request): JsonResponse
    {
        $user = $this->requireRole($request, 'student');
        $user->forceFill(['calendar_token' => Str::random(40)])->save();

        return response()->json($this->calendarPayload($request, $user));
    }

    private function calendarPayload(Request $request, User $user): array
    {
        if (! $user->calendar_token) {
            $user->forceFill(['calendar_token' => Str::random(40)])->save();
        }
        $url = $request->getSchemeAndHttpHost().'/calendar/'.$user->calendar_token.'.ics';

        return ['url' => $url, 'webcal_url' => preg_replace('#^https?://#', 'webcal://', $url)];
    }

    private function requireRole(Request $request, string ...$roles): User
    {
        $user = $request->user();
        abort_unless(in_array($user->role, $roles, true), 403, 'Forbidden for your role.');

        return $user;
    }

    private function userPayload(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'number' => $u->number,
            'role' => $u->role,
            'must_change_password' => (bool) $u->must_change_password,
        ];
    }

    private function tripPayload(TripRequest $r): array
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
