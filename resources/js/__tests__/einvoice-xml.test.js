import { describe, it, expect } from 'vitest';
import { parseEInvoiceXml, looksLikeEInvoiceXml } from '../shared/einvoice-xml.js';

const CII = `<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="x" xmlns:udt="y">
  <rsm:ExchangedDocument><ram:ID>2026-003</ram:ID><ram:TypeCode>380</ram:TypeCode><ram:IssueDateTime><udt:DateTimeString format="102">20260430</udt:DateTimeString></ram:IssueDateTime></rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:SpecifiedTradeProduct><ram:Name>VPN Zertifikat getauscht</ram:Name></ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement><ram:NetPriceProductTradePrice><ram:ChargeAmount>45.00</ram:ChargeAmount></ram:NetPriceProductTradePrice></ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery><ram:BilledQuantity unitCode="HUR">0.18</ram:BilledQuantity></ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement><ram:ApplicableTradeTax><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax></ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:BuyerTradeParty><ram:Name>IntellyTec GmbH</ram:Name><ram:PostalTradeAddress><ram:PostcodeCode>53797</ram:PostcodeCode><ram:LineOne>Grünenborn 1</ram:LineOne><ram:CityName>Lohmar</ram:CityName></ram:PostalTradeAddress><ram:SpecifiedTaxRegistration><ram:ID schemeID="VA">DE304323922</ram:ID></ram:SpecifiedTaxRegistration></ram:BuyerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
      <ram:SpecifiedTradePaymentTerms><ram:DueDateDateTime><udt:DateTimeString format="102">20260514</udt:DateTimeString></ram:DueDateDateTime></ram:SpecifiedTradePaymentTerms>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation><ram:LineTotalAmount>149.85</ram:LineTotalAmount><ram:TaxTotalAmount currencyID="EUR">28.47</ram:TaxTotalAmount><ram:GrandTotalAmount>178.32</ram:GrandTotalAmount></ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>`;

const UBL = `<?xml version="1.0"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>
  <cbc:ID>2026-001</cbc:ID>
  <cbc:IssueDate>2026-02-02</cbc:IssueDate>
  <cbc:DueDate>2026-02-16</cbc:DueDate>
  <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
  <cac:AccountingCustomerParty><cac:Party>
    <cac:PartyName><cbc:Name>IntellyTec GmbH</cbc:Name></cac:PartyName>
    <cac:PostalAddress><cbc:StreetName>Grünenborn 1</cbc:StreetName><cbc:CityName>53797 Lohmar</cbc:CityName></cac:PostalAddress>
    <cac:PartyTaxScheme><cbc:CompanyID>DE347517386</cbc:CompanyID></cac:PartyTaxScheme>
    <cac:Contact><cbc:ElectronicMail>ingo.radermacher@intellytec.de</cbc:ElectronicMail></cac:Contact>
  </cac:Party></cac:AccountingCustomerParty>
  <cac:TaxTotal><cbc:TaxAmount currencyID="EUR">29.93</cbc:TaxAmount></cac:TaxTotal>
  <cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID="EUR">157.50</cbc:LineExtensionAmount><cbc:TaxExclusiveAmount currencyID="EUR">157.50</cbc:TaxExclusiveAmount><cbc:TaxInclusiveAmount currencyID="EUR">187.43</cbc:TaxInclusiveAmount><cbc:PayableAmount currencyID="EUR">187.43</cbc:PayableAmount></cac:LegalMonetaryTotal>
  <cac:InvoiceLine><cbc:ID>1</cbc:ID><cbc:InvoicedQuantity unitCode="EA">2.50</cbc:InvoicedQuantity><cbc:LineExtensionAmount currencyID="EUR">112.50</cbc:LineExtensionAmount>
    <cac:Item><cbc:Name>Cyberangriff auf Apps Server unterbinden</cbc:Name><cac:ClassifiedTaxCategory><cbc:Percent>19.00</cbc:Percent></cac:ClassifiedTaxCategory></cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="EUR">45.00</cbc:PriceAmount></cac:Price></cac:InvoiceLine>
  <cac:InvoiceLine><cbc:ID>2</cbc:ID><cbc:InvoicedQuantity unitCode="EA">1</cbc:InvoicedQuantity>
    <cac:Item><cbc:Name>Härtung Crowdsec</cbc:Name><cac:ClassifiedTaxCategory><cbc:Percent>19.00</cbc:Percent></cac:ClassifiedTaxCategory></cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="EUR">45.00</cbc:PriceAmount></cac:Price></cac:InvoiceLine>
</Invoice>`;

describe('embedded e-invoice XML', () => {
    it('detects invoice XML', () => {
        expect(looksLikeEInvoiceXml(CII)).toBe(true);
        expect(looksLikeEInvoiceXml(UBL)).toBe(true);
        expect(looksLikeEInvoiceXml('<html>no</html>')).toBe(false);
    });

    it('parses ZUGFeRD/Factur-X CII fully', () => {
        const p = parseEInvoiceXml(CII);
        expect(p.syntax).toBe('cii');
        expect(p.number).toBe('2026-003');
        expect(p.issueDate).toBe('2026-04-30');
        expect(p.dueDate).toBe('2026-05-14');
        expect(p.net).toBe(149.85);
        expect(p.vat).toBe(28.47);
        expect(p.gross).toBe(178.32);
        expect(p.customer.name).toBe('IntellyTec GmbH');
        expect(p.customer.vatId).toBe('DE304323922');
        expect(p.customer.address).toContain('53797 Lohmar');
        expect(p.lines).toHaveLength(1);
        expect(p.lines[0]).toMatchObject({ desc: 'VPN Zertifikat getauscht', qty: 0.18, unit: 'Std', unitPrice: 45, vatRate: 19 });
    });

    it('parses XRechnung UBL fully with all line items', () => {
        const p = parseEInvoiceXml(UBL);
        expect(p.syntax).toBe('ubl');
        expect(p.number).toBe('2026-001');
        expect(p.issueDate).toBe('2026-02-02');
        expect(p.dueDate).toBe('2026-02-16');
        expect(p.net).toBe(157.5);
        expect(p.vat).toBe(29.93);
        expect(p.gross).toBe(187.43);
        expect(p.customer.name).toBe('IntellyTec GmbH');
        expect(p.customer.email).toBe('ingo.radermacher@intellytec.de');
        expect(p.lines).toHaveLength(2);
        expect(p.lines[0]).toMatchObject({ desc: 'Cyberangriff auf Apps Server unterbinden', qty: 2.5, unitPrice: 45, vatRate: 19 });
        expect(p.lines[1].desc).toBe('Härtung Crowdsec');
    });

    it('returns null for non-invoice XML', () => {
        expect(parseEInvoiceXml('<foo/>')).toBeNull();
    });
});
