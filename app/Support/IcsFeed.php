<?php

namespace App\Support;

use App\Models\TripRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds an iCalendar (RFC 5545) feed of a student's confirmed trips. Calendar
 * apps re-fetch a subscribed feed periodically, so trips that are rejected or
 * moved back to pending simply drop out on the next refresh.
 */
class IcsFeed
{
    private const TZID = 'Africa/Johannesburg';

    /**
     * @param  Collection<int, TripRequest>  $trips
     */
    public static function render(string $calendarName, Collection $trips): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CPUT FHWS//Transport Requests//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($calendarName),
            'X-WR-TIMEZONE:'.self::TZID,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
            'BEGIN:VTIMEZONE',
            'TZID:'.self::TZID,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0200',
            'TZNAME:SAST',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];

        foreach ($trips as $trip) {
            array_push($lines, ...self::event($trip));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    /** @return array<int, string> */
    private static function event(TripRequest $trip): array
    {
        $date = Carbon::parse($trip->date)->format('Y-m-d');
        $stamp = ($trip->updated_at ?? now())->copy()->utc();

        $lines = [
            'BEGIN:VEVENT',
            'UID:trip-'.$trip->id.'@fhws-transport',
            'DTSTAMP:'.$stamp->format('Ymd\THis\Z'),
            'LAST-MODIFIED:'.$stamp->format('Ymd\THis\Z'),
            'SEQUENCE:'.$stamp->timestamp,
        ];

        if (preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', trim((string) $trip->time), $m)) {
            $start = Carbon::parse("$date {$m[1]}:{$m[2]}", self::TZID);
            $end = Carbon::parse("$date {$m[3]}:{$m[4]}", self::TZID);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay(); // night shift ending the next morning
            }
            $lines[] = 'DTSTART;TZID='.self::TZID.':'.$start->format('Ymd\THis');
            $lines[] = 'DTEND;TZID='.self::TZID.':'.$end->format('Ymd\THis');
        } else {
            $day = Carbon::parse($date);
            $lines[] = 'DTSTART;VALUE=DATE:'.$day->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$day->copy()->addDay()->format('Ymd');
        }

        $description = ['Pickup: '.TransportOptions::PICKUP_POINT, 'Time slot: '.$trip->time, 'Status: '.ucfirst($trip->status)];
        if ($trip->notes) {
            $description[] = $trip->notes;
        }

        array_push(
            $lines,
            'SUMMARY:'.self::escape('Clinical placement: '.($trip->site ?: 'transport')),
            'LOCATION:'.self::escape((string) $trip->site),
            'DESCRIPTION:'.self::escape(implode("\n", $description)),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:Transport pickup',
            'TRIGGER:-PT1H',
            'END:VALARM',
            'END:VEVENT',
        );

        return $lines;
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Fold long lines at 75 octets without splitting a multibyte character (RFC 5545 §3.1). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $chunk = '';
        $limit = 75;
        foreach (mb_str_split($line) as $char) {
            if (strlen($chunk) + strlen($char) > $limit) {
                $out .= $chunk."\r\n ";
                $chunk = '';
                $limit = 74; // continuation lines start with a space
            }
            $chunk .= $char;
        }

        return $out.$chunk;
    }
}
