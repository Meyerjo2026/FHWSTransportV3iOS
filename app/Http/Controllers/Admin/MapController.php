<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\TripRequest;
use App\Support\JourneyPlanner;
use App\Support\TransportOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.map', ['user' => Auth::user()] + $this->data($request));
    }

    /**
     * Map data shared by the web view and the JSON API.
     */
    public function data(Request $request): array
    {
        $department = $request->input('department');
        $date = $request->input('date');
        $shift = $request->input('shift');
        // What to plot: 'all' (default), 'journeys' (routes only), 'individual' (single-site markers only).
        $view = $request->input('view', 'all');

        $pickup = [
            'name' => TransportOptions::PICKUP_POINT,
            'lat' => TransportOptions::PICKUP_LAT,
            'lng' => TransportOptions::PICKUP_LNG,
        ];

        // Individual placements: approved/finalised requests NOT part of a
        // combined journey (those are rendered as routes instead, below).
        $requests = TripRequest::query()
            ->whereNotNull('clinical_site_id')
            ->whereIn('status', ['approved', 'finalised'])
            ->whereNull('journey_id')
            ->with('clinicalSite')
            ->when($department, fn ($q) => $q->where('department', $department))
            ->when($date, fn ($q) => $q->where('date', $date))
            ->get()
            ->filter(fn ($r) => $r->clinicalSite && $r->clinicalSite->lat !== null)
            ->when($shift, fn ($c) => $c->filter(fn ($r) => TransportOptions::shiftFor($r->time) === $shift));

        $markers = $requests
            ->groupBy('clinical_site_id')
            ->map(function ($group) {
                $site = $group->first()->clinicalSite;

                return [
                    'name' => $site->name,
                    'address' => $site->address,
                    'lat' => $site->lat,
                    'lng' => $site->lng,
                    'count' => $group->count(),
                    'departments' => $group->pluck('department')->filter()->unique()->values(),
                ];
            })
            ->values();

        // Combined journeys: recommend a visiting order (nearest-neighbour
        // route from the pickup point) for each, so the map shows an actual
        // multi-stop route rather than just separate pins.
        $journeys = Journey::with('tripRequests.clinicalSite')
            ->get()
            ->filter(function ($journey) use ($department, $date, $shift) {
                if ($date && $journey->date !== $date) {
                    return false;
                }
                if ($shift && TransportOptions::shiftFor($journey->time) !== $shift) {
                    return false;
                }
                if ($department && ! $journey->tripRequests->contains('department', $department)) {
                    return false;
                }

                return $journey->tripRequests->isNotEmpty();
            })
            ->map(function ($journey) use ($pickup) {
                $items = $journey->tripRequests->filter(fn ($r) => $r->clinicalSite && $r->clinicalSite->lat !== null);
                $sites = $items->unique('clinical_site_id')->map->clinicalSite->values();
                $route = JourneyPlanner::orderRoute($sites, $pickup['lat'], $pickup['lng']);

                return [
                    'id' => $journey->id,
                    'label' => $journey->label,
                    'date' => $journey->date,
                    'time' => $journey->time,
                    'studentCount' => $items->count(),
                    'stops' => collect($route['stops'])->map(fn ($site, $i) => [
                        'order' => $i + 1,
                        'name' => $site->name,
                        'address' => $site->address,
                        'lat' => $site->lat,
                        'lng' => $site->lng,
                        'count' => $items->where('clinical_site_id', $site->id)->count(),
                    ])->values(),
                    'legsKm' => $route['legsKm'],
                    'totalKm' => $route['totalKm'],
                    'allFinalised' => $items->every(fn ($r) => $r->status === 'finalised'),
                ];
            })
            ->values();

        if ($view === 'journeys') {
            $markers = collect();
        } elseif ($view === 'individual') {
            $journeys = collect();
        }

        return [
            'markers' => $markers,
            'journeys' => $journeys,
            'departments' => TransportOptions::departments(),
            'filters' => [
                'department' => $department,
                'date' => $date,
                'shift' => $shift,
                'view' => $view,
            ],
            'pickup' => $pickup,
            'totalPlacements' => $requests->count() + $journeys->sum('studentCount'),
            'totalSites' => $markers->count(),
            'totalJourneys' => $journeys->count(),
        ];
    }
}
