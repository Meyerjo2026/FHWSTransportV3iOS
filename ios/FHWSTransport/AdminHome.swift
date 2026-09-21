import SwiftUI

struct AdminHome: View {
    var body: some View {
        TabView {
            AdminDashboardView()
                .tabItem { Label("Dashboard", systemImage: "chart.bar") }
            AdminReviewView()
                .tabItem { Label("Review", systemImage: "checkmark.seal") }
            AdminPlannerView()
                .tabItem { Label("Planner", systemImage: "point.topleft.down.to.point.bottomright.curvepath") }
            AdminMapView()
                .tabItem { Label("Map", systemImage: "map") }
            AdminQuotesView()
                .tabItem { Label("RFQs", systemImage: "doc.text") }
            BulkHome(kinds: [.trips, .sites])
                .tabItem { Label("Upload", systemImage: "square.and.arrow.up") }
            AdminSitesView()
                .tabItem { Label("Sites", systemImage: "cross.case") }
            AdminStaffView()
                .tabItem { Label("Staff", systemImage: "person.2") }
            AccountView()
                .tabItem { Label("Account", systemImage: "person.crop.circle") }
        }
    }
}

struct AdminDashboardView: View {
    @Environment(Session.self) private var session
    @State private var data: AdminDashboard?
    @State private var error: String?

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                if let d = data {
                    Section {
                        LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
                            ForEach(["pending", "approved", "rejected", "finalised"], id: \.self) { s in
                                StatTile(title: s.capitalized, value: d.statusCounts[s] ?? 0, color: Theme.statusColor(s))
                            }
                        }
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                    } header: {
                        Text("\(d.total) requests in total")
                    }
                    if d.awaitingQuote > 0 {
                        Section {
                            Label("\(d.awaitingQuote) finalised trip\(d.awaitingQuote == 1 ? "" : "s") awaiting an RFQ", systemImage: "doc.text")
                                .font(.brand(.subheadline))
                        }
                    }
                    Section("By department") {
                        ForEach(d.byDepartment) { LabeledContent($0.label, value: "\($0.total)") }
                    }
                    Section("Recent") {
                        ForEach(d.recent) { TripRow(trip: $0, showStudent: true) }
                    }
                }
            }
            .overlay { if data == nil && error == nil { ProgressView() } }
            .brandBackground()
            .navigationTitle("Dashboard")
            .refreshable { await load() }
            .task { await load() }
        }
    }

    private func load() async {
        do { data = try await session.api.adminDashboard(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }
}

struct AdminReviewView: View {
    @Environment(Session.self) private var session
    @State private var filter = "pending"
    @State private var trips: [TripRequest] = []
    @State private var selection = Set<Int>()
    @State private var error: String?
    @State private var editing = false

    private let filters = ["pending", "approved", "rejected", "finalised"]

    var body: some View {
        NavigationStack {
            List(selection: $selection) {
                if let error { Text(error).foregroundStyle(.red) }
                ForEach(trips) { TripRow(trip: $0, showStudent: true).tag($0.id) }
            }
            .environment(\.editMode, .constant(editing ? .active : .inactive))
            .safeAreaInset(edge: .top) {
                Picker("Status", selection: $filter) {
                    ForEach(filters, id: \.self) { Text($0.capitalized).tag($0) }
                }
                .pickerStyle(.segmented)
                .padding(.horizontal).padding(.bottom, 8)
                .background(.bar)
            }
            .overlay { if trips.isEmpty && error == nil { ContentUnavailableView("Nothing here", systemImage: "tray") } }
            .brandBackground()
            .navigationTitle("Review")
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(editing ? "Done" : "Select") { editing.toggle(); selection = [] }
                }
                if editing {
                    ToolbarItemGroup(placement: .bottomBar) {
                        Button("Approve") { Task { await apply("approved") } }
                        Button("Reject", role: .destructive) { Task { await apply("rejected") } }
                        Button("Finalise") { Task { await apply("finalised") } }
                        Button("Reopen") { Task { await apply("pending") } }
                    }
                }
            }
            .disabled(false)
            .refreshable { await load() }
            .task(id: filter) { selection = []; await load() }
        }
    }

    private func load() async {
        do { trips = try await session.api.adminReview(status: filter); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }

    private func apply(_ status: String) async {
        guard !selection.isEmpty else { return }
        do {
            _ = try await session.api.bulkStatus(ids: Array(selection), status: status)
            selection = []
            await load()
        } catch { self.error = error.localizedDescription }
    }
}

struct AdminSitesView: View {
    @Environment(Session.self) private var session
    @State private var data: SitesResponse?
    @State private var error: String?
    @State private var search = ""
    @State private var editing: AdminSite?
    @State private var adding = false

    private var filtered: [AdminSite] {
        guard let sites = data?.sites else { return [] }
        return search.isEmpty ? sites : sites.filter { $0.name.localizedCaseInsensitiveContains(search) }
    }

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                ForEach(filtered) { site in
                    Button { editing = site } label: {
                        VStack(alignment: .leading, spacing: 2) {
                            HStack {
                                Text(site.name).font(.brand(.headline)).foregroundStyle(site.active ? Color.primary : .secondary)
                                if !site.active { Text("Inactive").font(.brand(.caption2)).foregroundStyle(.orange) }
                            }
                            Text([site.type, site.address].compactMap { $0 }.joined(separator: " · "))
                                .font(.brand(.caption)).foregroundStyle(.secondary)
                        }
                    }
                    .swipeActions {
                        Button(site.active ? "Deactivate" : "Activate") {
                            Task { await save(site, active: !site.active) }
                        }.tint(site.active ? .orange : .green)
                    }
                }
            }
            .searchable(text: $search)
            .overlay { if data == nil && error == nil { ProgressView() } }
            .brandBackground()
            .navigationTitle("Clinical Sites")
            .toolbar { Button("Add", systemImage: "plus") { adding = true } }
            .sheet(item: $editing) { site in
                SiteForm(site: site, types: data?.types ?? []) { await load() }
            }
            .sheet(isPresented: $adding) {
                SiteForm(site: nil, types: data?.types ?? []) { await load() }
            }
            .refreshable { await load() }
            .task { await load() }
        }
    }

    private func load() async {
        do { data = try await session.api.adminSites(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }

    private func save(_ s: AdminSite, active: Bool) async {
        do {
            try await session.api.saveSite(id: s.id, name: s.name, address: s.address, type: s.type, active: active)
            await load()
        } catch { self.error = error.localizedDescription }
    }
}

struct SiteForm: View {
    @Environment(Session.self) private var session
    @Environment(\.dismiss) private var dismiss
    let site: AdminSite?
    let types: [String]
    let onSaved: () async -> Void
    @State private var name = ""
    @State private var address = ""
    @State private var type = ""
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Form {
                TextField("Name", text: $name)
                TextField("Address", text: $address)
                Picker("Type", selection: $type) {
                    Text("None").tag("")
                    ForEach(types, id: \.self) { Text($0).tag($0) }
                }
                if let error { Text(error).foregroundStyle(.red) }
            }
            .brandBackground()
            .navigationTitle(site == nil ? "Add Site" : "Edit Site")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") { Task { await save() } }.disabled(name.isEmpty)
                }
            }
            .onAppear {
                name = site?.name ?? ""
                address = site?.address ?? ""
                type = site?.type ?? ""
            }
        }
    }

    private func save() async {
        do {
            try await session.api.saveSite(id: site?.id, name: name,
                                           address: address.isEmpty ? nil : address,
                                           type: type.isEmpty ? nil : type)
            await onSaved()
            dismiss()
        } catch { self.error = error.localizedDescription }
    }
}

struct AdminStaffView: View {
    @Environment(Session.self) private var session
    @State private var staff: [StaffMember] = []
    @State private var error: String?
    @State private var adding = false
    @State private var notice: String?
    @State private var toDelete: StaffMember?

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                ForEach(staff) { m in
                    VStack(alignment: .leading, spacing: 2) {
                        HStack {
                            Text(m.name).font(.brand(.headline))
                            if !m.active { Text("Inactive").font(.brand(.caption2)).foregroundStyle(.orange) }
                        }
                        Text(m.email).font(.brand(.subheadline)).foregroundStyle(.secondary)
                        if !m.assignments.isEmpty {
                            Text(m.assignments.joined(separator: ", ")).font(.brand(.caption)).foregroundStyle(.secondary)
                        }
                    }
                    .swipeActions {
                        Button("Delete", role: .destructive) { toDelete = m }
                        Button("Reset PW") { Task { await reset(m) } }.tint(.blue)
                        Button(m.active ? "Deactivate" : "Activate") { Task { await toggle(m) } }
                            .tint(m.active ? .orange : .green)
                    }
                }
            }
            .overlay { if staff.isEmpty && error == nil { ContentUnavailableView("No staff yet", systemImage: "person.2") } }
            .brandBackground()
            .navigationTitle("Staff")
            .toolbar {
                ToolbarItem(placement: .topBarLeading) {
                    NavigationLink("Assignments") { AdminAssignmentsView() }
                }
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Add", systemImage: "plus") { adding = true }
                }
            }
            .sheet(isPresented: $adding) {
                StaffForm { temp, name in notice = "Created \(name). Temporary password: \(temp)"; await load() }
            }
            .alert("Temporary password", isPresented: .constant(notice != nil)) {
                Button("OK") { notice = nil }
            } message: { Text((notice ?? "") + "\nThe staff member must change it on first login.") }
            .confirmationDialog("Permanently remove \(toDelete?.name ?? "")?",
                                isPresented: .constant(toDelete != nil), titleVisibility: .visible) {
                Button("Delete", role: .destructive) {
                    if let m = toDelete { Task { await remove(m) } }
                    toDelete = nil
                }
                Button("Cancel", role: .cancel) { toDelete = nil }
            }
            .refreshable { await load() }
            .task { await load() }
        }
    }

    private func load() async {
        do { staff = try await session.api.adminStaff(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }
    private func reset(_ m: StaffMember) async {
        do { notice = "\(m.name): \(try await session.api.resetStaff(id: m.id))" }
        catch { self.error = error.localizedDescription }
    }
    private func toggle(_ m: StaffMember) async {
        do { try await session.api.toggleStaff(id: m.id); await load() }
        catch { self.error = error.localizedDescription }
    }
    private func remove(_ m: StaffMember) async {
        do { try await session.api.deleteStaff(id: m.id); await load() }
        catch { self.error = error.localizedDescription }
    }
}

struct StaffForm: View {
    @Environment(Session.self) private var session
    @Environment(\.dismiss) private var dismiss
    let onCreated: (String, String) async -> Void
    @State private var name = ""
    @State private var email = ""
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Form {
                TextField("Name", text: $name)
                TextField("Email", text: $email)
                    .keyboardType(.emailAddress)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                if let error { Text(error).foregroundStyle(.red) }
            }
            .brandBackground()
            .navigationTitle("Add Staff")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Create") { Task { await create() } }.disabled(name.isEmpty || email.isEmpty)
                }
            }
        }
    }

    private func create() async {
        do {
            let temp = try await session.api.createStaff(name: name, email: email)
            dismiss()
            await onCreated(temp, name)
        } catch { self.error = error.localizedDescription }
    }
}

struct StatTile: View {
    let title: String
    let value: Int
    let color: Color

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("\(value)")
                .font(.brand(.largeTitle, weight: .bold))
                .foregroundStyle(color)
                .monospacedDigit()
            Text(title).font(.brand(.subheadline, weight: .semibold)).foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(Theme.card, in: RoundedRectangle(cornerRadius: 16))
        .overlay(alignment: .topTrailing) {
            Circle().fill(color.opacity(0.18)).frame(width: 12, height: 12).padding(14)
        }
    }
}
