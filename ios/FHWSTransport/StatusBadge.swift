import SwiftUI

struct StatusBadge: View {
    let status: String

    var body: some View {
        let color = Theme.statusColor(status)
        Text(status.capitalized)
            .font(.brand(.caption, weight: .bold))
            .padding(.horizontal, 10).padding(.vertical, 4)
            .background(color.opacity(0.14), in: Capsule())
            .foregroundStyle(color)
    }
}

/// Small calendar-style date tile used on trip rows.
struct DateTile: View {
    let iso: String

    private var parts: (day: String, month: String) {
        let f = DateFormatter()
        f.dateFormat = "yyyy-MM-dd"
        f.locale = Locale(identifier: "en_US_POSIX")
        guard let d = f.date(from: iso) else { return (String(iso.suffix(2)), "") }
        return (d.formatted(.dateTime.day()), d.formatted(.dateTime.month(.abbreviated)).uppercased())
    }

    var body: some View {
        VStack(spacing: 0) {
            Text(parts.month).font(.brand(.caption2, weight: .bold)).foregroundStyle(Theme.primary)
            Text(parts.day).font(.brand(.title3, weight: .bold))
        }
        .frame(width: 48, height: 52)
        .background(Theme.primary.opacity(0.10), in: RoundedRectangle(cornerRadius: 12))
    }
}

struct TripRow: View {
    let trip: TripRequest
    var showStudent = false

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            DateTile(iso: trip.date)
            VStack(alignment: .leading, spacing: 3) {
                HStack(alignment: .firstTextBaseline) {
                    Text(trip.site ?? "—").font(.brand(.headline))
                    Spacer(minLength: 6)
                    StatusBadge(status: trip.status)
                }
                if showStudent, let n = trip.studentName {
                    Label([n, trip.studentNumber].compactMap { $0 }.joined(separator: " · "), systemImage: "person")
                        .font(.brand(.subheadline))
                }
                Label(trip.time, systemImage: "clock")
                    .font(.brand(.subheadline)).foregroundStyle(.secondary)
                Text([trip.department, trip.qualification, trip.year].compactMap { $0 }.joined(separator: " · "))
                    .font(.brand(.caption)).foregroundStyle(.secondary)
                if let notes = trip.notes, !notes.isEmpty {
                    Text(notes).font(.brand(.caption)).italic()
                }
                if let staff = trip.responsibleStaff, !staff.isEmpty, trip.status == "pending" {
                    Text("Awaiting approval from \(staff.joined(separator: ", "))")
                        .font(.brand(.caption)).foregroundStyle(Theme.amber)
                }
            }
        }
        .padding(.vertical, 4)
    }
}
