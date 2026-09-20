import Foundation

struct User: Codable, Identifiable {
    let id: Int
    let name: String
    let email: String
    let number: String?
    let role: String
    let mustChangePassword: Bool

    enum CodingKeys: String, CodingKey {
        case id, name, email, number, role
        case mustChangePassword = "must_change_password"
    }
}

struct LoginResponse: Decodable {
    let token: String
    let user: User
}

struct TripRequest: Decodable, Identifiable {
    let id: Int
    let studentName: String?
    let studentNumber: String?
    let site: String?
    let date: String
    let time: String
    let department: String?
    let qualification: String?
    let year: String?
    let notes: String?
    let status: String
    let responsibleStaff: [String]?

    enum CodingKeys: String, CodingKey {
        case id, site, date, time, department, qualification, year, notes, status
        case studentName = "student_name"
        case studentNumber = "student_number"
        case responsibleStaff = "responsible_staff"
    }
}

struct Approvals: Decodable {
    let pending: [TripRequest]
    let recent: [TripRequest]
}

struct Site: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let address: String?
    let type: String?
}

struct TripOptions: Decodable {
    let sites: [Site]
    let timeSlots: [String]
    let departments: [String]
    let qualificationsByDepartment: [String: [String]]
    let yearsByQualification: [String: [String]]
    let pickupPoint: String

    enum CodingKeys: String, CodingKey {
        case sites, departments
        case timeSlots = "time_slots"
        case qualificationsByDepartment = "qualifications_by_department"
        case yearsByQualification = "years_by_qualification"
        case pickupPoint = "pickup_point"
    }
}

struct NewRequest: Encodable {
    let clinicalSiteId: Int
    let date: String
    let time: String
    let department: String
    let qualification: String
    let year: String
    let notes: String?

    enum CodingKeys: String, CodingKey {
        case date, time, department, qualification, year, notes
        case clinicalSiteId = "clinical_site_id"
    }
}

struct AdminDashboard: Decodable {
    struct Row: Decodable, Identifiable { let label: String; let total: Int; var id: String { label } }
    let total: Int
    let statusCounts: [String: Int]
    let awaitingQuote: Int
    let byDepartment: [Row]
    let recent: [TripRequest]

    enum CodingKeys: String, CodingKey {
        case total, recent
        case statusCounts = "status_counts"
        case awaitingQuote = "awaiting_quote"
        case byDepartment = "by_department"
    }
}

struct AdminSite: Decodable, Identifiable {
    let id: Int
    let name: String
    let address: String?
    let type: String?
    let active: Bool
}

struct SitesResponse: Decodable {
    let sites: [AdminSite]
    let types: [String]
}

/// Response shape of site create/update (only the id is used).
struct Site2: Decodable { let id: Int }

struct StaffMember: Decodable, Identifiable {
    let id: Int
    let name: String
    let email: String
    let active: Bool
    let assignments: [String]
}

struct JourneySuggestion: Decodable, Identifiable {
    let key: String
    let date: String
    let time: String
    let studentCount: Int
    let maxSpanKm: Double
    let totalKm: Double
    let stops: [String]
    let tripRequestIds: [Int]
    var id: String { key }

    enum CodingKeys: String, CodingKey {
        case key, date, time, stops
        case studentCount = "student_count"
        case maxSpanKm = "max_span_km"
        case totalKm = "total_km"
        case tripRequestIds = "trip_request_ids"
    }
}

struct ActiveJourney: Decodable, Identifiable {
    let id: Int
    let label: String
    let date: String
    let time: String
    let tripCount: Int
    let locked: Bool

    enum CodingKeys: String, CodingKey {
        case id, label, date, time, locked
        case tripCount = "trip_count"
    }
}

struct JourneyPlan: Decodable {
    let threshold: Double
    let maxPerTrip: Int
    let eligible: Int
    let suggestions: [JourneySuggestion]
    let active: [ActiveJourney]

    enum CodingKeys: String, CodingKey {
        case threshold, eligible, suggestions, active
        case maxPerTrip = "max_per_trip"
    }
}

struct QuoteSummary: Decodable, Identifiable {
    let id: Int
    let ref: String
    let period: String
    let date: String
    let isTbc: Bool
    let rate: Double?
    let total: Double?

    enum CodingKeys: String, CodingKey {
        case id, ref, period, date, rate, total
        case isTbc = "is_tbc"
    }
}

struct QuoteLine: Decodable, Identifiable {
    let date: String
    let time: String
    let site: String
    let part: String?
    let qty: Int
    let unitPrice: Double?
    let extended: Double?
    var id: String { "\(date)|\(time)|\(site)|\(part ?? "")" }

    enum CodingKeys: String, CodingKey {
        case date, time, site, part, qty, extended
        case unitPrice = "unit_price"
    }
}

struct QuotesOverview: Decodable {
    let defaultRate: Double
    let awaitingTrips: Int
    let awaitingLines: [QuoteLine]
    let quotes: [QuoteSummary]

    enum CodingKeys: String, CodingKey {
        case quotes
        case defaultRate = "default_rate"
        case awaitingTrips = "awaiting_trips"
        case awaitingLines = "awaiting_lines"
    }
}

struct QuoteDetail: Decodable {
    let id: Int
    let ref: String
    let period: String
    let date: String
    let isTbc: Bool
    let rate: Double?
    let total: Double?
    let lines: [QuoteLine]

    enum CodingKeys: String, CodingKey {
        case id, ref, period, date, rate, total, lines
        case isTbc = "is_tbc"
    }

    /// Plain-text copy for the share sheet.
    var shareText: String {
        var out = "Request for Quote \(ref)\nHG Travelling Services · Bellville Campus\nDate: \(date)\nPeriod: \(period)\n\n"
        for (i, l) in lines.enumerated() {
            let part = l.part.map { " (\($0))" } ?? ""
            let price = l.extended.map { " — " + Money.format($0) } ?? " — to be calculated"
            out += "\(i + 1). \(l.date) \(l.time) - \(l.site) and return - 7-Seater\(part) × \(l.qty)\(price)\n"
        }
        out += "\nTotal (ZAR): " + (total.map(Money.format) ?? "To be calculated")
        return out
    }
}

enum Money {
    static func format(_ v: Double) -> String { "R " + v.formatted(.number.precision(.fractionLength(2))) }
}
