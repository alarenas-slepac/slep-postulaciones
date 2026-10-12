export const hourFields = ['total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos'];

export function count(value) {
    if (!/^\d+$/.test(String(value))) return null;
    const number = Number(value);
    return Number.isInteger(number) && number >= 0 && number <= 9999 ? number : null;
}

export function preview(row, neetValue, neepValue, matrix) {
    const neet = count(neetValue), neep = count(neepValue);
    const changed = String(neetValue) !== String(row.neet) || String(neepValue) !== String(row.neep);
    if (neet === null || neep === null) return { changed, valid: false, error: 'Ingresa cantidades enteras entre 0 y 9999.', neet, neep, total: null, calculation: {} };
    const total = neet + neep;
    if (total > Number(row.matricula)) return { changed, valid: false, error: 'NEET + NEEP supera la matrícula del curso.', neet, neep, total, calculation: {} };
    const different = neet !== Number(row.neet) || neep !== Number(row.neep);
    // Volver a los valores guardados también recupera sus horas; no recalcular históricos sin cambios.
    const calculation = different ? matrix[row.rule_key]?.[neet === 0 ? 0 : neet < 5 ? 1 : 5]?.[Math.min(neep, 32)] : row.calculation;
    if (!calculation) return { changed: different, valid: false, error: 'Recarga el catálogo de horas PIE.', neet, neep, total, calculation: {} };
    return { changed: different, valid: true, error: '', neet, neep, total, calculation };
}

export function summarize(values) {
    const result = { neet: 0, neep: 0, total: 0, invalid: 0, unknown: 0, changed: 0 };
    for (const field of hourFields) result[field] = 0;
    for (const value of values) {
        if (value.changed) result.changed++;
        if (!value.valid) { result.invalid++; continue; }
        result.neet += value.neet; result.neep += value.neep; result.total += value.total;
        let unknown = false;
        for (const field of hourFields) {
            if (value.calculation[field] == null) unknown = true;
            else result[field] += Number(value.calculation[field]);
        }
        if (unknown) result.unknown++;
    }
    return result;
}

export function formatHours(minutes) {
    if (minutes == null) return '—';
    const positive = Math.max(0, Number(minutes));
    return `${String(Math.floor(positive / 60)).padStart(2, '0')}:${String(positive % 60).padStart(2, '0')}`;
}

export function difference(before, after, hours = false) {
    if (before == null || after == null) return '—';
    const delta = after - before;
    return `${delta > 0 ? '+' : delta < 0 ? '−' : ''}${hours ? formatHours(Math.abs(delta)) : Math.abs(delta)}`;
}
