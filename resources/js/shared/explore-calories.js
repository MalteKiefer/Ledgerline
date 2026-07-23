// Rough energy-expenditure estimate for an Explore tour, computed client-side
// from the track stats + the user's (decrypted) health profile. Deliberately
// approximate — a MET-by-speed model plus an explicit vertical-work term — and
// only ever shown when height, sex and weight are all on file. Pure + tested.

/**
 * Estimate the calories (kcal) burned on a tour.
 *
 * @param {{distanceM:number, durationS?:number, ascentM?:number, weightKg:number, sex?:string}} p
 *   distanceM  total track distance (m)
 *   durationS  real moving/elapsed time (s); when absent/0 (e.g. a planned route)
 *              a duration is estimated from distance + ascent via Naismith's rule
 *   ascentM    total climb (m)
 *   weightKg   body weight (kg) — required
 *   sex        'm' | 'f' | 'x' — a rough lean-mass adjustment for 'f'
 * @returns {number|null}  rounded kcal, or null when it can't be estimated
 */
export function estimateCalories(p) {
    const weightKg = Number(p?.weightKg);
    const distanceM = Number(p?.distanceM);
    if (! (weightKg > 0) || ! (distanceM > 0)) return null;

    const distKm = distanceM / 1000;
    const ascent = Math.max(0, Number(p?.ascentM) || 0);

    // Duration: prefer the real moving time; otherwise estimate it (Naismith:
    // ~4.5 km/h on the flat + 1 h per 600 m of climb) so planned routes with no
    // timestamps still get a number.
    const durationS = Number(p?.durationS) || 0;
    const hours = durationS > 0 ? durationS / 3600 : (distKm / 4.5 + ascent / 600);
    if (! (hours > 0)) return null;

    const speedKmh = distKm / hours;
    // MET by walking/hiking speed.
    let met = speedKmh < 3.2 ? 2.5 : speedKmh < 4.8 ? 3.5 : speedKmh < 6.4 ? 5.0 : 6.5;
    // Hilly terrain (steep average grade) raises the effort.
    if (ascent / distKm > 40) met += 1.5;

    // ACTIVE energy only — 1 MET ≈ resting metabolism, which a fitness tracker's
    // "active/activity calories" excludes. Subtract that baseline so the estimate
    // matches an Apple Watch / Garmin reading rather than total expenditure.
    let kcal = Math.max(0, met - 1) * weightKg * hours;
    kcal += weightKg * ascent * 0.0098;     // extra vertical work (mgh at ~24% efficiency)
    if (p.sex === 'f') kcal *= 0.92;        // rough lower-lean-mass adjustment

    return Math.round(kcal);
}
