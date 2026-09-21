<?php

namespace App\Http\Controllers;

use App\Models\TripRequest;
use App\Models\User;
use App\Support\IcsFeed;
use Illuminate\Http\Response;

/**
 * Public, token-protected calendar subscription feed. Calendar apps can't
 * send auth headers, so the unguessable token in the URL is the credential;
 * students can rotate it from the app to revoke an old link.
 */
class CalendarFeedController extends Controller
{
    public function show(string $token): Response
    {
        $student = User::where('calendar_token', $token)->where('role', 'student')->where('active', true)->first();
        abort_unless($student, 404);

        $trips = TripRequest::where('student_id', $student->id)
            ->whereIn('status', ['approved', 'finalised'])
            ->orderBy('date')
            ->get();

        return response(IcsFeed::render('Clinical placement transport', $trips), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="transport.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
