// Parse an embedded e-invoice XML (ZUGFeRD/Factur-X CII or XRechnung UBL) into an
// invoice draft. Modern invoice PDFs embed this structured XML, so reading it is far
// more reliable than scraping the rendered text — every field (number, dates, buyer,
// line items, taxes, totals) is machine-readable. Namespace-agnostic regex extraction
// keeps this pure (runs in node + browser, unit-testable) and tolerant of prefixes.

const num = (s) => {
    if (s == null) return null;
    const n = parseFloat(String(s).replace(/[^\d.,-]/g, '').replace(/,(?=\d{3}\b)/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
};

/** First inner text of <*:name …> in the given xml (namespace-agnostic). */
function tagText(xml, name) {
    const m = String(xml).match(new RegExp(`<(?:\\w+:)?${name}\\b[^>]*>([\\s\\S]*?)</(?:\\w+:)?${name}>`, 'i'));
    return m ? m[1].replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/\s+/g, ' ').trim() : null;
}

/** All <*:name>…</*:name> inner blocks (with their markup) in order. */
function blocks(xml, name) {
    return [...String(xml).matchAll(new RegExp(`<(?:\\w+:)?${name}\\b[^>]*>([\\s\\S]*?)</(?:\\w+:)?${name}>`, 'gi'))].map((m) => m[1]);
}

/** CII "102" date (yyyymmdd) or UBL "yyyy-mm-dd" → ISO yyyy-mm-dd. */
function isoDate(s) {
    if (! s) return null;
    let m = String(s).match(/(\d{4})-(\d{2})-(\d{2})/); if (m) return `${m[1]}-${m[2]}-${m[3]}`;
    m = String(s).match(/(\d{4})(\d{2})(\d{2})/); return m ? `${m[1]}-${m[2]}-${m[3]}` : null;
}

const UNIT = { HUR: 'Std', DAY: 'Tage', MON: 'Monate', H87: 'Stk', C62: '', EA: '', ANN: 'Jahre' };
const unitLabel = (code) => UNIT[String(code || '').toUpperCase()] ?? '';

function ciiUnit(itemXml) {
    const m = itemXml.match(/<(?:\w+:)?BilledQuantity\b[^>]*unitCode="([^"]*)"/i);
    return unitLabel(m && m[1]);
}
function ublUnit(lineXml) {
    const m = lineXml.match(/<(?:\w+:)?InvoicedQuantity\b[^>]*unitCode="([^"]*)"/i);
    return unitLabel(m && m[1]);
}

/** Parse ZUGFeRD/Factur-X CII (rsm:CrossIndustryInvoice). */
function parseCII(xml) {
    const doc = blocks(xml, 'ExchangedDocument')[0] || '';
    const buyer = blocks(xml, 'BuyerTradeParty')[0] || '';
    const settle = blocks(xml, 'ApplicableHeaderTradeSettlement')[0] || xml;
    const sum = blocks(settle, 'SpecifiedTradeSettlementHeaderMonetarySummation')[0] || settle;

    const lines = blocks(xml, 'IncludedSupplyChainTradeLineItem').map((li) => {
        const priceBlk = blocks(li, 'NetPriceProductTradePrice')[0] || li;
        return {
            desc: tagText(blocks(li, 'SpecifiedTradeProduct')[0] || li, 'Name') || '',
            qty: num(tagText(li, 'BilledQuantity')) ?? 1,
            unit: ciiUnit(li),
            unitPrice: num(tagText(priceBlk, 'ChargeAmount')) ?? 0,
            vatRate: num(tagText(li, 'RateApplicablePercent')) ?? 0,
        };
    });

    const buyerVat = (buyer.match(/schemeID="VA"[^>]*>([^<]*)/i) || [])[1] || null;
    return {
        syntax: 'cii',
        number: tagText(doc, 'ID'),
        issueDate: isoDate(tagText(doc, 'DateTimeString')),
        dueDate: isoDate(tagText(blocks(settle, 'SpecifiedTradePaymentTerms')[0] || '', 'DateTimeString')),
        currency: tagText(settle, 'InvoiceCurrencyCode') || 'EUR',
        customer: buyerAddress(buyer, tagText(buyer, 'Name'), buyerVat, 'cii'),
        lines,
        net: num(tagText(sum, 'LineTotalAmount')),
        vat: num(tagText(sum, 'TaxTotalAmount')),
        gross: num(tagText(sum, 'GrandTotalAmount')),
    };
}

/** Parse XRechnung UBL (Invoice in the oasis UBL namespace). */
function parseUBL(xml) {
    const cust = blocks(xml, 'AccountingCustomerParty')[0] || '';
    const party = blocks(cust, 'Party')[0] || cust;
    const totals = blocks(xml, 'LegalMonetaryTotal')[0] || '';
    const taxTotal = blocks(xml, 'TaxTotal')[0] || '';

    const lines = blocks(xml, 'InvoiceLine').map((li) => {
        const item = blocks(li, 'Item')[0] || li;
        return {
            desc: tagText(item, 'Name') || '',
            qty: num(tagText(li, 'InvoicedQuantity')) ?? 1,
            unit: ublUnit(li),
            unitPrice: num(tagText(blocks(li, 'Price')[0] || li, 'PriceAmount')) ?? 0,
            vatRate: num(tagText(blocks(item, 'ClassifiedTaxCategory')[0] || item, 'Percent')) ?? 0,
        };
    });

    const name = tagText(blocks(party, 'PartyName')[0] || party, 'Name')
        || tagText(blocks(party, 'PartyLegalEntity')[0] || party, 'RegistrationName');
    const net = num(tagText(totals, 'TaxExclusiveAmount')) ?? num(tagText(totals, 'LineExtensionAmount'));
    const gross = num(tagText(totals, 'TaxInclusiveAmount')) ?? num(tagText(totals, 'PayableAmount'));
    return {
        syntax: 'ubl',
        number: (String(xml).match(/<(?:\w+:)?ID\b[^>]*>([^<]*)</i) || [])[1]?.trim() || null,
        issueDate: isoDate(tagText(xml, 'IssueDate')),
        dueDate: isoDate(tagText(xml, 'DueDate')),
        currency: tagText(xml, 'DocumentCurrencyCode') || 'EUR',
        customer: buyerAddress(party, name, tagText(blocks(party, 'PartyTaxScheme')[0] || '', 'CompanyID'), 'ubl'),
        lines,
        net,
        vat: num(tagText(taxTotal, 'TaxAmount')),
        gross,
    };
}

function buyerAddress(partyXml, name, vatId, syntax) {
    const addr = blocks(partyXml, syntax === 'cii' ? 'PostalTradeAddress' : 'PostalAddress')[0] || '';
    const street = tagText(addr, syntax === 'cii' ? 'LineOne' : 'StreetName') || '';
    const city = tagText(addr, 'CityName') || '';
    const zip = tagText(addr, syntax === 'cii' ? 'PostcodeCode' : 'PostalZone') || '';
    const cityLine = zip && ! /\d{4,}/.test(city) ? `${zip} ${city}`.trim() : city;
    const email = tagText(partyXml, 'ElectronicMail') || tagText(partyXml, 'URIID') || '';
    return { name: name || '', address: [street, cityLine].filter(Boolean).join('\n'), vatId: vatId || '', email };
}

/**
 * Parse an e-invoice XML string. Returns a normalised draft or null if the string is
 * not a recognisable CII or UBL invoice.
 */
export function parseEInvoiceXml(xml) {
    const s = String(xml || '');
    let out = null;
    if (/CrossIndustryInvoice/i.test(s)) out = parseCII(s);
    else if (/:Invoice-2|<Invoice\b|ubl:schema/i.test(s)) out = parseUBL(s);
    if (! out || ! out.number) return null;
    // Reconcile a missing total.
    if (out.net == null && out.gross != null && out.vat != null) out.net = out.gross - out.vat;
    if (out.gross == null && out.net != null) out.gross = out.net + (out.vat || 0);
    out.vatRate = out.lines.length ? (out.lines[0].vatRate || 0) : 0;
    return out;
}

/** True if the bytes/string look like an embedded e-invoice XML worth parsing. */
export function looksLikeEInvoiceXml(text) {
    return /CrossIndustryInvoice|:Invoice-2|ubl:schema/i.test(String(text || ''));
}
