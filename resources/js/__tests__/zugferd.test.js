import { describe, it, expect } from 'vitest';
import { buildZugferdXml, splitAddress, unitCode, zugferdFilename } from '../shared/zugferd.js';

describe('zugferd helpers', () => {
    it('splits a German address into postcode/city/street', () => {
        expect(splitAddress('Adalbert-Stifter-Str. 6\n95512 Neudrossenfeld'))
            .toEqual({ line: 'Adalbert-Stifter-Str. 6', postcode: '95512', city: 'Neudrossenfeld', country: 'DE' });
        expect(splitAddress('Mauermattenstraße 20\nD-79183 Waldkirch').postcode).toBe('79183');
    });
    it('maps units to UN/ECE codes', () => {
        expect(unitCode('Std')).toBe('HUR');
        expect(unitCode('Stunde(n)')).toBe('HUR');
        expect(unitCode('Monate')).toBe('MON');
        expect(unitCode('Stück')).toBe('H87');
        expect(unitCode('')).toBe('C62');
    });
    it('builds a safe filename', () => {
        expect(zugferdFilename({ number: 'R-2024-00001' })).toBe('R-2024-00001-factur-x.xml');
    });
});

describe('zugferd CII XML', () => {
    const inv = {
        number: 'R-2024-00001', issueDate: '2024-04-29', dueDate: '2024-05-06', currency: 'EUR',
        customer: { name: 'IntellyTec GmbH', address: 'Grünenborn 1\n53797 Lohmar', vatId: 'DE123456789', email: 'x@y.de' },
        lines: [{ desc: 'Beratung', qty: 1, unit: 'Stunde', unitPrice: 40, vatRate: 19 }],
    };
    const company = { name: 'Kiefer Networks', address: 'Adalbert-Stifter-Str. 6\n95512 Neudrossenfeld', vat_id: 'DE 30 43 23 922', email: 'info@kn.de', iban: 'DE24 1001 1001 2629 5661 74' };
    const totals = { net: 40, vat: 7.6, gross: 47.6, vatByRate: { 19: 7.6 } };
    const xml = buildZugferdXml(inv, company, totals);

    it('is a CII document with the EN16931 guideline', () => {
        expect(xml).toContain('<rsm:CrossIndustryInvoice');
        expect(xml).toContain('urn:cen.eu:en16931:2017');
        expect(xml).toContain('<ram:TypeCode>380</ram:TypeCode>');
    });
    it('carries number, dates, parties and VAT id (spaces stripped)', () => {
        expect(xml).toContain('<ram:ID>R-2024-00001</ram:ID>');
        expect(xml).toContain('format="102">20240429<');
        expect(xml).toContain('<ram:Name>IntellyTec GmbH</ram:Name>');
        expect(xml).toContain('<ram:PostcodeCode>53797</ram:PostcodeCode>');
        expect(xml).toContain('schemeID="VA">DE304323922<');
        expect(xml).toContain('<ram:IBANID>DE241001100126295661'.slice(0, 20));
    });
    it('carries the correct monetary summation and tax', () => {
        expect(xml).toContain('<ram:GrandTotalAmount>47.60</ram:GrandTotalAmount>');
        expect(xml).toContain('<ram:TaxBasisTotalAmount>40.00</ram:TaxBasisTotalAmount>');
        expect(xml).toContain('<ram:CategoryCode>S</ram:CategoryCode>');
        expect(xml).toContain('<ram:RateApplicablePercent>19.00</ram:RateApplicablePercent>');
    });
    it('marks a small-business (0%) invoice as exempt', () => {
        const x = buildZugferdXml(
            { number: '1', issueDate: '2014-04-10', currency: 'EUR', customer: { name: 'STN' }, lines: [{ desc: 'x', qty: 1, unit: '', unitPrice: 100, vatRate: 0 }] },
            company, { net: 100, vat: 0, gross: 100, vatByRate: { 0: 0 } },
        );
        expect(x).toContain('<ram:CategoryCode>E</ram:CategoryCode>');
        expect(x).toContain('Kleinunternehmer');
    });
    it('mixed-rate: the 0% tax group basis is only the 0%-rate net, and per-category bases reconcile to TaxBasisTotalAmount', () => {
        // 100 net @19% + 50 net @0% → net 150, vat 19. The 0% BasisAmount must be 50, not 150.
        const x = buildZugferdXml(
            { number: 'M1', issueDate: '2026-01-02', currency: 'EUR', customer: { name: 'Mix' },
                lines: [{ desc: 'a', qty: 1, unit: '', unitPrice: 100, vatRate: 19 }, { desc: 'b', qty: 1, unit: '', unitPrice: 50, vatRate: 0 }] },
            company, { net: 150, vat: 19, gross: 169, vatByRate: { 19: 19, 0: 0 } },
        );
        expect(x).toContain('<ram:BasisAmount>100.00</ram:BasisAmount>'); // 19% group
        expect(x).toContain('<ram:BasisAmount>50.00</ram:BasisAmount>');  // 0% group = 0%-net only
        expect(x).not.toContain('<ram:BasisAmount>150.00</ram:BasisAmount>'); // never the whole invoice net
        expect(x).toContain('<ram:TaxBasisTotalAmount>150.00</ram:TaxBasisTotalAmount>');
    });
    it('escapes XML-special characters', () => {
        const x = buildZugferdXml({ number: 'A&B', currency: 'EUR', customer: { name: '<x>' }, lines: [] }, company, totals);
        expect(x).toContain('<ram:ID>A&amp;B</ram:ID>');
        expect(x).toContain('&lt;x&gt;');
    });
});
