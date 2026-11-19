<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Field Forecast</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT@9..144,300..700,50&family=Instrument+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --paper: #f3eee3;
    --paper-deep: #e9e1d1;
    --card: #fbf8f2;
    --ink: #1f2a1c;
    --ink-soft: #5b6455;
    --rule: #d8cfbd;
    --open-meteo: #4f6b2e;
    --open-meteo-soft: rgba(79, 107, 46, .16);
    --weatherapi: #c0582f;
    --weatherapi-soft: rgba(192, 88, 47, .16);
    --rain: #2e5f86;
    --ok: #4f6b2e;
    --fail: #b23a2a;
    --radius: 14px;
    --display: "Fraunces", Georgia, serif;
    --body: "Instrument Sans", "Helvetica Neue", sans-serif;
    --mono: "JetBrains Mono", ui-monospace, monospace;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background-color: var(--paper);
    background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.05'/%3E%3C/svg%3E");
    color: var(--ink);
    font-family: var(--body);
    font-size: 15px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

.page { max-width: 1180px; margin: 0 auto; padding: 0 28px 80px; }

/* masthead */
.masthead {
    position: relative;
    overflow: hidden;
    padding: 34px 0 30px;
    border-bottom: 1px solid var(--rule);
}
.masthead::before {
    content: "";
    position: absolute;
    inset: -40% -10% -40% 35%;
    background: repeating-radial-gradient(ellipse at 70% 55%, transparent 0 22px, rgba(79, 107, 46, .13) 23px 24px);
    mask-image: radial-gradient(ellipse at 70% 55%, #000 30%, transparent 70%);
    pointer-events: none;
}
.topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--mono);
    font-size: 11.5px;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--ink-soft);
}
.brand { display: flex; align-items: center; gap: 10px; }
.brand svg { color: var(--open-meteo); }
h1 {
    position: relative;
    margin: 26px 0 6px;
    font-family: var(--display);
    font-weight: 380;
    font-variation-settings: "SOFT" 50, "opsz" 144;
    font-size: clamp(44px, 7vw, 86px);
    line-height: .95;
    letter-spacing: -.02em;
}
h1 em { font-style: italic; font-weight: 300; color: var(--open-meteo); }
.meta {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    gap: 8px 22px;
    font-family: var(--mono);
    font-size: 12.5px;
    color: var(--ink-soft);
}
.meta b { color: var(--ink); font-weight: 500; }
select {
    font: inherit;
    font-family: var(--mono);
    color: var(--ink);
    background: var(--card);
    border: 1px solid var(--rule);
    border-radius: 999px;
    padding: 6px 30px 6px 14px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%231f2a1c' stroke-width='1.4'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    cursor: pointer;
}
select:focus-visible, button:focus-visible { outline: 2px solid var(--open-meteo); outline-offset: 2px; }

.legend { display: flex; gap: 16px; align-items: center; }
.chip { display: inline-flex; align-items: center; gap: 7px; font-family: var(--mono); font-size: 12px; }
.chip i { width: 18px; height: 3px; border-radius: 2px; display: inline-block; }
.chip.open_meteo i { background: var(--open-meteo); }
.chip.weatherapi i { background: var(--weatherapi); }

/* sections */
section { margin-top: 54px; opacity: 0; transform: translateY(14px); animation: rise .7s cubic-bezier(.2, .7, .2, 1) forwards; }
section:nth-of-type(2) { animation-delay: .08s; }
section:nth-of-type(3) { animation-delay: .16s; }
section:nth-of-type(4) { animation-delay: .24s; }
@keyframes rise { to { opacity: 1; transform: none; } }

.section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 18px;
}
.eyebrow { font-family: var(--mono); font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: var(--ink-soft); }
h2 {
    margin: 4px 0 0;
    font-family: var(--display);
    font-weight: 420;
    font-variation-settings: "SOFT" 50, "opsz" 72;
    font-size: 30px;
    letter-spacing: -.01em;
}
.hint { max-width: 420px; color: var(--ink-soft); font-size: 13.5px; text-align: right; }

/* day cards */
.days {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}
.day {
    background: var(--card);
    border: 1px solid var(--rule);
    border-radius: var(--radius);
    padding: 16px 16px 14px;
    transition: transform .25s ease, box-shadow .25s ease;
}
.day:hover { transform: translateY(-3px); box-shadow: 0 14px 30px -18px rgba(31, 42, 28, .45); }
.day.today { background: var(--ink); color: var(--paper); border-color: var(--ink); }
.day.today .muted, .day.today .row small { color: #b9bfae; }
.day-name { font-family: var(--display); font-size: 22px; font-weight: 440; line-height: 1; }
.day-date { font-family: var(--mono); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.muted { color: var(--ink-soft); }
.row { margin-top: 12px; }
.row small { display: block; font-family: var(--mono); font-size: 11px; color: var(--ink-soft); white-space: nowrap; }
.range { position: relative; height: 6px; margin: 6px 0 4px; border-radius: 3px; background: rgba(127, 127, 110, .16); }
.range span { position: absolute; top: 0; bottom: 0; border-radius: 3px; }
.row.open_meteo .range span { background: var(--open-meteo); }
.row.weatherapi .range span { background: var(--weatherapi); }
.day.today .row.open_meteo .range span { background: #9fbf6b; }
.day.today .row.weatherapi .range span { background: #ec8a5f; }
.temps { display: flex; justify-content: space-between; font-family: var(--mono); font-size: 13px; }
.rain { display: flex; align-items: center; gap: 5px; margin-top: 2px; font-family: var(--mono); font-size: 11px; color: var(--ink-soft); }
.day.today .rain { color: #b9bfae; }
.rain svg { color: var(--rain); }
.day.today .rain svg { color: #8fc0e6; }
.spread {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px dashed var(--rule);
    font-family: var(--mono);
    font-size: 11px;
    display: flex;
    justify-content: space-between;
}
.day.today .spread { border-top-color: #4a5446; }
.na { font-family: var(--mono); font-size: 11px; color: var(--ink-soft); opacity: .8; padding: 5px 0 3px; }

/* chart */
.panel {
    overflow-x: auto;
    background: var(--card);
    border: 1px solid var(--rule);
    border-radius: var(--radius);
    padding: 18px 18px 10px;
}
.toggle { display: inline-flex; padding: 3px; background: var(--paper-deep); border-radius: 999px; }
.toggle button {
    border: 0;
    background: transparent;
    font-family: var(--mono);
    font-size: 12px;
    color: var(--ink-soft);
    padding: 6px 14px;
    border-radius: 999px;
    cursor: pointer;
}
.toggle button[aria-pressed="true"] { background: var(--ink); color: var(--paper); }
.chart-wrap { position: relative; }
.chart-wrap svg { display: block; width: 100%; min-width: 760px; height: auto; }
.axis text { font-family: var(--mono); font-size: 10.5px; fill: var(--ink-soft); }
.grid line { stroke: var(--rule); stroke-width: 1; }
.grid line.day { stroke: #c6bba5; stroke-dasharray: 2 4; }
.dry { font-family: var(--mono); font-size: 11px; fill: var(--ink-soft); }
.line { fill: none; stroke-width: 2.2; stroke-linejoin: round; stroke-linecap: round; }
.line.open_meteo { stroke: var(--open-meteo); }
.line.weatherapi { stroke: var(--weatherapi); }
.area.open_meteo { fill: var(--open-meteo-soft); }
.area.weatherapi { fill: var(--weatherapi-soft); }
.bar.open_meteo { fill: var(--open-meteo); opacity: .55; }
.bar.weatherapi { fill: var(--weatherapi); opacity: .55; }
.cursor { stroke: var(--ink); stroke-width: 1; stroke-dasharray: 3 3; }
.tooltip {
    position: absolute;
    top: 8px;
    pointer-events: none;
    background: var(--ink);
    color: var(--paper);
    border-radius: 10px;
    padding: 9px 12px;
    font-family: var(--mono);
    font-size: 11.5px;
    line-height: 1.6;
    white-space: nowrap;
    opacity: 0;
    transition: opacity .12s;
}
.tooltip b { font-weight: 500; color: #fff; }
.tooltip .om { color: #b4d07f; }
.tooltip .wa { color: #f19a72; }

/* revisions + runs */
.split { display: grid; grid-template-columns: 1.25fr 1fr; gap: 22px; align-items: start; }
.split > div { min-width: 0; }
table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
th {
    text-align: left;
    font-family: var(--mono);
    font-size: 10.5px;
    letter-spacing: .12em;
    text-transform: uppercase;
    font-weight: 500;
    color: var(--ink-soft);
    padding: 0 10px 10px 0;
    border-bottom: 1px solid var(--rule);
}
td { padding: 10px 10px 10px 0; border-bottom: 1px solid var(--rule); vertical-align: top; }
tr:last-child td { border-bottom: 0; }
td.num { font-family: var(--mono); font-size: 12.5px; white-space: nowrap; }
.provider-name { display: inline-flex; align-items: center; gap: 8px; font-weight: 500; }
.provider-name::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.provider-name.open_meteo { color: var(--open-meteo); }
.provider-name.weatherapi { color: var(--weatherapi); }
.pill {
    display: inline-block;
    font-family: var(--mono);
    font-size: 10.5px;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid currentColor;
}
.pill.succeeded { color: var(--ok); }
.pill.failed { color: var(--fail); }
.error-text { color: var(--fail); font-size: 12px; margin-top: 3px; }
.empty { color: var(--ink-soft); font-size: 13.5px; padding: 18px 0 8px; }
.notice {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--paper-deep);
    color: var(--ink-soft);
    font-size: 13px;
}
.alert { margin-top: 24px; padding: 14px 16px; border-radius: 10px; background: #f6dcd4; color: #7a2516; display: none; }

footer {
    margin-top: 64px;
    padding-top: 18px;
    border-top: 1px solid var(--rule);
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    font-family: var(--mono);
    font-size: 11.5px;
    color: var(--ink-soft);
}
footer a { color: inherit; }

@media (max-width: 860px) {
    .split { grid-template-columns: 1fr; }
    .section-head { flex-direction: column; align-items: flex-start; }
    .hint { text-align: left; }
}
@media (max-width: 520px) {
    .page { padding: 0 16px 60px; }
    .topline .legend { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    section { animation: none; opacity: 1; transform: none; }
    .day { transition: none; }
}
</style>
</head>
<body>
<div class="page">
    <header class="masthead">
        <div class="topline">
            <span class="brand">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21V11"/><path d="M12 11c0-4 3-7 7-7 0 4-3 7-7 7z"/><path d="M12 14c0-3-2.5-5.5-6-5.5 0 3 2.5 5.5 6 5.5z"/></svg>
                Field Forecast
            </span>
            <span class="legend">
                <span class="chip open_meteo"><i></i>Open-Meteo</span>
                <span class="chip weatherapi"><i></i>WeatherAPI</span>
            </span>
        </div>
        <h1 id="title">Loading<em>…</em></h1>
        <div class="meta">
            <span id="coords"></span>
            <span id="issued"></span>
            <label id="location-picker" hidden>Location <select id="location-select" aria-label="Location"></select></label>
        </div>
        <div class="alert" id="alert" role="alert"></div>
    </header>

    <section aria-labelledby="days-title">
        <div class="section-head">
            <div>
                <div class="eyebrow">Daily outlook</div>
                <h2 id="days-title">The week ahead</h2>
            </div>
            <p class="hint">Minimum to maximum temperature on a shared scale. Spread is how far the two providers disagree on the high.</p>
        </div>
        <div class="days" id="days"></div>
    </section>

    <section aria-labelledby="hourly-title">
        <div class="section-head">
            <div>
                <div class="eyebrow">Hour by hour</div>
                <h2 id="hourly-title">Temperature and rain</h2>
            </div>
            <div class="toggle" role="group" aria-label="Range">
                <button type="button" data-hours="72" aria-pressed="true">3 days</button>
                <button type="button" data-hours="168" aria-pressed="false">7 days</button>
            </div>
        </div>
        <div class="panel">
            <div class="chart-wrap" id="chart">
                <div class="tooltip" id="tooltip"></div>
            </div>
        </div>
    </section>

    <section aria-labelledby="history-title">
        <div class="split">
            <div>
                <div class="section-head">
                    <div>
                        <div class="eyebrow">Forecast history</div>
                        <h2 id="history-title">How the forecast changed</h2>
                    </div>
                    <select id="revision-date" aria-label="Forecast day"></select>
                </div>
                <div class="panel">
                    <div id="revisions"></div>
                </div>
            </div>
            <div>
                <div class="section-head">
                    <div>
                        <div class="eyebrow">Collector</div>
                        <h2>Recent runs</h2>
                    </div>
                </div>
                <div class="panel">
                    <div id="runs"></div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <span>Data collected daily from Open-Meteo and WeatherAPI.</span>
        <span>JSON API under <a href="/api/locations">/api</a></span>
    </footer>
</div>

<script>
const PROVIDERS = {
    open_meteo: { label: 'Open-Meteo', short: 'om' },
    weatherapi: { label: 'WeatherAPI', short: 'wa' },
};
const SVG_NS = 'http://www.w3.org/2000/svg';

const state = { location: null, hourly: [], hours: 72 };

const $ = (id) => document.getElementById(id);
const fmt = (value, digits = 1) => (value === null || value === undefined ? '–' : Number(value).toFixed(digits));

async function api(path) {
    const response = await fetch(path, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`${path} returned ${response.status}`);
    }
    return response.json();
}

function showError(error) {
    const alert = $('alert');
    alert.textContent = `Could not load data: ${error.message}`;
    alert.style.display = 'block';
}

function el(tag, attrs = {}, children = []) {
    const node = tag.startsWith('svg:') ? document.createElementNS(SVG_NS, tag.slice(4)) : document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (key === 'text') node.textContent = value;
        else if (key === 'html') node.innerHTML = value;
        else node.setAttribute(key, value);
    }
    for (const child of [].concat(children)) {
        if (child !== null && child !== undefined) node.append(child);
    }
    return node;
}

function dateLabel(isoDate, options) {
    return new Intl.DateTimeFormat('en-GB', { timeZone: 'UTC', ...options }).format(new Date(`${isoDate}T00:00:00Z`));
}

function localDate(date, timeZone) {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}

const rainIcon = () => el('span', { html: '<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5c-3.6 5-6.5 8.4-6.5 12A6.5 6.5 0 0 0 18.5 14.5c0-3.6-2.9-7-6.5-12z"/></svg>' }).firstChild;

/* ---------- header ---------- */

function renderHeader(location, snapshots) {
    $('title').replaceChildren(document.createTextNode(location.name), el('em', { text: '.' }));
    $('coords').innerHTML = `<b>${location.latitude.toFixed(3)}°N ${location.longitude.toFixed(3)}°E</b> · ${location.timezone}`;
    const issued = snapshots.map((snapshot) => snapshot.issue_date).sort().pop();
    $('issued').innerHTML = issued
        ? `Latest forecast <b>${dateLabel(issued, { day: 'numeric', month: 'short', year: 'numeric' })}</b>`
        : 'No forecasts collected yet';
    document.title = `${location.name} · Field Forecast`;
}

/* ---------- daily cards ---------- */

function renderDays(snapshots, location) {
    const byProvider = Object.fromEntries(snapshots.map((s) => [s.provider, new Map(s.daily.map((d) => [d.date, d]))]));
    const dates = [...new Set(snapshots.flatMap((s) => s.daily.map((d) => d.date)))].sort();
    const container = $('days');

    if (dates.length === 0) {
        container.replaceChildren(el('p', { class: 'empty', text: 'No daily forecast yet. Run php artisan forecasts:collect.' }));
        return;
    }

    const values = snapshots.flatMap((s) => s.daily.flatMap((d) => [d.temperature_min_c, d.temperature_max_c])).filter((v) => v !== null);
    const low = Math.floor(Math.min(...values)) - 1;
    const high = Math.ceil(Math.max(...values)) + 1;
    const position = (value) => ((value - low) / (high - low)) * 100;
    const today = localDate(new Date(), location.timezone);

    container.replaceChildren(...dates.map((date) => {
        const rows = Object.keys(PROVIDERS).map((provider) => {
            const day = byProvider[provider]?.get(date);
            if (!day) {
                return el('div', { class: `row ${provider}` }, [
                    el('small', { text: PROVIDERS[provider].label }),
                    el('div', { class: 'na', text: 'not in forecast range' }),
                ]);
            }
            const left = position(day.temperature_min_c);
            const width = Math.max(position(day.temperature_max_c) - left, 2);
            return el('div', { class: `row ${provider}` }, [
                el('small', { text: PROVIDERS[provider].label }),
                el('div', { class: 'range' }, [el('span', { style: `left:${left}%;width:${width}%` })]),
                el('div', { class: 'temps' }, [el('span', { text: `${fmt(day.temperature_min_c)}°` }), el('span', { text: `${fmt(day.temperature_max_c)}°` })]),
                el('div', { class: 'rain' }, [rainIcon(), `${fmt(day.precipitation_mm)} mm`]),
            ]);
        });

        const om = byProvider.open_meteo?.get(date);
        const wa = byProvider.weatherapi?.get(date);
        const spread = om && wa ? Math.abs(om.temperature_max_c - wa.temperature_max_c) : null;

        return el('article', { class: `day${date === today ? ' today' : ''}` }, [
            el('div', { class: 'day-name', text: date === today ? 'Today' : dateLabel(date, { weekday: 'short' }) }),
            el('div', { class: 'day-date muted', text: dateLabel(date, { day: 'numeric', month: 'short' }) }),
            ...rows,
            spread === null ? null : el('div', { class: 'spread' }, [el('span', { class: 'muted', text: 'Spread' }), el('span', { text: `${fmt(spread)}°` })]),
        ]);
    }));
}

/* ---------- hourly chart ---------- */

function renderChart() {
    const wrap = $('chart');
    const tooltip = $('tooltip');
    wrap.querySelector('svg')?.remove();

    const series = state.hourly
        .map((s) => ({ provider: s.provider, points: s.hourly.map((h) => ({ t: Date.parse(h.time), temp: h.temperature_c, rain: h.precipitation_mm })) }))
        .filter((s) => s.points.length > 0);

    if (series.length === 0) {
        tooltip.style.opacity = 0;
        wrap.append(el('p', { class: 'empty', text: 'No hourly data yet.' }));
        return;
    }
    wrap.querySelector('.empty')?.remove();

    const start = Math.min(...series.map((s) => s.points[0].t));
    const end = start + (state.hours - 1) * 3600e3;
    series.forEach((s) => { s.points = s.points.filter((p) => p.t >= start && p.t <= end); });

    const W = 1100, H = 380, left = 44, right = 16, top = 26, rainBand = 70, gap = 26;
    const plotBottom = H - rainBand - gap - 22;
    const temps = series.flatMap((s) => s.points.map((p) => p.temp)).filter((v) => v !== null);
    const tMin = Math.floor(Math.min(...temps) - 1);
    const tMax = Math.ceil(Math.max(...temps) + 1);
    const rainMax = Math.max(1, ...series.flatMap((s) => s.points.map((p) => p.rain ?? 0)));

    const x = (t) => left + ((t - start) / (end - start)) * (W - left - right);
    const y = (v) => top + (1 - (v - tMin) / (tMax - tMin)) * (plotBottom - top);
    const rainTop = plotBottom + gap;
    const rainY = (v) => rainTop + rainBand - (v / rainMax) * rainBand;

    const svg = el('svg:svg', { viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': 'Hourly temperature and precipitation by provider' });
    const grid = el('svg:g', { class: 'grid' });
    const axis = el('svg:g', { class: 'axis' });

    const step = tMax - tMin > 16 ? 5 : 2;
    for (let v = Math.ceil(tMin / step) * step; v <= tMax; v += step) {
        grid.append(el('svg:line', { x1: left, x2: W - right, y1: y(v), y2: y(v) }));
        axis.append(el('svg:text', { x: left - 8, y: y(v) + 4, 'text-anchor': 'end', text: `${v}°` }));
    }

    const tz = state.location.timezone;
    const hourOf = (t) => Number(new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', hourCycle: 'h23' }).format(t));
    for (let t = start; t <= end; t += 3600e3) {
        if (hourOf(t) !== 0) continue;
        grid.append(el('svg:line', { class: 'day', x1: x(t), x2: x(t), y1: top - 10, y2: rainTop + rainBand }));
        const label = new Intl.DateTimeFormat('en-GB', { timeZone: tz, weekday: 'short', day: 'numeric' }).format(t);
        axis.append(el('svg:text', { x: x(t) + 6, y: top - 12, text: label }));
    }
    axis.append(el('svg:text', { x: left - 8, y: rainTop + 10, 'text-anchor': 'end', text: 'mm' }));
    const hasRain = series.some((s) => s.points.some((p) => p.rain > 0));
    if (hasRain) {
        axis.append(el('svg:text', { x: left - 8, y: rainTop + 24, 'text-anchor': 'end', text: fmt(rainMax) }));
    } else {
        svg.append(el('svg:text', { class: 'dry', x: (left + W - right) / 2, y: rainTop + rainBand / 2 + 4, 'text-anchor': 'middle', text: 'No rain expected in this period' }));
    }
    grid.append(el('svg:line', { x1: left, x2: W - right, y1: rainTop + rainBand, y2: rainTop + rainBand }));
    svg.append(grid, axis);

    const barWidth = Math.max(((W - left - right) / state.hours) / series.length - 1, 1);
    series.forEach((s, index) => {
        const valid = s.points.filter((p) => p.temp !== null);
        if (valid.length > 1) {
            const path = valid.map((p, i) => `${i ? 'L' : 'M'}${x(p.t).toFixed(1)},${y(p.temp).toFixed(1)}`).join('');
            const area = `${path}L${x(valid.at(-1).t).toFixed(1)},${plotBottom}L${x(valid[0].t).toFixed(1)},${plotBottom}Z`;
            svg.append(el('svg:path', { class: `area ${s.provider}`, d: area, opacity: .55 }));
            const line = el('svg:path', { class: `line ${s.provider}`, d: path });
            svg.append(line);
            const length = line.getTotalLength?.() ?? 0;
            if (length && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
                line.style.strokeDasharray = length;
                line.style.strokeDashoffset = length;
                line.animate([{ strokeDashoffset: length }, { strokeDashoffset: 0 }], { duration: 1100, easing: 'cubic-bezier(.3,.7,.2,1)', fill: 'forwards' });
            }
        }
        s.points.filter((p) => p.rain > 0).forEach((p) => {
            svg.append(el('svg:rect', {
                class: `bar ${s.provider}`,
                x: x(p.t) - (barWidth * series.length) / 2 + index * barWidth,
                y: rainY(p.rain),
                width: barWidth,
                height: rainTop + rainBand - rainY(p.rain),
            }));
        });
    });

    const cursor = el('svg:line', { class: 'cursor', y1: top - 10, y2: rainTop + rainBand, opacity: 0 });
    const dots = series.map((s) => el('svg:circle', { r: 4.5, class: s.provider, fill: `var(--${s.provider.replace('_', '-')})`, stroke: '#fbf8f2', 'stroke-width': 2, opacity: 0 }));
    svg.append(cursor, ...dots);

    const overlay = el('svg:rect', { x: left, y: top - 10, width: W - left - right, height: rainTop + rainBand - top + 10, fill: 'transparent' });
    svg.append(overlay);

    overlay.addEventListener('pointermove', (event) => {
        const box = svg.getBoundingClientRect();
        const svgX = ((event.clientX - box.left) / box.width) * W;
        const t = start + Math.round(((svgX - left) / (W - left - right)) * (end - start) / 3600e3) * 3600e3;
        const lines = [];
        cursor.setAttribute('x1', x(t));
        cursor.setAttribute('x2', x(t));
        cursor.setAttribute('opacity', 1);
        series.forEach((s, i) => {
            const point = s.points.find((p) => p.t === t);
            dots[i].setAttribute('opacity', point && point.temp !== null ? 1 : 0);
            if (point && point.temp !== null) {
                dots[i].setAttribute('cx', x(t));
                dots[i].setAttribute('cy', y(point.temp));
                lines.push(`<span class="${PROVIDERS[s.provider].short}">${PROVIDERS[s.provider].label}</span> ${fmt(point.temp)}° · ${fmt(point.rain)} mm`);
            }
        });
        const when = new Intl.DateTimeFormat('en-GB', { timeZone: tz, weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(t);
        tooltip.innerHTML = `<b>${when}</b><br>${lines.join('<br>') || 'no data'}`;
        const px = (x(t) / W) * box.width;
        tooltip.style.left = `${px > box.width * .65 ? px - tooltip.offsetWidth - 14 : px + 14}px`;
        tooltip.style.opacity = 1;
    });
    overlay.addEventListener('pointerleave', () => {
        cursor.setAttribute('opacity', 0);
        dots.forEach((dot) => dot.setAttribute('opacity', 0));
        tooltip.style.opacity = 0;
    });

    wrap.prepend(svg);
}

/* ---------- revisions ---------- */

async function renderRevisions(date) {
    const target = $('revisions');
    if (!date) {
        target.replaceChildren(el('p', { class: 'empty', text: 'No forecast days yet.' }));
        return;
    }
    const { data } = await api(`/api/locations/${state.location.id}/forecasts/daily/${date}/revisions`);
    if (data.length === 0) {
        target.replaceChildren(el('p', { class: 'empty', text: 'No forecasts were issued for this day.' }));
        return;
    }

    const rows = data.flatMap((group) => group.revisions.map((revision) => el('tr', {}, [
        el('td', {}, [el('span', { class: `provider-name ${group.provider}`, text: PROVIDERS[group.provider]?.label ?? group.provider })]),
        el('td', { class: 'num', text: dateLabel(revision.issue_date, { day: 'numeric', month: 'short' }) }),
        el('td', { class: 'num', text: revision.lead_days === 0 ? 'same day' : `${revision.lead_days} d ahead` }),
        el('td', { class: 'num', text: `${fmt(revision.temperature_min_c)}° / ${fmt(revision.temperature_max_c)}°` }),
        el('td', { class: 'num', text: `${fmt(revision.precipitation_mm)} mm` }),
    ])));

    const table = el('table', {}, [
        el('thead', {}, [el('tr', {}, ['Provider', 'Issued', 'Lead', 'Min / max', 'Rain'].map((h) => el('th', { text: h })))]),
        el('tbody', {}, rows),
    ]);

    const single = data.every((group) => group.revisions.length < 2);
    target.replaceChildren(table, single
        ? el('div', { class: 'notice', text: 'Each day keeps its own snapshot, so this list grows as the same day is forecast again on the following mornings.' })
        : null);
}

/* ---------- runs ---------- */

async function renderRuns() {
    const { data } = await api(`/api/forecast-runs?location_id=${state.location.id}`);
    const target = $('runs');
    if (data.length === 0) {
        target.replaceChildren(el('p', { class: 'empty', text: 'No collection runs yet.' }));
        return;
    }
    const tz = state.location.timezone;
    const time = (iso) => (iso ? new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit' }).format(new Date(iso)) : '–');

    target.replaceChildren(el('table', {}, [
        el('thead', {}, [el('tr', {}, ['Day', 'Provider', 'Status', 'Tries', 'At'].map((h) => el('th', { text: h })))]),
        el('tbody', {}, data.slice(0, 8).map((run) => el('tr', {}, [
            el('td', { class: 'num', text: dateLabel(run.issue_date, { day: 'numeric', month: 'short' }) }),
            el('td', {}, [
                el('span', { class: `provider-name ${run.provider}`, text: PROVIDERS[run.provider]?.label ?? run.provider }),
                run.error ? el('div', { class: 'error-text', text: run.error }) : null,
            ]),
            el('td', {}, [el('span', { class: `pill ${run.status}`, text: run.status })]),
            el('td', { class: 'num', text: String(run.attempts) }),
            el('td', { class: 'num', text: time(run.fetched_at ?? run.updated_at) }),
        ]))),
    ]));
}

/* ---------- wiring ---------- */

async function loadLocation(location) {
    state.location = location;
    const [daily, hourly] = await Promise.all([
        api(`/api/locations/${location.id}/forecasts/daily`),
        api(`/api/locations/${location.id}/forecasts/hourly`),
    ]);

    renderHeader(location, daily.data);
    renderDays(daily.data, location);

    state.hourly = hourly.data;
    renderChart();

    const dates = [...new Set(daily.data.flatMap((s) => s.daily.map((d) => d.date)))].sort();
    const select = $('revision-date');
    select.replaceChildren(...dates.map((date) => el('option', { value: date, text: dateLabel(date, { weekday: 'short', day: 'numeric', month: 'short' }) })));
    select.value = dates[Math.min(2, dates.length - 1)] ?? '';
    select.hidden = dates.length === 0;

    await Promise.all([renderRevisions(select.value), renderRuns()]);
}

document.querySelectorAll('.toggle button').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.toggle button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
        state.hours = Number(button.dataset.hours);
        renderChart();
    });
});

$('revision-date').addEventListener('change', (event) => renderRevisions(event.target.value).catch(showError));

(async () => {
    try {
        const { data: locations } = await api('/api/locations');
        if (locations.length === 0) {
            $('title').textContent = 'No locations';
            return;
        }
        if (locations.length > 1) {
            const select = $('location-select');
            select.replaceChildren(...locations.map((l) => el('option', { value: l.id, text: l.name })));
            select.addEventListener('change', () => loadLocation(locations.find((l) => String(l.id) === select.value)).catch(showError));
            $('location-picker').hidden = false;
        }
        await loadLocation(locations[0]);
    } catch (error) {
        showError(error);
    }
})();
</script>
</body>
</html>
