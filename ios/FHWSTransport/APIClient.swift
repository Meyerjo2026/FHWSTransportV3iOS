import Foundation

enum APIError: LocalizedError {
    case unauthorized
    case server(String)
    case badURL

    var errorDescription: String? {
        switch self {
        case .unauthorized: "Your session has expired. Please sign in again."
        case .server(let m): m
        case .badURL: "The server address is not a valid URL."
        }
    }
}

struct APIClient {
    let baseURL: String
    let token: String?

    private struct LoginBody: Encodable { let email, password, device_name: String }
    private struct StatusBody: Encodable { let status: String }
    private struct PasswordBody: Encodable {
        let current_password, password, password_confirmation: String
    }
    private struct OK: Decodable { let ok: Bool }
    private struct ErrorBody: Decodable { let message: String? }

    func login(email: String, password: String) async throws -> LoginResponse {
        try await send("POST", "login", body: LoginBody(email: email, password: password, device_name: "iOS"))
    }
    func logout() async throws -> Bool { (try await send("POST", "logout") as OK).ok }
    func me() async throws -> User { try await send("GET", "me") }
    func options() async throws -> TripOptions { try await send("GET", "options") }
    func myRequests() async throws -> [TripRequest] { try await send("GET", "requests") }
    func submit(_ r: NewRequest) async throws -> TripRequest { try await send("POST", "requests", body: r) }
    func approvals() async throws -> Approvals { try await send("GET", "approvals") }
    func setStatus(id: Int, status: String) async throws -> TripRequest {
        try await send("POST", "requests/\(id)/status", body: StatusBody(status: status))
    }
    func changePassword(current: String, new: String) async throws {
        let _: OK = try await send("POST", "password",
            body: PasswordBody(current_password: current, password: new, password_confirmation: new))
    }

    private func send<T: Decodable>(_ method: String, _ path: String, body: (some Encodable)? = nil as String?) async throws -> T {
        guard let url = URL(string: baseURL.trimmingCharacters(in: CharacterSet(charactersIn: "/ ")) + "/api/" + path) else {
            throw APIError.badURL
        }
        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        if let token { req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }
        if let body {
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONEncoder().encode(body)
        }
        let (data, resp) = try await URLSession.shared.data(for: req)
        let code = (resp as? HTTPURLResponse)?.statusCode ?? 0
        if code == 401 && path != "login" { throw APIError.unauthorized }
        guard (200..<300).contains(code) else {
            let msg = (try? JSONDecoder().decode(ErrorBody.self, from: data))?.message
            throw APIError.server(msg ?? "Server error (\(code)).")
        }
        return try JSONDecoder().decode(T.self, from: data)
    }
}
