import { describe, it, expect } from 'vitest';
import { parseCoords, parseGoogleMapsUrl, looksLikeUrl, isShortMapsLink, classifySearch } from '../shared/geo-search.js';

describe('parseCoords', () => {
    it('parses a plain "lat, lng" pair', () => {
        expect(parseCoords('48.5216, 9.0576')).toEqual({ lat: 48.5216, lng: 9.0576 });
    });
    it('accepts space and semicolon separators', () => {
        expect(parseCoords('48.5216 9.0576')).toEqual({ lat: 48.5216, lng: 9.0576 });
        expect(parseCoords('48.5216;9.0576')).toEqual({ lat: 48.5216, lng: 9.0576 });
    });
    it('honours trailing hemisphere letters', () => {
        expect(parseCoords('48.5216N, 9.0576E')).toEqual({ lat: 48.5216, lng: 9.0576 });
        expect(parseCoords('48.5216N, 9.0576W')).toEqual({ lat: 48.5216, lng: -9.0576 });
        expect(parseCoords('33.9S, 18.4E')).toEqual({ lat: -33.9, lng: 18.4 });
    });
    it('handles negatives', () => {
        expect(parseCoords('-33.8688, 151.2093')).toEqual({ lat: -33.8688, lng: 151.2093 });
    });
    it('rejects out-of-range and non-coordinate text', () => {
        expect(parseCoords('99, 9')).toBeNull();
        expect(parseCoords('Stuttgart')).toBeNull();
        expect(parseCoords('48.5216')).toBeNull();
        expect(parseCoords('')).toBeNull();
    });
});

describe('parseGoogleMapsUrl', () => {
    it('reads the @lat,lng centre', () => {
        expect(parseGoogleMapsUrl('https://www.google.com/maps/@48.5216,9.0576,15z')).toEqual({ lat: 48.5216, lng: 9.0576 });
    });
    it('prefers the !3d!4d place pin over @centre', () => {
        const u = 'https://www.google.com/maps/place/X/@48.5,9.0,15z/data=!3d48.5216!4d9.0576';
        expect(parseGoogleMapsUrl(u)).toEqual({ lat: 48.5216, lng: 9.0576 });
    });
    it('reads q=/ll=/query= params (plain and encoded comma)', () => {
        expect(parseGoogleMapsUrl('https://maps.google.com/?q=48.5216,9.0576')).toEqual({ lat: 48.5216, lng: 9.0576 });
        expect(parseGoogleMapsUrl('https://maps.google.com/?ll=48.5216%2C9.0576&z=12')).toEqual({ lat: 48.5216, lng: 9.0576 });
    });
    it('returns null for a place link with no coordinates', () => {
        expect(parseGoogleMapsUrl('https://www.google.com/maps/place/Eiffel+Tower')).toBeNull();
        expect(parseGoogleMapsUrl('https://maps.app.goo.gl/abcd')).toBeNull();
    });
});

describe('looksLikeUrl / isShortMapsLink', () => {
    it('detects http(s) urls', () => {
        expect(looksLikeUrl('https://x.test')).toBe(true);
        expect(looksLikeUrl('Stuttgart')).toBe(false);
    });
    it('flags google short links only', () => {
        expect(isShortMapsLink('https://maps.app.goo.gl/abcd')).toBe(true);
        expect(isShortMapsLink('https://goo.gl/maps/abcd')).toBe(true);
        expect(isShortMapsLink('https://g.co/kgs/abcd')).toBe(true);
        expect(isShortMapsLink('https://www.google.com/maps/@48.5,9.0,15z')).toBe(false);
        expect(isShortMapsLink('https://example.com/x')).toBe(false);
    });
});

describe('classifySearch', () => {
    it('routes coordinates locally', () => {
        expect(classifySearch('48.5216, 9.0576')).toEqual({ kind: 'coords', lat: 48.5216, lng: 9.0576 });
    });
    it('routes a long google link locally', () => {
        expect(classifySearch('https://www.google.com/maps/@48.5216,9.0576,15z')).toEqual({ kind: 'coords', lat: 48.5216, lng: 9.0576 });
    });
    it('routes a short google link to the resolver', () => {
        expect(classifySearch('https://maps.app.goo.gl/abcd')).toEqual({ kind: 'resolve', url: 'https://maps.app.goo.gl/abcd' });
    });
    it('routes free text to the geocoder', () => {
        expect(classifySearch('Stuttgart Schlossplatz')).toEqual({ kind: 'geocode', q: 'Stuttgart Schlossplatz' });
    });
    it('returns null for empty input', () => {
        expect(classifySearch('   ')).toBeNull();
    });
});
