<?php

namespace App\Http\Controllers;

use App\Models\ClinicalSite;
use App\Models\GroupAssignment;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function approve()
    {
        // A staff member only sees trip requests matching a year group,
        // department, or qualification they've been assigned as
        // responsible for (see admin/group-assignments); requests
        // missing that field entirely (bulk-uploaded without one, or
        // "N/A" qualifications) are visible to everyone since they
        // can't be attributed to any group. Admins bypass this entirely
        // via their own review() view, which sees everything.
        $myAssignments = GroupAssignment::where('staff_id', Auth::id())->get();
        $myYears = $myAssignments->where('type', 'year')->pluck('value');
        $myDepartments = $myAssignments->where('type', 'department')->pluck('value');
        $myQualifications = $myAssignments->where('type', 'qualification')->pluck('value');

        $scope = function ($query) use ($myYears, $myDepartments, $myQualifications) {
            $query->whereIn('year', $myYears)
                ->orWhereIn('department', $myDepartments)
                ->orWhereIn('qualification', $myQualifications)
                ->orWhere(fn ($q) => $q->whereNull('year')->whereNull('department')->whereNull('qualification'));
        };

        $pending = TripRequest::where('status', 'pending')
            ->where($scope)
            ->orderBy('date')
            ->get();
        $recent = TripRequest::where('status', '!=', 'pending')
            ->where($scope)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('staff.approve', [
            'user' => Auth::user(),
            'pending' => $pending,
            'recent' => $recent,
            'myYears' => $myYears,
            'myDepartments' => $myDepartments,
            'myQualifications' => $myQualifications,
        ]);
    }

    public function setStatus(Request $request, TripRequest $tripRequest)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $tripRequest->update(['status' => $data['status']]);

        return back();
    }

    public function bulkForm()
    {
        return view('staff.bulk', ['user' => Auth::user()]);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows) ?? []);

        $sitesByName = ClinicalSite::pluck('id', 'name');

        $count = 0;
        foreach ($rows as $row) {
            if (! $row || count($row) < count($header)) {
                continue;
            }
            $assoc = array_combine($header, array_map('trim', $row));
            if (empty($assoc['site']) || empty($assoc['date']) || empty($assoc['time'])) {
                continue;
            }

            TripRequest::create([
                'student_id' => null,
                'student_name' => ($assoc['name'] ?? '') ?: 'Unknown',
                'student_email' => $assoc['email'] ?? '',
                'student_number' => $assoc['number'] ?? '',
                'clinical_site_id' => $sitesByName[$assoc['site']] ?? null,
                'site' => $assoc['site'],
                'date' => $assoc['date'],
                'time' => $assoc['time'],
                'department' => ($assoc['department'] ?? '') ?: null,
                'qualification' => ($assoc['qualification'] ?? '') ?: null,
                'year' => ($assoc['year'] ?? '') ?: null,
                'notes' => ($assoc['notes'] ?? '') ?: null,
                'status' => 'approved',
                'source' => 'bulk',
                'uploaded_by' => Auth::user()->name,
            ]);
            $count++;
        }

        return back()->with('success', "Uploaded and approved {$count} trip(s).");
    }

    public function bulkStudentsForm()
    {
        return view('staff.bulk-students', ['user' => Auth::user()]);
    }

    public function bulkStudentsUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows) ?? []);

        $created = [];
        $skipped = [];

        foreach ($rows as $row) {
            if (! $row || count($row) < count($header)) {
                continue;
            }
            $assoc = array_combine($header, array_map('trim', $row));
            $name = $assoc['name'] ?? '';
            $email = $assoc['email'] ?? '';
            $number = $assoc['number'] ?? '';

            if (! $name || ! $email) {
                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skipped[] = $email;

                continue;
            }

            $tempPassword = self::generateTempPassword();

            User::create([
                'name' => $name,
                'email' => $email,
                'number' => $number,
                'role' => 'student',
                'password' => Hash::make($tempPassword),
                'must_change_password' => true,
            ]);

            $created[] = [
                'name' => $name,
                'email' => $email,
                'number' => $number,
                'password' => $tempPassword,
            ];
        }

        return view('staff.bulk-students', [
            'user' => Auth::user(),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    /**
     * A random temporary password using an unambiguous character set
     * (no 0/O/1/l/I) so it's easy to read off a printed list and type in.
     */
    private static function generateTempPassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
