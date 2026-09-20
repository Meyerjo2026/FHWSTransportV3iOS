import SwiftUI

struct AccountView: View {
    @Environment(Session.self) private var session

    var body: some View {
        NavigationStack {
            Form {
                if let u = session.user {
                    Section {
                        LabeledContent("Name", value: u.name)
                        LabeledContent("Email", value: u.email)
                        if let n = u.number { LabeledContent("Number", value: n) }
                        LabeledContent("Role", value: u.role.capitalized)
                    }
                }
                Section {
                    NavigationLink("Change Password") { ChangePasswordView(forced: false) }
                }
                Section {
                    Button("Sign Out", role: .destructive) { Task { await session.signOut() } }
                }
            }
            .navigationTitle("Account")
        }
    }
}

struct ChangePasswordView: View {
    @Environment(Session.self) private var session
    @Environment(\.dismiss) private var dismiss
    let forced: Bool
    @State private var current = ""
    @State private var new = ""
    @State private var confirm = ""
    @State private var error: String?
    @State private var busy = false

    var body: some View {
        Form {
            if forced {
                Section { Text("You must set a new password before continuing.") }
            }
            Section {
                SecureField("Current password", text: $current)
                SecureField("New password", text: $new)
                SecureField("Confirm new password", text: $confirm)
            }
            if let error { Section { Text(error).foregroundStyle(.red) } }
            Section {
                Button("Update Password") { Task { await save() } }
                    .disabled(busy || current.isEmpty || new.count < 8 || new != confirm)
                if forced {
                    Button("Sign Out", role: .destructive) { Task { await session.signOut() } }
                }
            }
        }
        .navigationTitle("Change Password")
    }

    private func save() async {
        busy = true
        defer { busy = false }
        do {
            try await session.api.changePassword(current: current, new: new)
            session.user = try await session.api.me()
            if !forced { dismiss() }
        } catch {
            self.error = error.localizedDescription
        }
    }
}
