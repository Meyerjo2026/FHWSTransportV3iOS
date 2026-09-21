import Foundation
import Security
import Observation

/// Persists the API token in the Keychain and the server URL in UserDefaults.
@Observable
final class Session {
    var user: User?
    var baseURL: String {
        didSet { UserDefaults.standard.set(baseURL, forKey: "baseURL") }
    }
    private(set) var token: String?

    init() {
        baseURL = UserDefaults.standard.string(forKey: "baseURL") ?? "https://fhws-transport.test"
        token = Keychain.read()
    }

    var isSignedIn: Bool { token != nil && user != nil }
    var hasToken: Bool { token != nil }

    var api: APIClient { APIClient(baseURL: baseURL, token: token) }

    func signIn(email: String, password: String) async throws {
        let res = try await api.login(email: email, password: password)
        Keychain.write(res.token)
        token = res.token
        user = res.user
    }

    func restore() async {
        guard token != nil else { return }
        do { user = try await api.me() } catch APIError.unauthorized { clear() } catch {}
    }

    func signOut() async {
        _ = try? await api.logout()
        clear()
    }

    func clear() {
        Keychain.delete()
        token = nil
        user = nil
    }
}

enum Keychain {
    private static let query: [String: Any] = [
        kSecClass as String: kSecClassGenericPassword,
        kSecAttrService as String: "za.ac.cput.fhws.transport",
        kSecAttrAccount as String: "api-token",
    ]

    static func read() -> String? {
        var q = query
        q[kSecReturnData as String] = true
        var out: AnyObject?
        guard SecItemCopyMatching(q as CFDictionary, &out) == errSecSuccess, let d = out as? Data else { return nil }
        return String(data: d, encoding: .utf8)
    }

    static func write(_ token: String) {
        delete()
        var q = query
        q[kSecValueData as String] = Data(token.utf8)
        SecItemAdd(q as CFDictionary, nil)
    }

    static func delete() { SecItemDelete(query as CFDictionary) }
}
