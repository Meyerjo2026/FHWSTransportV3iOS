import SwiftUI

struct StatusBadge: View {
    let status: String

    private var color: Color {
        switch status {
        case "approved": .green
        case "rejected": .red
        case "finalised": .blue
        default: .orange
        }
    }

    var body: some View {
        Text(status.capitalized)
            .font(.caption.bold())
            .padding(.horizontal, 8).padding(.vertical, 3)
            .background(color.opacity(0.15), in: Capsule())
            .foregroundStyle(color)
    }
}

struct TripRow: View {
    let trip: TripRequest
    var showStudent = false

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(trip.site ?? "—").font(.headline)
                Spacer()
                StatusBadge(status: trip.status)
            }
            if showStudent, let n = trip.studentName {
                Text([n, trip.studentNumber].compactMap { $0 }.joined(separator: " · "))
                    .font(.subheadline)
            }
            Text("\(trip.date) · \(trip.time)").font(.subheadline).foregroundStyle(.secondary)
            Text([trip.department, trip.qualification, trip.year].compactMap { $0 }.joined(separator: " · "))
                .font(.caption).foregroundStyle(.secondary)
            if let notes = trip.notes, !notes.isEmpty {
                Text(notes).font(.caption)
            }
            if let staff = trip.responsibleStaff, !staff.isEmpty, trip.status == "pending" {
                Text("Awaiting approval from \(staff.joined(separator: ", "))")
                    .font(.caption).foregroundStyle(.secondary)
            }
        }
        .padding(.vertical, 2)
    }
}
