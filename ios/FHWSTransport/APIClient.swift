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

    // MARK: Admin
    private struct BulkBody: Encodable { let status: String; let ids: [Int] }
    private struct SiteBody: Encodable { let name: String; let address: String?; let type: String?; let active: Bool? }
    private struct StaffBody: Encodable { let name, email: String }
    private struct Updated: Decodable { let updated: Int }
    private struct Temp: Decodable { let temporary_password: String }
    private struct Toggled: Decodable { let active: Bool }

    func adminDashboard() async throws -> AdminDashboard { try await send("GET", "admin/dashboard") }
    func adminReview(status: String?) async throws -> [TripRequest] {
        try await send("GET", "admin/review" + (status.map { "?status=\($0)" } ?? ""))
    }
    func bulkStatus(ids: [Int], status: String) async throws -> Int {
        (try await send("POST", "admin/review/bulk", body: BulkBody(status: status, ids: ids)) as Updated).updated
    }
    func adminSites() async throws -> SitesResponse { try await send("GET", "admin/sites") }
    func saveSite(id: Int?, name: String, address: String?, type: String?, active: Bool? = nil) async throws {
        let body = SiteBody(name: name, address: address, type: type, active: active)
        let _: Site2 = try await send("POST", id.map { "admin/sites/\($0)" } ?? "admin/sites", body: body)
    }
    func adminStaff() async throws -> [StaffMember] { try await send("GET", "admin/staff") }
    func createStaff(name: String, email: String) async throws -> String {
        (try await send("POST", "admin/staff", body: StaffBody(name: name, email: email)) as Temp).temporary_password
    }
    func resetStaff(id: Int) async throws -> String {
        (try await send("POST", "admin/staff/\(id)/reset-password") as Temp).temporary_password
    }
    func toggleStaff(id: Int) async throws { let _: Toggled = try await send("POST", "admin/staff/\(id)/toggle") }
    func deleteStaff(id: Int) async throws { let _: OK = try await send("DELETE", "admin/staff/\(id)") }

    private struct JourneyBody: Encodable {
        let trip_request_ids: [Int]; let date, time, label: String; let threshold: Double
    }
    func journeys(threshold: Double) async throws -> JourneyPlan {
        try await send("GET", "admin/journeys?threshold=\(threshold)")
    }
    func combine(_ s: JourneySuggestion, label: String, threshold: Double) async throws {
        let body = JourneyBody(trip_request_ids: s.tripRequestIds, date: s.date, time: s.time, label: label, threshold: threshold)
        let _: Created = try await send("POST", "admin/journeys", body: body)
    }
    func cancelJourney(id: Int) async throws { let _: OK = try await send("DELETE", "admin/journeys/\(id)") }
    private struct Created: Decodable { let id: Int }

    private struct QuoteBody: Encodable { let period, pricing: String; let rate: Double? }
    func quotes() async throws -> QuotesOverview { try await send("GET", "admin/quotes") }
    func quote(id: Int) async throws -> QuoteDetail { try await send("GET", "admin/quotes/\(id)") }
    func createQuote(period: String, rate: Double?) async throws -> Int {
        let body = QuoteBody(period: period, pricing: rate == nil ? "tbc" : "rate", rate: rate)
        return (try await send("POST", "admin/quotes", body: body) as QuoteSummary).id
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
