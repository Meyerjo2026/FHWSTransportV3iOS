import SwiftUI

/// Staff and admin: approve or reject pending trip requests.
struct ApprovalsHome: View {
    var body: some View {
        TabView {
            ApprovalsView()
                .tabItem { Label("Approvals", systemImage: "checkmark.seal") }
            AccountView()
                .tabItem { Label("Account", systemImage: "person.crop.circle") }
        }
    }
}

struct ApprovalsView: View {
    @Environment(Session.self) private var session
    @State private var data: Approvals?
    @State private var error: String?

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                if let data {
                    Section("Pending (\(data.pending.count))") {
                        ForEach(data.pending) { trip in
                            TripRow(trip: trip, showStudent: true)
                                .swipeActions(edge: .trailing) {
                                    Button("Reject", role: .destructive) { Task { await set(trip, "rejected") } }
                                }
                                .swipeActions(edge: .leading) {
                                    Button("Approve") { Task { await set(trip, "approved") } }.tint(.green)
                                }
                        }
                    }
                    Section("Recent") {
                        ForEach(data.recent) { trip in
                            TripRow(trip: trip, showStudent: true)
                                .swipeActions {
                                    Button("Reopen") { Task { await set(trip, "pending") } }.tint(.orange)
                                }
                        }
                    }
                }
            }
            .overlay { if data == nil && error == nil { ProgressView() } }
            .navigationTitle("Approvals")
            .refreshable { await load() }
            .task { await load() }
        }
    }

    private func load() async {
        do { data = try await session.api.approvals(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }

    private func set(_ trip: TripRequest, _ status: String) async {
        do { _ = try await session.api.setStatus(id: trip.id, status: status); await load() }
        catch { self.error = error.localizedDescription }
    }
}
