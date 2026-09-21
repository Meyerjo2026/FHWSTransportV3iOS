import SwiftUI

/// Which staff member is responsible for each year group, department and
/// qualification. Staff only see (and approve) requests in their groups.
struct AdminAssignmentsView: View {
    @Environment(Session.self) private var session
    @State private var data: AssignmentsResponse?
    @State private var type = "year"
    @State private var error: String?
    @State private var saving: String?

    private var section: AssignmentSection? { data?.sections.first { $0.type == type } }

    var body: some View {
        List {
            if let error { Text(error).font(.brand(.footnote)).foregroundStyle(Theme.red) }
            if let data, let section {
                Section {
                    Picker("Group type", selection: $type) {
                        ForEach(data.sections) { Text($0.label).tag($0.type) }
                    }
                    .pickerStyle(.segmented)
                    .listRowBackground(Color.clear)
                    .listRowInsets(EdgeInsets())
                }
                Section {
                    ForEach(section.options) { option in
                        HStack {
                            Text(option.value).font(.brand(.subheadline))
                            Spacer(minLength: 8)
                            if saving == option.id { ProgressView() }
                            Picker("", selection: binding(for: option)) {
                                Text("Unassigned").tag(Int?.none)
                                ForEach(data.staff) { Text($0.name).tag(Int?.some($0.id)) }
                            }
                            .labelsHidden()
                            .tint(option.staffId == nil ? .secondary : Theme.primary)
                        }
                    }
                } footer: {
                    Text("Requests with none of these fields set are visible to all staff.")
                }
            }
        }
        .overlay { if data == nil && error == nil { ProgressView() } }
        .brandBackground()
        .navigationTitle("Assignments")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await load() }
        .task { await load() }
    }

    private func binding(for option: AssignmentOption) -> Binding<Int?> {
        Binding(
            get: { option.staffId },
            set: { new in Task { await set(option, to: new) } }
        )
    }

    private func set(_ option: AssignmentOption, to staffID: Int?) async {
        guard staffID != option.staffId else { return }
        saving = option.id
        defer { saving = nil }
        do {
            try await session.api.assign(type: type, value: option.value, staffID: staffID)
            await load()
        } catch APIError.unauthorized {
            session.clear()
        } catch {
            self.error = error.localizedDescription
            await load()
        }
    }

    private func load() async {
        do { data = try await session.api.assignments(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }
}
