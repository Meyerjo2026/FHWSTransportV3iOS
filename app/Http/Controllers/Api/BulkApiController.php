<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StaffController;
use App\Models\ClinicalSite;
use App\Models\TripRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * CSV bulk uploads, mirroring the web importers but reporting per-row
 * problems so the mobile app can show what was skipped and why.
 */
class BulkApiController extends Controller
{
    private const MAX_ROWS = 5000;

    public function trips(Request $request): JsonResponse
    {
        $this->requireRole($request, 'staff', 'admin');
        [$rows, $error] = $this->rows($request);
        if ($error) {
            return $error;
        }

        $sites = ClinicalSite::pluck('id', 'name');
        $uploader = $request->user()->name;
        $created = 0;
        $skipped = [];
        $unknownSites = [];

        foreach ($rows as [$line, $r]) {
            foreach (['site', 'date', 'time'] as $col) {
                if (($r[$col] ?? '') === '') {
                    $skipped[] = ['line' => $line, 'reason' => "Missing {$col}"];
                    continue 2;
                }
            }
            $date = $this->date($r['date']);
            if (! $date) {
                $skipped[] = ['line' => $line, 'reason' => "Invalid date \"{$r['date']}\" (use YYYY-MM-DD)"];
                continue;
            }
            if (! isset($sites[$r['site']])) {
                $unknownSites[$r['site']] = true;
            }

            TripRequest::create([
                'student_id' => null,
                'student_name' => ($r['name'] ?? '') ?: 'Unknown',
                'student_email' => $r['email'] ?? '',
                'student_number' => $r['number'] ?? '',
                'clinical_site_id' => $sites[$r['site']] ?? null,
                'site' => $r['site'],
                'date' => $date,
                'time' => $r['time'],
                'department' => ($r['department'] ?? '') ?: null,
                'qualification' => ($r['qualification'] ?? '') ?: null,
                'year' => ($r['year'] ?? '') ?: null,
                'notes' => ($r['notes'] ?? '') ?: null,
                'status' => 'approved',
                'source' => 'bulk',
                'uploaded_by' => $uploader,
            ]);
            $created++;
        }

        return response()->json([
            'created' => $created,
            'skipped' => array_slice($skipped, 0, 50),
            'skipped_count' => count($skipped),
            'unknown_sites' => array_keys($unknownSites),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $this->requireRole($request, 'staff');
        [$rows, $error] = $this->rows($request);
        if ($error) {
            return $error;
        }

        $created = [];
        $skipped = [];

        foreach ($rows as [$line, $r]) {
            $name = $r['name'] ?? '';
            $email = $r['email'] ?? '';
            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = ['line' => $line, 'reason' => $name === '' ? 'Missing name' : 'Missing or invalid email'];
                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skipped[] = ['line' => $line, 'reason' => "{$email} already exists"];
                continue;
            }

            $temp = StaffController::generateTempPassword();
            User::create([
                'name' => $name,
                'email' => $email,
                'number' => $r['number'] ?? '',
                'role' => 'student',
                'password' => Hash::make($temp),
                'must_change_password' => true,
            ]);
            $created[] = ['name' => $name, 'email' => $email, 'number' => $r['number'] ?? '', 'password' => $temp];
        }

        return response()->json([
            'created' => $created,
            'skipped' => array_slice($skipped, 0, 50),
            'skipped_count' => count($skipped),
        ]);
    }

    public function sites(Request $request): JsonResponse
    {
        $this->requireRole($request, 'admin');
        [$rows, $error] = $this->rows($request);
        if ($error) {
            return $error;
        }

        $added = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as [$line, $r]) {
            if (($r['name'] ?? '') === '') {
                $skipped[] = ['line' => $line, 'reason' => 'Missing name'];
                continue;
            }
            $site = ClinicalSite::updateOrCreate(
                ['name' => $r['name']],
                [
                    'address' => ($r['address'] ?? '') ?: null,
                    'type' => ($r['type'] ?? '') ?: null,
                    'lat' => is_numeric($r['lat'] ?? null) ? (float) $r['lat'] : null,
                    'lng' => is_numeric($r['lng'] ?? null) ? (float) $r['lng'] : null,
                ]
            );
            $site->wasRecentlyCreated ? $added++ : $updated++;
        }

        return response()->json([
            'added' => $added,
            'updated' => $updated,
            'skipped' => array_slice($skipped, 0, 50),
            'skipped_count' => count($skipped),
        ]);
    }

    /**
     * Parse the uploaded CSV into [line number, assoc row] pairs.
     *
     * @return array{0: array<int, array{0:int,1:array<string,string>}>, 1: ?JsonResponse}
     */
    private function rows(Request $request): array
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $lines = file($request->file('file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if ($lines && str_starts_with($lines[0], "\xEF\xBB\xBF")) {
            $lines[0] = substr($lines[0], 3);
        }
        if (count($lines) < 2) {
            return [[], response()->json(['message' => 'The file needs a header row and at least one data row.'], 422)];
        }
        if (count($lines) - 1 > self::MAX_ROWS) {
            return [[], response()->json(['message' => 'Too many rows (max '.self::MAX_ROWS.').'], 422)];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv(array_shift($lines)));
        $out = [];
        foreach ($lines as $i => $line) {
            $cells = array_map('trim', str_getcsv($line));
            if (count($cells) < count($header)) {
                $out[] = [$i + 2, array_combine($header, array_pad($cells, count($header), ''))];
            } else {
                $out[] = [$i + 2, array_combine($header, array_slice($cells, 0, count($header)))];
            }
        }

        return [$out, null];
    }

    private function date(string $value): ?string
    {
        try {
            $d = Carbon::createFromFormat('Y-m-d', $value);

            return $d && $d->format('Y-m-d') === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function requireRole(Request $request, string ...$roles): void
    {
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'Forbidden for your role.');
    }
}
