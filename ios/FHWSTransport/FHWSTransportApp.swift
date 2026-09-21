import SwiftUI

@main
struct FHWSTransportApp: App {
    @State private var session = Session()
    @State private var calendar = CalendarService()

    init() { }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(session)
                .environment(calendar)
                .tint(Theme.primary)
                .font(.brand(.body))
        }
    }
}
