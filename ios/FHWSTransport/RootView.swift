import SwiftUI

struct RootView: View {
    @Environment(Session.self) private var session
    @State private var restoring = true

    var body: some View {
        Group {
            if restoring && session.hasToken {
                ProgressView("Loading…")
            } else if let user = session.user {
                if user.mustChangePassword {
                    ChangePasswordView(forced: true)
                } else if user.role == "student" {
                    StudentHome()
                } else {
                    ApprovalsHome()
                }
            } else {
                LoginView()
            }
        }
        .task {
            await session.restore()
            restoring = false
        }
    }
}
