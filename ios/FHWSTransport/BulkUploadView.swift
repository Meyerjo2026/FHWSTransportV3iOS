import SwiftUI
import UniformTypeIdentifiers

/// Landing list of the CSV uploads a role may run.
struct BulkHome: View {
    let kinds: [BulkKind]

    var body: some View {
        NavigationStack {
            List(kinds, id: \.rawValue) { kind in
                NavigationLink(value: kind) {
                    HStack(spacing: 14) {
                        Image(systemName: kind.icon)
                            .font(.system(size: 20))
                            .foregroundStyle(Theme.primary)
                            .frame(width: 44, height: 44)
                            .background(Theme.primary.opacity(0.10), in: RoundedRectangle(cornerRadius: 12))
                        VStack(alignment: .leading, spacing: 2) {
                            Text(kind.title).font(.brand(.headline))
                            Text(kind.summary).font(.brand(.caption)).foregroundStyle(.secondary).lineLimit(2)
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
            .brandBackground()
            .navigationTitle("Bulk Upload")
            .navigationDestination(for: BulkKind.self) { BulkUploadView(kind: $0) }
        }
    }
}

extension BulkKind: Hashable {}

struct BulkUploadView: View {
    @Environment(Session.self) private var session
    let kind: BulkKind

    @State private var picking = false
    @State private var fileName: String?
    @State private var csv: Data?
    @State private var header: [String] = []
    @State private var rowCount = 0
    @State private var error: String?
    @State private var busy = false
    @State private var trips: TripUploadResult?
    @State private var students: StudentUploadResult?
    @State private var sites: SiteUploadResult?

    private var missing: [String] { kind.required.filter { !header.contains($0) } }
    private var canUpload: Bool { csv != nil && rowCount > 0 && missing.isEmpty && !busy }

    var body: some View {
        List {
            Section {
                Text(kind.summary).font(.brand(.subheadline))
                LabeledContent("Columns") { Text(kind.columns).font(.brand(.caption)).multilineTextAlignment(.trailing) }
                ShareLink(item: kind.template, subject: Text("\(kind.rawValue)-template.csv"),
                          preview: SharePreview("\(kind.rawValue)-template.csv")) {
                    Label("Share template CSV", systemImage: "square.and.arrow.up")
                }
            }

            Section("File") {
                Button { picking = true } label: {
                    Label(fileName ?? "Choose CSV file…", systemImage: "doc.badge.plus")
                }
                if csv != nil {
                    Text("\(rowCount) data row\(rowCount == 1 ? "" : "s") · \(header.count) columns")
                        .font(.brand(.footnote)).foregroundStyle(.secondary)
                    if !missing.isEmpty {
                        Label("Missing required column\(missing.count == 1 ? "" : "s"): \(missing.joined(separator: ", "))",
                              systemImage: "exclamationmark.triangle.fill")
                            .font(.brand(.footnote)).foregroundStyle(Theme.red)
                    }
                }
                if let error { Text(error).font(.brand(.footnote)).foregroundStyle(Theme.red) }
                Button { Task { await upload() } } label: {
                    if busy { ProgressView().tint(.white) } else { Text("Upload") }
                }
                .buttonStyle(.pill)
                .disabled(!canUpload)
                .listRowBackground(Color.clear)
                .listRowInsets(EdgeInsets())
            }

            if let t = trips {
                Section("Result") {
                    Label("\(t.created) trip\(t.created == 1 ? "" : "s") uploaded and approved", systemImage: "checkmark.circle.fill")
                        .foregroundStyle(Theme.green)
                    if !t.unknownSites.isEmpty {
                        VStack(alignment: .leading, spacing: 2) {
                            Label("Sites not found (trips added without a map location):", systemImage: "mappin.slash")
                                .font(.brand(.footnote)).foregroundStyle(Theme.amber)
                            Text(t.unknownSites.joined(separator: ", ")).font(.brand(.caption)).foregroundStyle(.secondary)
                        }
                    }
                }
                skippedSection(t.skipped, total: t.skippedCount)
            }

            if let st = students {
                Section("Result") {
                    Label("\(st.created.count) account\(st.created.count == 1 ? "" : "s") created", systemImage: "checkmark.circle.fill")
                        .foregroundStyle(Theme.green)
                }
                if !st.created.isEmpty {
                    Section {
                        ForEach(st.created) { s in
                            VStack(alignment: .leading, spacing: 2) {
                                Text(s.name).font(.brand(.headline))
                                Text(s.email).font(.brand(.caption)).foregroundStyle(.secondary)
                                Text(s.password).font(.system(.callout, design: .monospaced)).textSelection(.enabled)
                            }
                        }
                        ShareLink(item: st.credentialsCSV, subject: Text("student-credentials.csv"),
                                  preview: SharePreview("student-credentials.csv")) {
                            Label("Share credentials", systemImage: "square.and.arrow.up")
                        }
                    } header: {
                        Text("Temporary passwords")
                    } footer: {
                        Text("These are shown only once. Students must change them on first sign-in.")
                    }
                }
                skippedSection(st.skipped, total: st.skippedCount)
            }

            if let si = sites {
                Section("Result") {
                    Label("\(si.added) added, \(si.updated) updated", systemImage: "checkmark.circle.fill")
                        .foregroundStyle(Theme.green)
                }
                skippedSection(si.skipped, total: si.skippedCount)
            }
        }
        .brandBackground()
        .navigationTitle(kind.title)
        .navigationBarTitleDisplayMode(.inline)
        .fileImporter(isPresented: $picking, allowedContentTypes: [.commaSeparatedText, .plainText, .text]) { result in
            load(result)
        }
    }

    @ViewBuilder
    private func skippedSection(_ rows: [SkippedRow], total: Int) -> some View {
        if total > 0 {
            Section("Skipped rows (\(total))") {
                ForEach(rows) { r in
                    HStack(alignment: .firstTextBaseline) {
                        Text("Line \(r.line)").font(.brand(.caption, weight: .bold)).foregroundStyle(Theme.amber)
                        Text(r.reason).font(.brand(.footnote))
                    }
                }
                if total > rows.count {
                    Text("…and \(total - rows.count) more").font(.brand(.caption)).foregroundStyle(.secondary)
                }
            }
        }
    }

    private func load(_ result: Result<URL, Error>) {
        trips = nil; students = nil; sites = nil; error = nil
        do {
            let url = try result.get()
            let scoped = url.startAccessingSecurityScopedResource()
            defer { if scoped { url.stopAccessingSecurityScopedResource() } }
            let data = try Data(contentsOf: url)
            guard data.count <= 5 * 1024 * 1024 else { throw APIError.server("That file is over the 5 MB limit.") }
            guard let text = String(data: data, encoding: .utf8) ?? String(data: data, encoding: .isoLatin1) else {
                throw APIError.server("Couldn't read that file as text.")
            }
            let lines = text.split(whereSeparator: \.isNewline).filter { !$0.trimmingCharacters(in: .whitespaces).isEmpty }
            header = (lines.first.map(String.init) ?? "")
                .replacingOccurrences(of: "\u{FEFF}", with: "")
                .split(separator: ",", omittingEmptySubsequences: false)
                .map { $0.trimmingCharacters(in: .whitespaces).trimmingCharacters(in: CharacterSet(charactersIn: "\"")).lowercased() }
            rowCount = max(lines.count - 1, 0)
            csv = data
            fileName = url.lastPathComponent
        } catch {
            csv = nil; fileName = nil; header = []; rowCount = 0
            self.error = error.localizedDescription
        }
    }

    private func upload() async {
        guard let csv else { return }
        busy = true; error = nil
        defer { busy = false }
        let name = fileName ?? "upload.csv"
        do {
            switch kind {
            case .trips: trips = try await session.api.upload(.trips, csv: csv, filename: name)
            case .students: students = try await session.api.upload(.students, csv: csv, filename: name)
            case .sites: sites = try await session.api.upload(.sites, csv: csv, filename: name)
            }
        } catch APIError.unauthorized {
            session.clear()
        } catch {
            self.error = error.localizedDescription
        }
    }
}
