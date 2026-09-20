import SwiftUI

/// Requests for Quote: bundle finalised trips into an RFQ for the transport
/// supplier, priced per trip or left "to be calculated".
struct AdminQuotesView: View {
    @Environment(Session.self) private var session
    @State private var data: QuotesOverview?
    @State private var error: String?
    @State private var creating = false
    @State private var opened: Int?

    var body: some View {
        NavigationStack {
            List {
                if let error { Text(error).foregroundStyle(.red) }
                if let d = data {
                    Section("Awaiting an RFQ (\(d.awaitingTrips) trips)") {
                        ForEach(d.awaitingLines) { l in
                            VStack(alignment: .leading, spacing: 2) {
                                Text(l.site).font(.brand(.headline))
                                Text("\(l.date) · \(l.time) · \(l.qty) student\(l.qty == 1 ? "" : "s")\(l.part.map { " · \($0)" } ?? "")")
                                    .font(.brand(.subheadline)).foregroundStyle(.secondary)
                            }
                        }
                        if d.awaitingLines.isEmpty {
                            Text("No finalised trips waiting. Finalise approved trips from the Review tab.")
                                .font(.brand(.footnote)).foregroundStyle(.secondary)
                        } else {
                            Button("Create RFQ") { creating = true }
                                .buttonStyle(.pill)
                                .listRowBackground(Color.clear)
                        }
                    }
                    Section("Issued RFQs") {
                        ForEach(d.quotes) { q in
                            NavigationLink(value: q.id) {
                                VStack(alignment: .leading, spacing: 2) {
                                    HStack {
                                        Text(q.ref).font(.brand(.headline))
                                        if q.isTbc { Text("rate TBC").font(.brand(.caption2)).foregroundStyle(.orange) }
                                    }
                                    Text("\(q.period) · \(q.total.map(Money.format) ?? "To be calculated")")
                                        .font(.brand(.subheadline)).foregroundStyle(.secondary)
                                }
                            }
                        }
                    }
                }
            }
            .overlay { if data == nil && error == nil { ProgressView() } }
            .brandBackground()
            .navigationTitle("RFQs")
            .navigationDestination(for: Int.self) { QuoteDetailView(id: $0) }
            .navigationDestination(item: $opened) { QuoteDetailView(id: $0) }
            .sheet(isPresented: $creating) {
                if let d = data {
                    NewQuoteForm(defaultRate: d.defaultRate, trips: d.awaitingTrips) { id in
                        await load()
                        opened = id
                    }
                }
            }
            .refreshable { await load() }
            .task { await load() }
        }
    }

    private func load() async {
        do { data = try await session.api.quotes(); error = nil }
        catch APIError.unauthorized { session.clear() }
        catch { self.error = error.localizedDescription }
    }
}

struct NewQuoteForm: View {
    @Environment(Session.self) private var session
    @Environment(\.dismiss) private var dismiss
    let defaultRate: Double
    let trips: Int
    let onCreated: (Int) async -> Void

    @State private var period = ""
    @State private var priced = true
    @State private var rate = ""
    @State private var error: String?
    @State private var busy = false

    private var rateValue: Double? { Double(rate.replacingOccurrences(of: ",", with: ".")) }

    var body: some View {
        NavigationStack {
            Form {
                Section("RFQ for \(trips) finalised trip\(trips == 1 ? "" : "s")") {
                    TextField("Period (e.g. Oct 2026)", text: $period)
                }
                Section {
                    Picker("Pricing", selection: $priced) {
                        Text("Rate per trip").tag(true)
                        Text("To be calculated").tag(false)
                    }
                    .pickerStyle(.segmented)
                    if priced {
                        TextField("Rate per trip (ZAR)", text: $rate).keyboardType(.decimalPad)
                    } else {
                        Text("The supplier will price each trip line.").font(.brand(.footnote)).foregroundStyle(.secondary)
                    }
                }
                if let error { Text(error).foregroundStyle(.red) }
            }
            .brandBackground()
            .navigationTitle("New RFQ")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Create") { Task { await create() } }
                        .disabled(busy || period.isEmpty || (priced && rateValue == nil))
                }
            }
            .onAppear { if rate.isEmpty { rate = String(defaultRate) } }
        }
    }

    private func create() async {
        busy = true
        defer { busy = false }
        do {
            let id = try await session.api.createQuote(period: period, rate: priced ? rateValue : nil)
            dismiss()
            await onCreated(id)
        } catch { self.error = error.localizedDescription }
    }
}

struct QuoteDetailView: View {
    @Environment(Session.self) private var session
    let id: Int
    @State private var quote: QuoteDetail?
    @State private var error: String?

    var body: some View {
        List {
            if let error { Text(error).foregroundStyle(.red) }
            if let q = quote {
                Section {
                    LabeledContent("Ref", value: q.ref)
                    LabeledContent("Date", value: q.date)
                    LabeledContent("Period", value: q.period)
                    if q.isTbc { LabeledContent("Rate", value: "To be confirmed") }
                }
                Section("Trips") {
                    ForEach(q.lines) { l in
                        VStack(alignment: .leading, spacing: 2) {
                            Text("\(l.date) \(l.time)").font(.brand(.headline))
                            Text("\(l.site) and return · 7-Seater\(l.part.map { " (\($0))" } ?? "")")
                                .font(.brand(.subheadline))
                            HStack {
                                Text("\(l.qty) trip\(l.qty == 1 ? "" : "s")").foregroundStyle(.secondary)
                                Spacer()
                                Text(l.extended.map(Money.format) ?? "To be calculated")
                            }
                            .font(.brand(.caption))
                        }
                    }
                }
                Section {
                    LabeledContent("Total (ZAR)", value: q.total.map(Money.format) ?? "To be calculated")
                        .font(.brand(.headline))
                }
            }
        }
        .overlay { if quote == nil && error == nil { ProgressView() } }
        .navigationTitle(quote?.ref ?? "RFQ")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if let q = quote {
                ShareLink(item: q.shareText, subject: Text(q.ref))
            }
        }
        .task {
            do { quote = try await session.api.quote(id: id) }
            catch APIError.unauthorized { session.clear() }
            catch { self.error = error.localizedDescription }
        }
    }
}
