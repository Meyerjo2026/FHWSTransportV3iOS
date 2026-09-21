import SwiftUI

struct StudentHome: View {
    @State private var refresh = 0

    var body: some View {
        TabView {
            NewRequestView(onSubmitted: { refresh += 1 })
                .tabItem { Label("Request", systemImage: "plus.circle") }
            MyRequestsView(refresh: refresh)
                .tabItem { Label("My Requests", systemImage: "list.bullet") }
            AccountView()
                .tabItem { Label("Account", systemImage: "person.crop.circle") }
        }
    }
}

struct MyRequestsView: View {
    @Environment(Session.self) private var session
    @Environment(CalendarService.self) private var calendar
    @Environment(\.openURL) private var openURL
    let refresh: Int
    @State private var link: CalendarLink?
    @State private var confirmReset = false
    @State private var trips: [TripRequest] = []
    @State private var error: String?
    @State private var notice: String?
    @State private var loaded = false

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).font(.brand(.footnote)).foregroundStyle(Theme.red) }
                if let notice { Label(notice, systemImage: "calendar.badge.checkmark").font(.brand(.footnote)).foregroundStyle(Theme.green) }
                syncCard
                ForEach(trips) { trip in
                    VStack(alignment: .leading, spacing: 8) {
                        TripRow(trip: trip)
                        if trip.status == "approved" || trip.status == "finalised" {
                            calendarButton(trip)
                        }
                    }
                }
            }
            .overlay {
                if loaded && trips.isEmpty && error == nil {
                    ContentUnavailableView("No requests yet", systemImage: "bus",
                        description: Text("Submit a trip request from the Request tab."))
                }
            }
            .brandBackground()
            .navigationTitle("My Requests")
            .toolbar {
                let pending = calendar.pending(trips)
                if !pending.isEmpty {
                    ToolbarItem(placement: .topBarTrailing) {
                        Button("Add \(pending.count) to Calendar", systemImage: "calendar.badge.plus") {
                            Task { await add(pending) }
                        }
                    }
                }
            }
            .refreshable { await load() }
            .task(id: refresh) { await load() }
        }
    }

    private var syncCard: some View {
        Section {
            VStack(alignment: .leading, spacing: 10) {
                Label("Keep your calendar up to date", systemImage: "calendar.badge.clock")
                    .font(.brand(.headline))
                Text("Subscribe once and approved trips appear in Calendar automatically. If a trip is rejected or changed, it updates or disappears on the next refresh.")
                    .font(.brand(.footnote)).foregroundStyle(.secondary)
                Button("Subscribe in Calendar") {
                    Task { await subscribe() }
                }
                .buttonStyle(.pill)
                HStack(spacing: 16) {
                    if let link { ShareLink("Share link", item: link.url) }
                    if link != nil { Button("Reset link") { confirmReset = true } }
                }
                .font(.brand(.caption, weight: .semibold))
                .buttonStyle(.borderless)
                Text("Use either subscribing or the Add to Calendar buttons below, not both, or trips show twice.")
                    .font(.brand(.caption2)).foregroundStyle(.secondary)
            }
            .padding(.vertical, 4)
        }
        .confirmationDialog("Reset your calendar link?", isPresented: $confirmReset, titleVisibility: .visible) {
            Button("Reset link", role: .destructive) { Task { await resetLink() } }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("The old link stops working. You'll need to subscribe again.")
        }
    }

    private func subscribe() async {
        error = nil
        do {
            let l = try await session.api.calendarLink()
            link = l
            if let url = URL(string: l.webcalUrl) { openURL(url) }
        } catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }

    private func resetLink() async {
        do {
            link = try await session.api.rotateCalendarLink()
            notice = "Link reset. Remove the old calendar in Calendar ▸ Calendars and subscribe again."
        } catch { self.error = error.localizedDescription }
    }

    @ViewBuilder
    private func calendarButton(_ trip: TripRequest) -> some View {
        if calendar.isAdded(trip) {
            Label("On your calendar", systemImage: "calendar.badge.checkmark")
                .font(.brand(.caption, weight: .semibold)).foregroundStyle(Theme.green)
        } else {
            Button { Task { await add([trip]) } } label: {
                Label("Add this trip once", systemImage: "calendar.badge.plus")
                    .font(.brand(.caption, weight: .semibold))
            }
            .buttonStyle(.borderless)
        }
    }

    private func add(_ list: [TripRequest]) async {
        error = nil; notice = nil
        do {
            let n = try await calendar.add(list)
            notice = n == 1 ? "Added 1 trip to your calendar." : "Added \(n) trips to your calendar."
        } catch { self.error = error.localizedDescription }
    }

    private func load() async {
        do {
            trips = try await session.api.myRequests()
            error = nil
        } catch APIError.unauthorized {
            session.clear()
        } catch {
            self.error = error.localizedDescription
        }
        loaded = true
    }
}

struct NewRequestView: View {
    @Environment(Session.self) private var session
    let onSubmitted: () -> Void

    @State private var options: TripOptions?
    @State private var siteID: Int?
    @State private var date = Calendar.current.date(byAdding: .day, value: 1, to: .now) ?? .now
    @State private var time = ""
    @State private var department = ""
    @State private var qualification = ""
    @State private var year = ""
    @State private var notes = ""
    @State private var message: String?
    @State private var isError = false
    @State private var busy = false

    private var qualifications: [String] { options?.qualificationsByDepartment[department] ?? [] }
    private var years: [String] { options?.yearsByQualification[qualification] ?? [] }
    private var canSubmit: Bool {
        siteID != nil && !time.isEmpty && !department.isEmpty && !qualification.isEmpty && !year.isEmpty && !busy
    }

    var body: some View {
        NavigationStack {
            Form {
                if let options {
                    Section("Trip") {
                        Picker("Clinical site", selection: $siteID) {
                            Text("Select…").tag(Int?.none)
                            ForEach(options.sites) { Text($0.name).tag(Int?.some($0.id)) }
                        }
                        DatePicker("Date", selection: $date, in: Date.now..., displayedComponents: .date)
                        Picker("Time slot", selection: $time) {
                            Text("Select…").tag("")
                            ForEach(options.timeSlots, id: \.self) { Text($0).tag($0) }
                        }
                        LabeledContent("Pickup", value: options.pickupPoint)
                            .font(.brand(.footnote))
                    }
                    Section("Programme") {
                        Picker("Department", selection: $department) {
                            Text("Select…").tag("")
                            ForEach(options.departments, id: \.self) { Text($0).tag($0) }
                        }
                        .onChange(of: department) { qualification = ""; year = "" }
                        Picker("Qualification", selection: $qualification) {
                            Text("Select…").tag("")
                            ForEach(qualifications, id: \.self) { Text($0).tag($0) }
                        }
                        .disabled(department.isEmpty)
                        .onChange(of: qualification) { year = years.count == 1 ? years[0] : "" }
                        Picker("Year", selection: $year) {
                            Text("Select…").tag("")
                            ForEach(years, id: \.self) { Text($0).tag($0) }
                        }
                        .disabled(qualification.isEmpty)
                    }
                    Section("Notes") {
                        TextField("Optional", text: $notes, axis: .vertical)
                    }
                    if let message {
                        Section { Text(message).foregroundStyle(isError ? .red : .green) }
                    }
                    Section {
                        Button {
                            Task { await submit() }
                        } label: {
                            if busy { ProgressView().tint(.white) } else { Text("Submit Request") }
                        }
                        .buttonStyle(.pill)
                        .disabled(!canSubmit)
                        .listRowBackground(Color.clear)
                        .listRowInsets(EdgeInsets())
                    }
                } else if let message {
                    Text(message).foregroundStyle(.red)
                } else {
                    ProgressView()
                }
            }
            .brandBackground()
            .navigationTitle("New Request")
            .task { await loadOptions() }
        }
    }

    private func loadOptions() async {
        guard options == nil else { return }
        do { options = try await session.api.options() }
        catch APIError.unauthorized { session.clear() }
        catch { message = error.localizedDescription; isError = true }
    }

    private func submit() async {
        guard let siteID else { return }
        busy = true
        defer { busy = false }
        let f = DateFormatter()
        f.dateFormat = "yyyy-MM-dd"
        f.locale = Locale(identifier: "en_US_POSIX")
        let req = NewRequest(clinicalSiteId: siteID, date: f.string(from: date), time: time,
                             department: department, qualification: qualification, year: year,
                             notes: notes.isEmpty ? nil : notes)
        do {
            _ = try await session.api.submit(req)
            message = "Request submitted. Awaiting approval."
            isError = false
            notes = ""
            onSubmitted()
        } catch {
            message = error.localizedDescription
            isError = true
        }
    }
}
