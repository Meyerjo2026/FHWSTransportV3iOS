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
