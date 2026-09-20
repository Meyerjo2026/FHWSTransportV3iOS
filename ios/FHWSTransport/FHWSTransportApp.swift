import SwiftUI

@main
struct FHWSTransportApp: App {
    @State private var session = Session()

    init() { }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(session)
                .tint(Theme.primary)
                .font(.brand(.body))
        }
    }
}
