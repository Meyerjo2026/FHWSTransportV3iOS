import SwiftUI
import UIKit

/// Brand styling shared with the web app: CPUT purple, Open Sans, pill buttons.
enum Theme {
    static let primary = Color(red: 0.600, green: 0.200, blue: 0.600)      // #993399
    static let primaryDark = Color(red: 0.502, green: 0.169, blue: 0.502)  // #802B80
    static let green = Color(red: 0.141, green: 0.541, blue: 0.239)        // #248A3D
    static let red = Color(red: 1.0, green: 0.231, blue: 0.188)            // #FF3B30
    static let amber = Color(red: 1.0, green: 0.584, blue: 0.0)            // #FF9500
    static let blue = Color(red: 0.0, green: 0.443, blue: 0.890)

    static let background = Color(uiColor: UIColor { $0.userInterfaceStyle == .dark
        ? UIColor(red: 0.07, green: 0.07, blue: 0.08, alpha: 1)
        : UIColor(red: 0.961, green: 0.961, blue: 0.969, alpha: 1) })   // #F5F5F7
    static let card = Color(uiColor: UIColor { $0.userInterfaceStyle == .dark
        ? UIColor(red: 0.11, green: 0.11, blue: 0.12, alpha: 1) : .white })

    static let gradient = LinearGradient(colors: [primaryDark, primary, Color(red: 0.72, green: 0.30, blue: 0.72)],
                                         startPoint: .topLeading, endPoint: .bottomTrailing)

    static func statusColor(_ status: String) -> Color {
        switch status {
        case "approved": green
        case "rejected": red
        case "finalised": blue
        default: amber
        }
    }

    /// Point size per text style (matches the system defaults) so Open Sans scales with Dynamic Type.
    private static func size(_ style: Font.TextStyle) -> CGFloat {
        switch style {
        case .largeTitle: 34
        case .title: 28
        case .title2: 22
        case .title3: 20
        case .headline: 17
        case .body: 17
        case .callout: 16
        case .subheadline: 15
        case .footnote: 13
        case .caption: 12
        case .caption2: 11
        default: 17
        }
    }

    static func font(_ style: Font.TextStyle, weight: Font.Weight? = nil) -> Font {
        let w: Font.Weight = weight ?? (style == .headline ? .semibold : .regular)
        return Font.custom("Open Sans", size: size(style), relativeTo: style).weight(w)
    }
}

extension Font {
    static func brand(_ style: Font.TextStyle, weight: Font.Weight? = nil) -> Font { Theme.font(style, weight: weight) }
}

/// Full-width rounded pill button, like the web app's primary buttons.
struct PillButtonStyle: ButtonStyle {
    var tint: Color = Theme.primary
    @Environment(\.isEnabled) private var enabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.brand(.body, weight: .semibold))
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 13)
            .background(tint.opacity(enabled ? (configuration.isPressed ? 0.8 : 1) : 0.35), in: Capsule())
    }
}

extension ButtonStyle where Self == PillButtonStyle {
    static var pill: PillButtonStyle { PillButtonStyle() }
}

/// Card look for list sections: white rounded rows on the soft grey page.
struct BrandBackground: ViewModifier {
    func body(content: Content) -> some View {
        content
            .scrollContentBackground(.hidden)
            .background(Theme.background)
    }
}

extension View {
    func brandBackground() -> some View { modifier(BrandBackground()) }
}
