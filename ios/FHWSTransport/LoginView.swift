import SwiftUI

struct LoginView: View {
    @Environment(Session.self) private var session
    @State private var email = ""
    @State private var password = ""
    @State private var error: String?
    @State private var busy = false

    var body: some View {
        @Bindable var session = session
        NavigationStack {
            Form {
                Section {
                    Text("CPUT Faculty of Health & Wellness Sciences")
                        .font(.headline)
                    Text("Clinical placement transport requests")
                        .foregroundStyle(.secondary)
                }
                Section("Account") {
                    TextField("Email", text: $email)
                        .textContentType(.username)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                    SecureField("Password", text: $password)
                        .textContentType(.password)
                }
                Section("Server") {
                    TextField("Server URL", text: $session.baseURL)
                        .keyboardType(.URL)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                }
                if let error {
                    Section { Text(error).foregroundStyle(.red) }
                }
                Section {
                    Button {
                        Task { await signIn() }
                    } label: {
                        if busy { ProgressView() } else { Text("Sign In").frame(maxWidth: .infinity) }
                    }
                    .disabled(busy || email.isEmpty || password.isEmpty)
                }
            }
            .navigationTitle("Transport")
        }
    }

    private func signIn() async {
        busy = true
        error = nil
        defer { busy = false }
        do {
            try await session.signIn(email: email, password: password)
        } catch {
            self.error = error.localizedDescription
        }
    }
}
