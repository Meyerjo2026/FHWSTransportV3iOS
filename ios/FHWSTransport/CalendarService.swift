import Foundation
import EventKit
import Observation

/// Adds approved trips to the student's calendar using write-only access, so the
/// app never reads existing events. Trips already added are remembered locally.
@Observable
final class CalendarService {
    private let store = EKEventStore()
    private(set) var added: Set<Int>

    private static let key = "calendarAddedTripIDs"
    /// Trips run on South African time regardless of the phone's current zone.
    private static let zone = TimeZone(identifier: "Africa/Johannesburg") ?? .current
    static let pickup = "CPUT Bellville Campus"

    init() {
        added = Set(UserDefaults.standard.array(forKey: Self.key) as? [Int] ?? [])
    }

    enum CalendarError: LocalizedError {
        case denied
        case badTime(String)

        var errorDescription: String? {
            switch self {
            case .denied: "Calendar access was denied. Enable it for this app in Settings ▸ Privacy ▸ Calendars."
            case .badTime(let t): "Couldn't read the trip time \"\(t)\"."
            }
        }
    }

    func isAdded(_ trip: TripRequest) -> Bool { added.contains(trip.id) }

    /// Trips that can go on a calendar: approved or finalised and not already added.
    func pending(_ trips: [TripRequest]) -> [TripRequest] {
        trips.filter { ($0.status == "approved" || $0.status == "finalised") && !added.contains($0.id) }
    }

    func add(_ trips: [TripRequest]) async throws -> Int {
        guard try await store.requestWriteOnlyAccessToEvents() else { throw CalendarError.denied }
        var count = 0
        for trip in trips where !added.contains(trip.id) {
            let (start, end) = try Self.interval(for: trip)
            let event = EKEvent(eventStore: store)
            event.calendar = store.defaultCalendarForNewEvents
            event.title = "Clinical placement: \(trip.site ?? "transport")"
            event.location = trip.site
            event.startDate = start
            event.endDate = end
            event.timeZone = Self.zone
            var notes = ["Pickup: \(Self.pickup)", "Time slot: \(trip.time)"]
            if let n = trip.notes, !n.isEmpty { notes.append(n) }
            event.notes = notes.joined(separator: "\n")
            event.addAlarm(EKAlarm(relativeOffset: -3600))
            try store.save(event, span: .thisEvent)
            added.insert(trip.id)
            count += 1
        }
        UserDefaults.standard.set(Array(added), forKey: Self.key)
        return count
    }

    /// "06:00 - 18:00" on the trip date; night shifts ending earlier than they start run into the next day.
    static func interval(for trip: TripRequest) throws -> (Date, Date) {
        let parts = trip.time.split(separator: "-").map { $0.trimmingCharacters(in: .whitespaces) }
        var cal = Calendar(identifier: .gregorian)
        cal.timeZone = zone
        let day = DateFormatter()
        day.calendar = cal
        day.timeZone = zone
        day.locale = Locale(identifier: "en_US_POSIX")
        day.dateFormat = "yyyy-MM-dd HH:mm"

        guard parts.count == 2,
              let start = day.date(from: "\(trip.date) \(parts[0])"),
              var end = day.date(from: "\(trip.date) \(parts[1])") else {
            throw CalendarError.badTime(trip.time)
        }
        if end <= start { end = cal.date(byAdding: .day, value: 1, to: end) ?? end }
        return (start, end)
    }
}
