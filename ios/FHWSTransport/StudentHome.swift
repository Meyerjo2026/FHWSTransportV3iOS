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
    let refresh: Int
    @State private var trips: [TripRequest] = []
    @State private var error: String?
    @State private var loaded = false

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                ForEach(trips) { TripRow(trip: $0) }
            }
            .overlay {
                if loaded && trips.isEmpty && error == nil {
                    ContentUnavailableView("No requests yet", systemImage: "bus",
                        description: Text("Submit a trip request from the Request tab."))
                }
            }
            .navigationTitle("My Requests")
            .refreshable { await load() }
            .task(id: refresh) { await load() }
        }
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
                            .font(.footnote)
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
                            if busy { ProgressView().frame(maxWidth: .infinity) }
                            else { Text("Submit Request").frame(maxWidth: .infinity) }
                        }
                        .disabled(!canSubmit)
                    }
                } else if let message {
                    Text(message).foregroundStyle(.red)
                } else {
                    ProgressView()
                }
            }
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
