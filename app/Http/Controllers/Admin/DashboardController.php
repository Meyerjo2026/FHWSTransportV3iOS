<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TripRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    protected const STATUSES = ['pending', 'approved', 'rejected', 'finalised'];

    public function index()
    {
        $statusCounts = TripRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $departmentStats = $this->breakdown('department');
        $qualificationStats = $this->breakdown('qualification');

        $recent = TripRequest::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $awaitingQuote = TripRequest::where('status', 'finalised')->whereNull('quote_id')->count();

        return view('admin.dashboard', [
            'user' => Auth::user(),
            'statusCounts' => $statusCounts,
            'departmentStats' => $departmentStats,
            'qualificationStats' => $qualificationStats,
            'statuses' => self::STATUSES,
            'totalRequests' => TripRequest::count(),
            'recent' => $recent,
            'awaitingQuote' => $awaitingQuote,
        ]);
    }

    /**
     * Count requests per distinct value of the given column, broken down
     * by status, sorted by total descending.
     */
    private function breakdown(string $column)
    {
        $rows = TripRequest::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("$column as label, status, count(*) as total")
            ->groupBy($column, 'status')
            ->get();

        $grouped = $rows->groupBy('label')->map(function ($rowsForLabel, $label) {
            $byStatus = $rowsForLabel->pluck('total', 'status');

            return [
                'label' => $label,
                'total' => $byStatus->sum(),
                'pending' => $byStatus->get('pending', 0),
                'approved' => $byStatus->get('approved', 0),
                'rejected' => $byStatus->get('rejected', 0),
                'finalised' => $byStatus->get('finalised', 0),
            ];
        })->values()->sortByDesc('total')->values();

        $max = $grouped->max('total') ?: 1;

        return $grouped->map(function ($row) use ($max) {
            $row['pct'] = round(($row['total'] / $max) * 100);

            return $row;
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $statuses = array_values(array_intersect(
            $request->input('status', self::STATUSES),
            self::STATUSES
        ));
        if (empty($statuses)) {
            $statuses = self::STATUSES;
        }

        $rows = TripRequest::whereIn('status', $statuses)
            ->orderBy('date')
            ->get();

        $filename = 'trip-requests-'.implode('-', $statuses).'-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Student Name', 'Email', 'Student Number', 'Site', 'Date', 'Time',
                'Department', 'Qualification', 'Status', 'Source', 'Notes', 'Submitted At',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id, $r->student_name, $r->student_email, $r->student_number, $r->site,
                    $r->date, $r->time, $r->department, $r->qualification, $r->status,
                    $r->source, $r->notes, $r->created_at,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
