// ZUGFeRD / Factur-X invoice XML (UN/CEFACT Cross Industry Invoice, EN 16931 profile).
// Pure + client-side (the XML is generated in the browser and downloaded locally). Produces
// the structured XML payload that is the heart of a ZUGFeRD e-invoice — directly usable
// as an XRechnung/Factur-X XML and ready to be embedded into a PDF/A-3 later.

const esc = (s) => String(s ?? '').replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' }[c]));
const dec = (n) => (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);

/** ISO yyyy-mm-dd → CII format code 102 date "yyyymmdd". */
function ciiDate(iso) {
    const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[1] + m[2] + m[3] : '';
}

/** German address block → { line, postcode, city, country }. */
export function splitAddress(address) {
    const lines = String(address || '').split('\n').map((s) => s.trim()).filter(Boolean);
    let postcode = '', city = '', country = 'DE';
    const streetParts = [];
    for (const ln of lines) {
        const m = ln.match(/^(?:D-)?(\d{5})\s+(.+)$/);
        if (m && ! postcode) { postcode = m[1]; city = m[2]; } else streetParts.push(ln);
    }
    return { line: streetParts.join(', '), postcode, city, country };
}

/** UN/ECE Rec 20 unit code for a free-text unit. Default C62 (one/piece). */
export function unitCode(unit) {
    const u = String(unit || '').toLowerCase();
    if (/std|stunde|hour|\bh\b/.test(u)) return 'HUR';
    if (/monat|month|\bmon\b/.test(u)) return 'MON';
    if (/tag|day/.test(u)) return 'DAY';
    if (/stück|stk|piece|pcs/.test(u)) return 'H87';
    return 'C62';
}

function partyXml(role, name, addr, vatId, email) {
    const a = splitAddress(addr);
    return `      <ram:${role}TradeParty>
        <ram:Name>${esc(name)}</ram:Name>
        <ram:PostalTradeAddress>
${a.postcode ? `          <ram:PostcodeCode>${esc(a.postcode)}</ram:PostcodeCode>\n` : ''}${a.line ? `          <ram:LineOne>${esc(a.line)}</ram:LineOne>\n` : ''}${a.city ? `          <ram:CityName>${esc(a.city)}</ram:CityName>\n` : ''}          <ram:CountryID>${esc(a.country)}</ram:CountryID>
        </ram:PostalTradeAddress>
${email ? `        <ram:URIUniversalCommunication><ram:URIID schemeID="EM">${esc(email)}</ram:URIID></ram:URIUniversalCommunication>\n` : ''}${vatId ? `        <ram:SpecifiedTaxRegistration><ram:ID schemeID="VA">${esc(vatId.replace(/\s+/g, ''))}</ram:ID></ram:SpecifiedTaxRegistration>\n` : ''}      </ram:${role}TradeParty>`;
}

/**
 * Build the CII XML for an invoice.
 * @param {object} inv  invoice record { number, issueDate, dueDate, currency, customer, lines }
 * @param {object} company  seller { name, address, vat_id, tax_id, email, iban, bic }
 * @param {object} totals  { net, vat, gross, vatByRate }
 */
export function buildZugferdXml(inv, company, totals) {
    const cur = inv.currency || 'EUR';
    const t = totals || { net: 0, vat: 0, gross: 0, vatByRate: {} };
    // Line total is the raw (pre-discount) net; a global discount is a document-level
    // allowance, so TaxBasisTotal = LineTotal − allowance = t.net (the discounted net).
    const rawNet = Number.isFinite(t.grossNet) ? t.grossNet : t.net;
    const discount = Number(t.discount) || 0;

    // One line item per invoice line.
    const lines = (inv.lines || []).map((l, i) => {
        const qty = Number(l.qty) || 0;
        const price = Number(l.unitPrice) || 0;
        const lineNet = Math.round(qty * price * 100) / 100;
        const cat = (Number(l.vatRate) || 0) > 0 ? 'S' : 'E';
        return `    <ram:IncludedSupplyChainTradeLineItem>
      <ram:AssociatedDocumentLineDocument><ram:LineID>${i + 1}</ram:LineID></ram:AssociatedDocumentLineDocument>
      <ram:SpecifiedTradeProduct><ram:Name>${esc(l.desc || '-')}</ram:Name></ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement><ram:NetPriceProductTradePrice><ram:ChargeAmount>${dec(price)}</ram:ChargeAmount></ram:NetPriceProductTradePrice></ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery><ram:BilledQuantity unitCode="${unitCode(l.unit)}">${dec(qty)}</ram:BilledQuantity></ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:ApplicableTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>${cat}</ram:CategoryCode><ram:RateApplicablePercent>${dec(l.vatRate || 0)}</ram:RateApplicablePercent></ram:ApplicableTradeTax>
        <ram:SpecifiedTradeSettlementLineMonetarySummation><ram:LineTotalAmount>${dec(lineNet)}</ram:LineTotalAmount></ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>`;
    }).join('\n');

    // One ApplicableTradeTax per VAT rate. For a positive rate the basis is derived from
    // that group's VAT. On a mixed-rate invoice the 0%/exempt group's BasisAmount must be
    // ONLY the 0%-rate net, NOT the whole invoice net — otherwise the per-category bases
    // exceed TaxBasisTotalAmount and the Factur-X fails EN-16931 (BR-CO-10 / BR-S-08 /
    // BR-E-08). Derive the 0% basis as the tax-basis remainder after the positive-rate
    // bases, which reconciles to TaxBasisTotalAmount (t.net) exactly.
    const rateKeysAll = Object.keys(t.vatByRate || {});
    const positiveBasis = rateKeysAll.reduce((sum, rate) => {
        const r = Number(rate);
        return r > 0 ? sum + Math.round((t.vatByRate[rate] / (r / 100)) * 100) / 100 : sum;
    }, 0);
    const zeroBasis = Math.round((Number(t.net) - positiveBasis) * 100) / 100;
    const taxes = rateKeysAll.map((rate) => {
        const r = Number(rate);
        const vat = t.vatByRate[rate];
        const basis = r > 0 ? Math.round((vat / (r / 100)) * 100) / 100 : zeroBasis;
        const cat = r > 0 ? 'S' : 'E';
        return `      <ram:ApplicableTradeTax>
        <ram:CalculatedAmount>${dec(vat)}</ram:CalculatedAmount>
        <ram:TypeCode>VAT</ram:TypeCode>${r > 0 ? '' : '\n        <ram:ExemptionReason>Kleinunternehmer gemäß § 19 UStG</ram:ExemptionReason>'}
        <ram:BasisAmount>${dec(basis)}</ram:BasisAmount>
        <ram:CategoryCode>${cat}</ram:CategoryCode>
        <ram:RateApplicablePercent>${dec(r)}</ram:RateApplicablePercent>
      </ram:ApplicableTradeTax>`;
    }).join('\n') || `      <ram:ApplicableTradeTax><ram:CalculatedAmount>0.00</ram:CalculatedAmount><ram:TypeCode>VAT</ram:TypeCode><ram:BasisAmount>${dec(t.net)}</ram:BasisAmount><ram:CategoryCode>E</ram:CategoryCode><ram:RateApplicablePercent>0.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>`;

    // Document-level discount (BG-20 allowance). ChargeIndicator=false → allowance.
    const rateKeys = Object.keys(t.vatByRate || {}).map(Number);
    const allowRate = rateKeys.length ? Math.max(...rateKeys) : 0;
    const allowanceXml = discount ? `      <ram:SpecifiedTradeAllowanceCharge>
        <ram:ChargeIndicator><udt:Indicator>false</udt:Indicator></ram:ChargeIndicator>
        <ram:ActualAmount>${dec(Math.abs(discount))}</ram:ActualAmount>
        <ram:Reason>${esc(inv.discountType === 'percent' ? 'Rabatt ' + dec(inv.discountValue) + ' %' : 'Rabatt')}</ram:Reason>
        <ram:CategoryTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>${allowRate > 0 ? 'S' : 'E'}</ram:CategoryCode><ram:RateApplicablePercent>${dec(allowRate)}</ram:RateApplicablePercent></ram:CategoryTradeTax>
      </ram:SpecifiedTradeAllowanceCharge>\n` : '';

    return `<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocumentContext>
    <ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>urn:cen.eu:en16931:2017</ram:ID></ram:GuidelineSpecifiedDocumentContextParameter>
  </rsm:ExchangedDocumentContext>
  <rsm:ExchangedDocument>
    <ram:ID>${esc(inv.number || '')}</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime><udt:DateTimeString format="102">${ciiDate(inv.issueDate)}</udt:DateTimeString></ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
${lines}
    <ram:ApplicableHeaderTradeAgreement>
${partyXml('Seller', company.name, company.address, company.vat_id, company.email)}
${partyXml('Buyer', inv.customer?.name, inv.customer?.address, inv.customer?.vatId, inv.customer?.email)}
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery>
      <ram:ActualDeliverySupplyChainEvent><ram:OccurrenceDateTime><udt:DateTimeString format="102">${ciiDate(inv.issueDate)}</udt:DateTimeString></ram:OccurrenceDateTime></ram:ActualDeliverySupplyChainEvent>
    </ram:ApplicableHeaderTradeDelivery>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>${esc(cur)}</ram:InvoiceCurrencyCode>
${company.iban ? `      <ram:SpecifiedTradeSettlementPaymentMeans><ram:TypeCode>58</ram:TypeCode><ram:PayeePartyCreditorFinancialAccount><ram:IBANID>${esc(company.iban.replace(/\s+/g, ''))}</ram:IBANID></ram:PayeePartyCreditorFinancialAccount></ram:SpecifiedTradeSettlementPaymentMeans>\n` : ''}${taxes}
${allowanceXml}${inv.dueDate ? `      <ram:SpecifiedTradePaymentTerms><ram:DueDateDateTime><udt:DateTimeString format="102">${ciiDate(inv.dueDate)}</udt:DateTimeString></ram:DueDateDateTime></ram:SpecifiedTradePaymentTerms>\n` : ''}      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:LineTotalAmount>${dec(rawNet)}</ram:LineTotalAmount>
${discount ? `        <ram:AllowanceTotalAmount>${dec(Math.abs(discount))}</ram:AllowanceTotalAmount>\n` : ''}        <ram:TaxBasisTotalAmount>${dec(t.net)}</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount currencyID="${esc(cur)}">${dec(t.vat)}</ram:TaxTotalAmount>
        <ram:GrandTotalAmount>${dec(t.gross)}</ram:GrandTotalAmount>
        <ram:DuePayableAmount>${dec(t.gross)}</ram:DuePayableAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
`;
}

/** A filesystem-safe XML filename for the invoice. */
export function zugferdFilename(inv) {
    const base = (inv.number || 'rechnung').replace(/[^\w.-]+/g, '_');
    return `${base}-factur-x.xml`;
}
