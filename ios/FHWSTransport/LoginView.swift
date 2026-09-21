import SwiftUI

struct LoginView: View {
    @Environment(Session.self) private var session
    @State private var email = ""
    @State private var password = ""
    @State private var error: String?
    @State private var busy = false
    @State private var showServer = false
    @FocusState private var focus: Field?

    private enum Field { case email, password, server }

    var body: some View {
        @Bindable var session = session
        ScrollView {
            VStack(spacing: 0) {
                header
                VStack(spacing: 14) {
                    field("Email", systemImage: "envelope") {
                        TextField("name@cput.ac.za", text: $email)
                            .textContentType(.username)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .focused($focus, equals: .email)
                            .submitLabel(.next)
                            .onSubmit { focus = .password }
                    }
                    field("Password", systemImage: "lock") {
                        SecureField("Password", text: $password)
                            .textContentType(.password)
                            .focused($focus, equals: .password)
                            .submitLabel(.go)
                            .onSubmit { Task { await signIn() } }
                    }

                    if let error {
                        Label(error, systemImage: "exclamationmark.triangle.fill")
                            .font(.brand(.footnote))
                            .foregroundStyle(Theme.red)
                            .frame(maxWidth: .infinity, alignment: .leading)
                    }

                    Button {
                        Task { await signIn() }
                    } label: {
                        if busy { ProgressView().tint(.white) } else { Text("Sign In") }
                    }
                    .buttonStyle(.pill)
                    .disabled(busy || email.isEmpty || password.isEmpty)
                    .padding(.top, 6)

                    DisclosureGroup("Server", isExpanded: $showServer) {
                        TextField("Server URL", text: $session.baseURL)
                            .keyboardType(.URL)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .focused($focus, equals: .server)
                            .padding(12)
                            .background(Theme.card, in: RoundedRectangle(cornerRadius: 12))
                    }
                    .font(.brand(.footnote))
                    .foregroundStyle(.secondary)
                    .padding(.top, 8)
                }
                .padding(24)
            }
        }
        .ignoresSafeArea(edges: .top)
        .scrollDismissesKeyboard(.interactively)
        .background(Theme.background)
    }

    private var header: some View {
        ZStack(alignment: .bottomLeading) {
            Theme.gradient
            Circle().fill(.white.opacity(0.08)).frame(width: 260).offset(x: 190, y: -60)
            Circle().fill(.white.opacity(0.06)).frame(width: 180).offset(x: -70, y: 40)
            VStack(alignment: .leading, spacing: 10) {
                Image(systemName: "bus.fill")
                    .font(.system(size: 30))
                    .padding(14)
                    .background(.white.opacity(0.18), in: RoundedRectangle(cornerRadius: 16))
                Text("Clinical Placement\nTransport")
                    .font(.brand(.largeTitle, weight: .bold))
                Text("CPUT Faculty of Health & Wellness Sciences")
                    .font(.brand(.subheadline))
                    .opacity(0.85)
            }
            .foregroundStyle(.white)
            .padding(24)
            .padding(.bottom, 8)
        }
        .frame(height: 320)
        .clipShape(UnevenRoundedRectangle(bottomLeadingRadius: 32, bottomTrailingRadius: 32))
    }

    private func field<Content: View>(_ title: String, systemImage: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title).font(.brand(.footnote, weight: .semibold)).foregroundStyle(.secondary)
            HStack(spacing: 10) {
                Image(systemName: systemImage).foregroundStyle(Theme.primary).frame(width: 20)
                content()
            }
            .padding(14)
            .background(Theme.card, in: RoundedRectangle(cornerRadius: 14))
            .overlay(RoundedRectangle(cornerRadius: 14).stroke(.black.opacity(0.06)))
        }
    }

    private func signIn() async {
        guard !busy, !email.isEmpty, !password.isEmpty else { return }
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
