import SwiftUI
import MapKit

/// Placements map: individual approved/finalised sites as pins, and combined
/// journeys as routes from the CPUT pickup point through each stop and back.
struct AdminMapView: View {
    @Environment(Session.self) private var session
    @State private var data: MapData?
    @State private var error: String?
    @State private var department: String?
    @State private var shift: String?
    @State private var mode = "all"
    @State private var useDate = false
    @State private var date = Date.now
    @State private var position = MapCameraPosition.region(MKCoordinateRegion(
        center: CLLocationCoordinate2D(latitude: -33.95, longitude: 18.55),
        span: MKCoordinateSpan(latitudeDelta: 0.5, longitudeDelta: 0.5)))
    @State private var selected: String?

    private let colors: [Color] = [.blue, .purple, .orange, .teal, .pink, .indigo, .green]

    private func coord(_ lat: Double, _ lng: Double) -> CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: lat, longitude: lng)
    }

    private var dateString: String? {
        guard useDate else { return nil }
        let f = DateFormatter()
        f.dateFormat = "yyyy-MM-dd"
        f.locale = Locale(identifier: "en_US_POSIX")
        return f.string(from: date)
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                Map(position: $position, selection: $selected) {
                    if let d = data {
                        Marker("CPUT Bellville", systemImage: "building.columns", coordinate: coord(d.pickup.lat, d.pickup.lng))
                            .tint(.red)
                            .tag("pickup")
                        ForEach(d.markers) { m in
                            Marker("\(m.name) (\(m.count))", coordinate: coord(m.lat, m.lng))
                                .tint(.blue)
                                .tag(m.id)
                        }
                        ForEach(Array(d.journeys.enumerated()), id: \.element.id) { i, j in
                            let color = colors[i % colors.count]
                            MapPolyline(coordinates:
                                [coord(d.pickup.lat, d.pickup.lng)] + j.stops.map { coord($0.lat, $0.lng) } + [coord(d.pickup.lat, d.pickup.lng)],
                                contourStyle: .straight)
                                .stroke(color, lineWidth: 3)
                            ForEach(j.stops) { s in
                                Annotation("", coordinate: coord(s.lat, s.lng)) {
                                    Text("\(s.order)")
                                        .font(.caption2.bold()).foregroundStyle(.white)
                                        .frame(width: 20, height: 20)
                                        .background(color, in: Circle())
                                }
                                .annotationTitles(.hidden)
                            }
                        }
                    }
                }
                .mapControls { MapCompass(); MapScaleView() }
                .overlay(alignment: .top) {
                    if let error { Text(error).font(.caption).padding(6).background(.red.opacity(0.85), in: Capsule()).foregroundStyle(.white).padding(.top, 6) }
                }

                summary
            }
            .navigationTitle("Placements Map")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .topBarTrailing) { filterMenu } }
            .task(id: "\(department ?? "")|\(shift ?? "")|\(mode)|\(dateString ?? "")") { await load() }
        }
    }

    private var summary: some View {
        List {
            if let d = data {
                Section {
                    Text("\(d.totalPlacements) placements · \(d.totalSites) individual sites · \(d.totalJourneys) journeys")
                        .font(.footnote).foregroundStyle(.secondary)
                    if let sel = selected, sel != "pickup", let m = d.markers.first(where: { $0.id == sel }) {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(m.name).font(.headline)
                            if let a = m.address { Text(a).font(.caption).foregroundStyle(.secondary) }
                            Text("\(m.count) placement\(m.count == 1 ? "" : "s") · \(m.departments.joined(separator: ", "))").font(.caption)
                        }
                    }
                }
                ForEach(Array(d.journeys.enumerated()), id: \.element.id) { i, j in
                    HStack(alignment: .top, spacing: 10) {
                        Circle().fill(colors[i % colors.count]).frame(width: 12, height: 12).padding(.top, 5)
                        VStack(alignment: .leading, spacing: 2) {
                            Text(j.label).font(.subheadline.bold())
                            Text("\(j.date) · \(j.time) · \(j.studentCount) students · \(String(format: "%.1f", j.totalKm)) km\(j.allFinalised ? " · finalised" : "")")
                                .font(.caption).foregroundStyle(.secondary)
                            Text(j.stops.map { "\($0.order). \($0.name)" }.joined(separator: "  "))
                                .font(.caption2).foregroundStyle(.secondary)
                        }
                    }
                }
            }
        }
        .listStyle(.plain)
        .frame(maxHeight: 210)
    }

    private var filterMenu: some View {
        Menu {
            Picker("Show", selection: $mode) {
                Text("All").tag("all")
                Text("Journeys only").tag("journeys")
                Text("Individual sites").tag("individual")
            }
            Picker("Shift", selection: $shift) {
                Text("Any shift").tag(String?.none)
                Text("Day").tag(String?.some("day"))
                Text("Night").tag(String?.some("night"))
            }
            Picker("Department", selection: $department) {
                Text("All departments").tag(String?.none)
                ForEach(data?.departments ?? [], id: \.self) { Text($0).tag(String?.some($0)) }
            }
            Toggle("Filter by date (\(date.formatted(date: .abbreviated, time: .omitted)))", isOn: $useDate)
            if useDate {
                Button("Tomorrow") { date = Calendar.current.date(byAdding: .day, value: 1, to: .now) ?? .now }
                Button("Today") { date = .now }
            }
        } label: {
            Label("Filters", systemImage: "line.3.horizontal.decrease.circle")
        }
    }

    private func load() async {
        do {
            data = try await session.api.map(department: department, shift: shift, view: mode, date: dateString)
            error = nil
        } catch APIError.unauthorized { session.clear() }
        catch is CancellationError {}
        catch { self.error = error.localizedDescription }
    }
}
