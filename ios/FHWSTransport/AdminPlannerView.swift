import SwiftUI

/// Suggests combining approved trips to nearby clinical sites on the same
/// date and shift into one vehicle. Uses distance-based clustering on the
/// server (not a hosted AI model), so results are explainable.
struct AdminPlannerView: View {
    @Environment(Session.self) private var session
    @State private var plan: JourneyPlan?
    @State private var threshold = 5.0
    @State private var error: String?
    @State private var pending: JourneySuggestion?
    @State private var label = ""

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                Section {
                    Stepper(value: $threshold, in: 1...50, step: 1) {
                        Text("Group sites within \(Int(threshold)) km")
                    }
                    if let plan {
                        Text("\(plan.eligible) approved trips available · max \(plan.maxPerTrip) students per vehicle")
                            .font(.brand(.caption)).foregroundStyle(.secondary)
                    }
                }
                if let plan {
                    Section("Suggestions (\(plan.suggestions.count))") {
                        ForEach(plan.suggestions) { s in
                            VStack(alignment: .leading, spacing: 4) {
                                Text("\(s.date) · \(s.time)").font(.brand(.headline))
                                Text("\(s.studentCount) students · \(s.stops.count) stops")
                                    .font(.brand(.subheadline))
                                Text(s.stops.joined(separator: " → "))
                                    .font(.brand(.caption)).foregroundStyle(.secondary)
                                Text(String(format: "Route ≈ %.1f km round trip · sites span %.1f km", s.totalKm, s.maxSpanKm))
                                    .font(.brand(.caption)).foregroundStyle(.secondary)
                                Button("Combine into one journey") {
                                    label = s.stops.joined(separator: " + ")
                                    pending = s
                                }
                                .buttonStyle(.pill)
                                .padding(.top, 6)
                            }
                            .padding(.vertical, 4)
                        }
                        if plan.suggestions.isEmpty {
                            Text("Nothing to combine. Trips must be approved, not yet combined, on the same date and shift, at sites within the distance.")
                                .font(.brand(.footnote)).foregroundStyle(.secondary)
                        }
                    }
                    Section("Combined journeys (\(plan.active.count))") {
                        ForEach(plan.active) { j in
                            VStack(alignment: .leading, spacing: 2) {
                                Text(j.label).font(.brand(.headline))
                                Text("\(j.date) · \(j.time) · \(j.tripCount) trips")
                                    .font(.brand(.subheadline)).foregroundStyle(.secondary)
                                if j.locked { Text("Finalised").font(.brand(.caption)).foregroundStyle(.blue) }
                            }
                            .swipeActions {
                                if !j.locked {
                                    Button("Split", role: .destructive) { Task { await cancel(j) } }
                                }
                            }
                        }
                    }
                }
            }
            .overlay { if plan == nil && error == nil { ProgressView() } }
            .brandBackground()
            .navigationTitle("Trip Planner")
            .refreshable { await load() }
            .task(id: threshold) {
                try? await Task.sleep(for: .milliseconds(400))
                if !Task.isCancelled { await load() }
            }
            .alert("Combine trips", isPresented: .constant(pending != nil)) {
                TextField("Journey label", text: $label)
                Button("Combine") { if let s = pending { Task { await combine(s) } } }
                Button("Cancel", role: .cancel) { pending = nil }
            } message: {
                Text("Name this shared journey.")
            }
        }
    }

    private func load() async {
        do { plan = try await session.api.journeys(threshold: threshold); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch is CancellationError {}
        catch { self.error = error.localizedDescription }
    }

    private func combine(_ s: JourneySuggestion) async {
        pending = nil
        do { try await session.api.combine(s, label: label, threshold: threshold); await load() }
        catch { self.error = error.localizedDescription }
    }

    private func cancel(_ j: ActiveJourney) async {
        do { try await session.api.cancelJourney(id: j.id); await load() }
        catch { self.error = error.localizedDescription }
    }
}
