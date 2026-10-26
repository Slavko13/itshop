import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const rawDir = process.env.LIGHTHOUSE_RAW_DIR
  ? path.resolve(root, process.env.LIGHTHOUSE_RAW_DIR)
  : path.join(root, 'lighthouse-comparison', 'raw');
// The repository already contains a file named `tmp`, so use the existing
// build workspace rather than replacing user data with a directory.
const tempDir = path.join(root, '.build', 'pdfs');
const outputDir = path.join(root, 'output', 'pdf');
const htmlPath = path.join(tempDir, 'itsoft-lighthouse-comparison.html');
const dataPath = path.join(tempDir, 'itsoft-lighthouse-comparison-data.json');
const pdfPath = process.env.LIGHTHOUSE_PDF_PATH
  ? path.resolve(root, process.env.LIGHTHOUSE_PDF_PATH)
  : path.join(outputDir, 'ITSOFT_Lighthouse_Comparison_2026-10-26.pdf');
const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

fs.mkdirSync(tempDir, { recursive: true });
fs.mkdirSync(outputDir, { recursive: true });

const siteMeta = {
  itsoft: { name: 'IT Софт', domain: 'test.itsoft.store', color: '#6C43FF', accent: '#EFEAFF' },
  keysoft: { name: 'Keysoft', domain: 'keysoft.store', color: '#24262B', accent: '#E9EAEC' },
  rusoft: { name: 'Rusoft', domain: 'rusoft.shop', color: '#0A84FF', accent: '#E7F3FF' },
};

const pageMeta = {
  home: { name: 'Главная', short: 'Главная' },
  category: { name: 'Категория Microsoft Office', short: 'Категория' },
  product: { name: 'Карточка Office 2021 Pro Plus', short: 'Товар' },
};

const urls = {
  itsoft: {
    home: 'https://test.itsoft.store/',
    category: 'https://test.itsoft.store/product-category/microsoft-office/',
    product: 'https://test.itsoft.store/product/microsoft-office-2021-professional-plus/',
  },
  keysoft: {
    home: 'https://keysoft.store/',
    category: 'https://keysoft.store/microsoft-office',
    product: 'https://keysoft.store/microsoft-office/buy-office-2021-professional-Plus-key/3226383',
  },
  rusoft: {
    home: 'https://rusoft.shop/',
    category: 'https://rusoft.shop/product-category/catalog/microsoft-office/',
    product: 'https://rusoft.shop/product/microsoft-office-2021-pro/',
  },
};

const median = (values) => {
  const nums = values.filter((value) => Number.isFinite(value)).sort((a, b) => a - b);
  if (!nums.length) return 0;
  const middle = Math.floor(nums.length / 2);
  return nums.length % 2 ? nums[middle] : (nums[middle - 1] + nums[middle]) / 2;
};

const average = (values) => values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : 0;
const score = (report, key) => Math.round((report.categories?.[key]?.score ?? 0) * 100);
const auditNumber = (report, key) => Number(report.audits?.[key]?.numericValue ?? 0);
const auditSavings = (report, keys) => {
  for (const key of keys) {
    const audit = report.audits?.[key];
    if (!audit) continue;
    const direct = Number(audit.details?.overallSavingsBytes ?? audit.numericValue ?? 0);
    if (direct > 0) return direct;
    const items = audit.details?.items ?? [];
    const sum = items.reduce((total, item) => total + Number(item.wastedBytes ?? item.wastedBytesByResourceType ?? 0), 0);
    if (sum > 0) return sum;
  }
  return 0;
};

function failingAudits(report, categoryKey) {
  const refs = report.categories?.[categoryKey]?.auditRefs ?? [];
  return refs
    .filter((ref) => (ref.weight ?? 0) > 0)
    .map((ref) => report.audits?.[ref.id])
    .filter((audit) => audit && audit.scoreDisplayMode !== 'notApplicable' && audit.score !== null && audit.score < 1)
    .map((audit) => ({ id: audit.id, title: audit.title, score: audit.score, displayValue: audit.displayValue ?? '' }));
}

function extractReport(report) {
  const requests = report.audits?.['network-requests']?.details?.items ?? [];
  const resources = { document: 0, css: 0, js: 0, image: 0, font: 0, other: 0 };
  for (const request of requests) {
    const bytes = Number(request.transferSize ?? 0);
    const type = String(request.resourceType ?? '').toLowerCase();
    if (type === 'document') resources.document += bytes;
    else if (type === 'stylesheet') resources.css += bytes;
    else if (type === 'script') resources.js += bytes;
    else if (type === 'image') resources.image += bytes;
    else if (type === 'font') resources.font += bytes;
    else resources.other += bytes;
  }

  return {
    performance: score(report, 'performance'),
    accessibility: score(report, 'accessibility'),
    bestPractices: score(report, 'best-practices'),
    seo: score(report, 'seo'),
    fcp: auditNumber(report, 'first-contentful-paint'),
    lcp: auditNumber(report, 'largest-contentful-paint'),
    speedIndex: auditNumber(report, 'speed-index'),
    tbt: auditNumber(report, 'total-blocking-time'),
    cls: auditNumber(report, 'cumulative-layout-shift'),
    ttfb: auditNumber(report, 'server-response-time'),
    totalBytes: auditNumber(report, 'total-byte-weight'),
    requests: requests.length,
    resources,
    savings: {
      images: auditSavings(report, ['image-delivery-insight', 'uses-optimized-images', 'modern-image-formats']),
      javascript: auditSavings(report, ['unused-javascript']),
      css: auditSavings(report, ['unused-css-rules']),
      cache: auditSavings(report, ['cache-insight', 'uses-long-cache-ttl']),
    },
    seoFailures: failingAudits(report, 'seo'),
    accessibilityFailures: failingAudits(report, 'accessibility'),
    bestPracticeFailures: failingAudits(report, 'best-practices'),
    finalUrl: report.finalDisplayedUrl ?? report.finalUrl ?? '',
    fetchTime: report.fetchTime ?? '',
    lighthouseVersion: report.lighthouseVersion ?? '',
  };
}

const files = fs.readdirSync(rawDir).filter((name) => name.endsWith('.json'));
const grouped = new Map();
for (const name of files) {
  const match = name.match(/^(itsoft|keysoft|rusoft)-(home|category|product)-(mobile|desktop)-run(\d+)\.json$/);
  if (!match) continue;
  const report = JSON.parse(fs.readFileSync(path.join(rawDir, name), 'utf8'));
  if (report.runtimeError) continue;
  const key = `${match[1]}|${match[2]}|${match[3]}`;
  if (!grouped.has(key)) grouped.set(key, []);
  grouped.get(key).push(extractReport(report));
}

function aggregateRuns(runs) {
  const scalarKeys = ['performance', 'accessibility', 'bestPractices', 'seo', 'fcp', 'lcp', 'speedIndex', 'tbt', 'cls', 'ttfb', 'totalBytes', 'requests'];
  const out = {};
  for (const key of scalarKeys) out[key] = median(runs.map((run) => run[key]));
  out.resources = {};
  for (const key of ['document', 'css', 'js', 'image', 'font', 'other']) out.resources[key] = median(runs.map((run) => run.resources[key]));
  out.savings = {};
  for (const key of ['images', 'javascript', 'css', 'cache']) out.savings[key] = median(runs.map((run) => run.savings[key]));
  const all = (key) => runs.flatMap((run) => run[key] ?? []);
  const unique = (items) => [...new Map(items.map((item) => [item.id, item])).values()];
  out.seoFailures = unique(all('seoFailures'));
  out.accessibilityFailures = unique(all('accessibilityFailures'));
  out.bestPracticeFailures = unique(all('bestPracticeFailures'));
  out.finalUrl = runs[0]?.finalUrl ?? '';
  out.fetchTime = runs[0]?.fetchTime ?? '';
  out.lighthouseVersion = runs[0]?.lighthouseVersion ?? '';
  out.runCount = runs.length;
  return out;
}

const results = {};
for (const site of Object.keys(siteMeta)) {
  results[site] = {};
  for (const page of Object.keys(pageMeta)) {
    results[site][page] = {};
    for (const profile of ['mobile', 'desktop']) {
      const key = `${site}|${page}|${profile}`;
      const runs = grouped.get(key) ?? [];
      if (!runs.length) throw new Error(`Missing Lighthouse data: ${key}`);
      results[site][page][profile] = aggregateRuns(runs);
    }
  }
}

const siteSummary = {};
for (const site of Object.keys(siteMeta)) {
  const mobile = Object.keys(pageMeta).map((page) => results[site][page].mobile);
  const desktop = Object.keys(pageMeta).map((page) => results[site][page].desktop);
  const all = [...mobile, ...desktop];
  siteSummary[site] = {
    mobilePerformance: average(mobile.map((item) => item.performance)),
    desktopPerformance: average(desktop.map((item) => item.performance)),
    accessibility: average(all.map((item) => item.accessibility)),
    bestPractices: average(all.map((item) => item.bestPractices)),
    seo: average(all.map((item) => item.seo)),
    bytes: average(all.map((item) => item.totalBytes)),
    requests: average(all.map((item) => item.requests)),
    lcpMobile: average(mobile.map((item) => item.lcp)),
    lcpDesktop: average(desktop.map((item) => item.lcp)),
  };
}

const reportData = {
  generatedAt: new Date().toISOString(),
  methodology: { lighthouseVersion: results.itsoft.home.mobile.lighthouseVersion, runsPerProfile: 2 },
  urls,
  results,
  siteSummary,
};
fs.writeFileSync(dataPath, JSON.stringify(reportData, null, 2), 'utf8');

const beforeDataPath = process.env.LIGHTHOUSE_BEFORE_DATA
  ? path.resolve(root, process.env.LIGHTHOUSE_BEFORE_DATA)
  : path.join(tempDir, 'itsoft-lighthouse-comparison-data-before-update.json');
const beforeReportData = fs.existsSync(beforeDataPath)
  ? JSON.parse(fs.readFileSync(beforeDataPath, 'utf8'))
  : null;

const fmtScore = (value) => String(Math.round(value));
const fmtMs = (value) => value >= 1000 ? `${(value / 1000).toFixed(1)} с` : `${Math.round(value)} мс`;
const fmtBytes = (value) => {
  if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(2)} МБ`;
  return `${Math.round(value / 1024)} КБ`;
};
const fmtCount = (value) => String(Math.round(value));
const fmtCls = (value) => value.toFixed(3);
const esc = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

const beforeItsoft = beforeReportData?.results?.itsoft ?? null;
const beforeItsoftSummary = beforeReportData?.siteSummary?.itsoft ?? null;
const delta = (after, before, digits = 1) => Number(after - before).toFixed(digits);
const signed = (value, suffix = '') => `${Number(value) > 0 ? '+' : ''}${value}${suffix}`;

const scoreClass = (value) => value >= 90 ? 'good' : value >= 50 ? 'warn' : 'bad';
const metricClass = (name, value) => {
  const limits = {
    lcp: [2500, 4000],
    fcp: [1800, 3000],
    tbt: [200, 600],
    cls: [0.1, 0.25],
    ttfb: [800, 1800],
  };
  const [good, poor] = limits[name] ?? [Infinity, Infinity];
  return value <= good ? 'good' : value <= poor ? 'warn' : 'bad';
};

const scorePill = (value) => `<span class="score-pill ${scoreClass(value)}">${fmtScore(value)}</span>`;
const metricPill = (name, value, formatted) => `<span class="metric-pill ${metricClass(name, value)}">${formatted}</span>`;

function siteScoreCard(site) {
  const s = siteSummary[site];
  return `<article class="site-card" style="--site:${siteMeta[site].color};--accent:${siteMeta[site].accent}">
    <div class="site-card-head"><span class="site-dot"></span><div><strong>${siteMeta[site].name}</strong><small>${siteMeta[site].domain}</small></div></div>
    <div class="primary-score"><span>${fmtScore(s.mobilePerformance)}</span><small>Performance mobile</small></div>
    <div class="mini-grid">
      <div><b>${fmtScore(s.desktopPerformance)}</b><small>Desktop</small></div>
      <div><b>${fmtScore(s.seo)}</b><small>SEO</small></div>
      <div><b>${fmtScore(s.accessibility)}</b><small>Accessibility</small></div>
      <div><b>${fmtBytes(s.bytes)}</b><small>Средний вес</small></div>
    </div>
  </article>`;
}

function scoreTable(profile) {
  const rows = Object.keys(pageMeta).map((page) => {
    const cells = Object.keys(siteMeta).map((site) => {
      const r = results[site][page][profile];
      return `<td>${scorePill(r.performance)} <span class="cell-note">LCP ${fmtMs(r.lcp)} · ${fmtBytes(r.totalBytes)}</span></td>`;
    }).join('');
    return `<tr><th>${pageMeta[page].name}</th>${cells}</tr>`;
  }).join('');
  return `<table class="comparison-table"><thead><tr><th>Страница</th>${Object.keys(siteMeta).map((site) => `<th>${siteMeta[site].name}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table>`;
}

function detailedTable(page, profile) {
  const rows = Object.keys(siteMeta).map((site) => {
    const r = results[site][page][profile];
    return `<tr>
      <th><span class="brand-line" style="--brand:${siteMeta[site].color}"></span>${siteMeta[site].name}</th>
      <td>${scorePill(r.performance)}</td>
      <td>${metricPill('fcp', r.fcp, fmtMs(r.fcp))}</td>
      <td>${metricPill('lcp', r.lcp, fmtMs(r.lcp))}</td>
      <td>${metricPill('tbt', r.tbt, fmtMs(r.tbt))}</td>
      <td>${metricPill('cls', r.cls, fmtCls(r.cls))}</td>
      <td>${fmtBytes(r.totalBytes)}</td>
      <td>${fmtCount(r.requests)}</td>
    </tr>`;
  }).join('');
  return `<table class="data-table"><thead><tr><th>Сайт</th><th>Perf.</th><th>FCP</th><th>LCP</th><th>TBT</th><th>CLS</th><th>Вес</th><th>Запросы</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function resourceStack(site, profile) {
  const pages = Object.keys(pageMeta).map((page) => results[site][page][profile]);
  const totals = {};
  for (const key of ['document', 'css', 'js', 'image', 'font', 'other']) totals[key] = average(pages.map((item) => item.resources[key]));
  const total = Object.values(totals).reduce((sum, value) => sum + value, 0) || 1;
  const colors = { document: '#74809A', css: '#28B8A6', js: '#FFB13B', image: '#6C43FF', font: '#E8578E', other: '#CBD2DF' };
  const labels = { document: 'HTML', css: 'CSS', js: 'JS', image: 'Изображения', font: 'Шрифты', other: 'Прочее' };
  const segments = Object.keys(totals).map((key) => `<span title="${labels[key]} ${fmtBytes(totals[key])}" style="width:${(totals[key] / total * 100).toFixed(2)}%;background:${colors[key]}"></span>`).join('');
  return `<div class="resource-row"><div class="resource-label"><b>${siteMeta[site].name}</b><span>${fmtBytes(total)}</span></div><div class="stack">${segments}</div><div class="resource-details">${Object.keys(totals).map((key) => `<span><i style="background:${colors[key]}"></i>${labels[key]} ${fmtBytes(totals[key])}</span>`).join('')}</div></div>`;
}

function auditFailureRows(category, profile = 'mobile') {
  const rows = [];
  for (const site of Object.keys(siteMeta)) {
    const ids = new Map();
    for (const page of Object.keys(pageMeta)) {
      for (const item of results[site][page][profile][category]) {
        if (!ids.has(item.id)) ids.set(item.id, { ...item, pages: [] });
        ids.get(item.id).pages.push(pageMeta[page].short);
      }
    }
    const items = [...ids.values()];
    if (!items.length) {
      rows.push(`<tr><th>${siteMeta[site].name}</th><td><span class="ok-text">Критичных провалов не найдено</span></td></tr>`);
      continue;
    }
    rows.push(`<tr><th>${siteMeta[site].name}</th><td>${items.slice(0, 4).map((item) => `<div class="issue"><b>${esc(item.title)}</b><span>${esc(item.pages.join(', '))}</span></div>`).join('')}</td></tr>`);
  }
  return rows.join('');
}

function opportunityTable(site) {
  const rows = [];
  for (const page of Object.keys(pageMeta)) {
    const r = results[site][page].mobile;
    rows.push(`<tr><th>${pageMeta[page].name}</th><td>${fmtBytes(r.savings.images)}</td><td>${fmtBytes(r.savings.javascript)}</td><td>${fmtBytes(r.savings.css)}</td><td>${fmtBytes(r.savings.cache)}</td></tr>`);
  }
  return `<table class="data-table"><thead><tr><th>Страница</th><th>Изображения</th><th>JS</th><th>CSS</th><th>Кэш</th></tr></thead><tbody>${rows.join('')}</tbody></table>`;
}

function fullAppendixRows(profile) {
  const rows = [];
  for (const page of Object.keys(pageMeta)) {
    for (const site of Object.keys(siteMeta)) {
      const r = results[site][page][profile];
      rows.push(`<tr><td>${pageMeta[page].short}</td><td>${siteMeta[site].name}</td><td>${fmtScore(r.performance)}</td><td>${fmtScore(r.accessibility)}</td><td>${fmtScore(r.bestPractices)}</td><td>${fmtScore(r.seo)}</td><td>${fmtMs(r.fcp)}</td><td>${fmtMs(r.lcp)}</td><td>${fmtMs(r.tbt)}</td><td>${fmtCls(r.cls)}</td><td>${fmtBytes(r.totalBytes)}</td><td>${fmtCount(r.requests)}</td></tr>`);
    }
  }
  return rows.join('');
}

const mobileRanking = Object.keys(siteMeta).sort((a, b) => siteSummary[b].mobilePerformance - siteSummary[a].mobilePerformance);
const seoRanking = Object.keys(siteMeta).sort((a, b) => siteSummary[b].seo - siteSummary[a].seo);
const lightestRanking = Object.keys(siteMeta).sort((a, b) => siteSummary[a].bytes - siteSummary[b].bytes);
const itsoft = siteSummary.itsoft;
const mobileLeader = siteSummary[mobileRanking[0]];
const lightest = siteSummary[lightestRanking[0]];
const seoGap = Math.max(0, siteSummary[seoRanking[0]].seo - itsoft.seo);
const perfGap = mobileLeader.mobilePerformance - itsoft.mobilePerformance;

const beforeAfterSection = beforeItsoft && beforeItsoftSummary ? (() => {
  const rows = Object.keys(pageMeta).flatMap((page) => ['mobile', 'desktop'].map((profile) => {
    const previous = beforeItsoft[page][profile];
    const current = results.itsoft[page][profile];
    return `<tr>
      <td><b>${pageMeta[page].short}</b></td>
      <td>${profile === 'mobile' ? 'Mobile' : 'Desktop'}</td>
      <td>${fmtScore(previous.performance)} → ${fmtScore(current.performance)} <span class="change-note">${signed(delta(current.performance, previous.performance), ' п.')}</span></td>
      <td>${fmtMs(previous.lcp)} → ${fmtMs(current.lcp)} <span class="change-note">${signed(Math.round(current.lcp - previous.lcp), ' мс')}</span></td>
      <td>${fmtBytes(previous.totalBytes)} → ${fmtBytes(current.totalBytes)}</td>
    </tr>`;
  })).join('');
  const mobilePerfDelta = delta(itsoft.mobilePerformance, beforeItsoftSummary.mobilePerformance, 1);
  const desktopPerfDelta = delta(itsoft.desktopPerformance, beforeItsoftSummary.desktopPerformance, 1);
  const mobileLcpDelta = Math.round(itsoft.lcpMobile - beforeItsoftSummary.lcpMobile);
  const bytesDelta = Math.round(itsoft.bytes - beforeItsoftSummary.bytes);
  return `<section class="page" data-page="03">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Повторный замер</div></div>
    <div class="section-title"><div><div class="eyebrow">После обновления</div><h2>IT Софт: что изменилось</h2></div><p>12 новых запусков: три страницы, Mobile и Desktop, по два повтора.</p></div>
    <div class="change-summary">
      <div class="change-card"><span>Mobile Performance</span><b>${fmtScore(beforeItsoftSummary.mobilePerformance)} → ${fmtScore(itsoft.mobilePerformance)}</b><small>${signed(mobilePerfDelta, ' п.')}</small></div>
      <div class="change-card"><span>Desktop Performance</span><b>${fmtScore(beforeItsoftSummary.desktopPerformance)} → ${fmtScore(itsoft.desktopPerformance)}</b><small>${signed(desktopPerfDelta, ' п.')}</small></div>
      <div class="change-card"><span>Средний Mobile LCP</span><b>${fmtMs(beforeItsoftSummary.lcpMobile)} → ${fmtMs(itsoft.lcpMobile)}</b><small>${signed(mobileLcpDelta, ' мс')}</small></div>
      <div class="change-card"><span>Средний вес</span><b>${fmtBytes(beforeItsoftSummary.bytes)} → ${fmtBytes(itsoft.bytes)}</b><small>${signed(Math.round(bytesDelta / 1024), ' КБ')}</small></div>
    </div>
    <div class="subsection"><h3>Разница по каждой странице</h3>
      <table class="data-table"><thead><tr><th>Страница</th><th>Профиль</th><th>Performance</th><th>LCP</th><th>Передано</th></tr></thead><tbody>${rows}</tbody></table>
    </div>
    <div class="two-col subsection">
      <div class="panel"><h3>Положительная динамика</h3><p>Главная Mobile: LCP быстрее примерно на 0,6 с. Категория Desktop: Performance вырос на 4 пункта, LCP сократился примерно на 0,8 с. Средний переданный объём уменьшился примерно на 25 КБ.</p></div>
      <div class="panel"><h3>Что ещё требует внимания</h3><p>Средний Mobile Performance остался на уровне 75. У карточки товара Mobile LCP вырос примерно на 0,5 с; это может быть вариацией лабораторного запуска, но участок нужно перепроверить серией из 3-5 прогонов.</p></div>
    </div>
    <div class="callout"><b>Интерпретация</b><p>Обновление не ухудшило сайт системно и дало локальные выигрыши. Lighthouse SEO теперь стабильно равен ${fmtScore(itsoft.seo)}, а основная зона роста — мобильный LCP категории и карточки товара.</p></div>
  </section>`;
})() : '';

const html = `<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Сравнительный Lighthouse-аудит — IT Софт</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #F7F8FC; color: #10111B; font-family: Inter, "Segoe UI", Arial, sans-serif; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page { --page-bg:#F7F8FC; width: 210mm; height: 297mm; padding: 16mm 16mm 14mm; background: var(--page-bg); position: relative; overflow: hidden; break-inside: avoid; page-break-inside: avoid; break-after: page; page-break-after: always; }
  .page:last-child { break-after: auto; page-break-after: auto; }
  .page::after { content: attr(data-page); position: absolute; right: 16mm; bottom: 8mm; font-size: 8.5pt; color: #8D94A8; }
  .page:not(.cover)::after { display: none; }
  .page-header { position: relative; height: 10mm; display: grid; grid-template-columns: 1fr 1fr; align-items: start; margin: 0 0 5mm; }
  .page-header .brand { position: static; }
  .page-header > .eyebrow { position: static; text-align: right; padding-top: 2mm; }
  .page:not(.cover) .page-header { visibility: hidden; }
  .brand { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 10pt; }
  .brand-mark { width: 26px; height: 26px; border-radius: 9px; background: linear-gradient(135deg,#7C56FF,#5530F1); display: grid; place-items: center; color: white; font-weight: 900; }
  .eyebrow { color: #6C43FF; font-size: 8.5pt; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
  h1 { margin: 0; font-size: 30pt; line-height: 1.04; letter-spacing: -.035em; }
  h2 { margin: 0 0 4mm; font-size: 20pt; line-height: 1.12; letter-spacing: -.025em; }
  h3 { margin: 0 0 2.5mm; font-size: 12pt; }
  p { margin: 0 0 3mm; font-size: 9.6pt; line-height: 1.5; color: #555D72; }
  .muted { color: #7B8398; }
  .cover { --page-bg:#4A2BD8; background: linear-gradient(145deg,#161226 0%,#241A4D 55%,#5530F1 100%); color: white; padding: 55mm 20mm 20mm; }
  .cover::after { color: rgba(255,255,255,.56); }
  .cover .page-header { position: absolute; top: 20mm; left: 20mm; right: 20mm; margin: 0; }
  .cover .brand-mark { background: white; color: #6C43FF; }
  .cover h1 { max-width: 155mm; font-size: 38pt; }
  .cover .lead { margin-top: 7mm; max-width: 150mm; color: #D8D2EF; font-size: 13pt; line-height: 1.5; }
  .cover-meta { display: flex; gap: 4mm; margin-top: 10mm; }
  .cover-meta span { border: 1px solid rgba(255,255,255,.22); border-radius: 999px; padding: 2.6mm 4mm; font-size: 8.5pt; color: #EFEAFF; }
  .cover-grid { margin-top: 25mm; display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
  .cover-site { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.18); border-radius: 5mm; padding: 5mm; }
  .cover-site b { display: block; font-size: 12pt; margin-bottom: 1mm; }
  .cover-site span { font-size: 8.5pt; color: #CFC9E5; }
  .cover-note { position: absolute; left: 20mm; bottom: 17mm; right: 36mm; color: #CFC9E5; font-size: 8.5pt; line-height: 1.5; }
  .section-title { display: block; position: relative; min-height: 22mm; margin-bottom: 6mm; }
  .section-title > div { max-width: 100mm; }
  .section-title > p { position: absolute; right: 0; top: 0; width: 75mm; max-width: none; text-align: right; margin: 0; }
  .site-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
  .site-card { background: white; border: 1px solid #E1E4EE; border-radius: 5mm; padding: 5mm; box-shadow: 0 3mm 9mm rgba(31,35,54,.05); }
  .site-card-head { display: flex; gap: 2.5mm; align-items: center; }
  .site-card-head strong, .site-card-head small { display: block; }
  .site-card-head small { margin-top: .6mm; font-size: 7.5pt; color: #8990A4; }
  .site-dot { width: 4mm; height: 4mm; border-radius: 50%; background: var(--site); box-shadow: 0 0 0 2mm var(--accent); }
  .primary-score { margin: 6mm 0 4mm; display: flex; align-items: baseline; gap: 2mm; }
  .primary-score span { font-size: 31pt; line-height: .9; font-weight: 800; color: var(--site); }
  .primary-score small { color: #7F879B; font-size: 7.2pt; }
  .mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm; }
  .mini-grid div { background: #F7F8FC; border-radius: 2.5mm; padding: 2.5mm; }
  .mini-grid b, .mini-grid small { display: block; }
  .mini-grid b { font-size: 10pt; }
  .mini-grid small { margin-top: .5mm; color: #8A91A5; font-size: 6.8pt; }
  .insight-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 4mm; margin-top: 5mm; }
  .panel { background: white; border: 1px solid #E1E4EE; border-radius: 5mm; padding: 5mm; }
  .insight-list { display: grid; gap: 3mm; }
  .insight { display: grid; grid-template-columns: 8mm 1fr; gap: 3mm; }
  .insight-num { width: 7mm; height: 7mm; display: grid; place-items: center; border-radius: 2mm; background: #EFEAFF; color: #6C43FF; font-weight: 800; font-size: 8pt; }
  .insight b { display: block; font-size: 9.5pt; margin-bottom: .7mm; }
  .insight span { display: block; color: #687085; font-size: 8.3pt; line-height: 1.4; }
  .decision { background: linear-gradient(145deg,#6C43FF,#4E2CDA); color: white; border: 0; }
  .decision p { color: #E9E3FF; }
  .big-gap { font-size: 27pt; line-height: 1; font-weight: 800; margin: 6mm 0 2mm; }
  .badge { display: inline-block; padding: 1.5mm 2.5mm; border-radius: 999px; background: #EFEAFF; color: #6C43FF; font-weight: 750; font-size: 7.3pt; }
  .comparison-table, .data-table, .audit-table, .appendix-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border: 1px solid #E1E4EE; border-radius: 4mm; overflow: hidden; }
  table th, table td { text-align: left; vertical-align: middle; border-bottom: 1px solid #E9EBF2; }
  table tr:last-child th, table tr:last-child td { border-bottom: 0; }
  .comparison-table th, .comparison-table td { padding: 3.6mm; font-size: 8.2pt; }
  .comparison-table thead th { background: #F0F2F8; color: #626A7E; font-size: 7.4pt; text-transform: uppercase; letter-spacing: .04em; }
  .score-pill, .metric-pill { display: inline-grid; place-items: center; min-width: 10mm; height: 7mm; border-radius: 999px; font-weight: 800; font-size: 8pt; }
  .score-pill.good, .metric-pill.good { color: #08785B; background: #E3F8F0; }
  .score-pill.warn, .metric-pill.warn { color: #A35B00; background: #FFF1D9; }
  .score-pill.bad, .metric-pill.bad { color: #BE2947; background: #FFE4EA; }
  .cell-note { display: block; margin-top: 1.2mm; color: #81899D; font-size: 6.8pt; white-space: nowrap; }
  .data-table th, .data-table td { padding: 3mm 2.5mm; font-size: 7.8pt; }
  .data-table thead th { background: #F0F2F8; color: #626A7E; font-size: 7pt; }
  .brand-line { display: inline-block; width: 2.2mm; height: 5mm; border-radius: 999px; background: var(--brand); margin-right: 2mm; vertical-align: middle; }
  .subsection { margin-top: 6mm; }
  .device-label { display: flex; align-items: center; gap: 2mm; margin: 4mm 0 2.5mm; font-weight: 800; font-size: 9pt; }
  .device-label i { width: 2.2mm; height: 2.2mm; border-radius: 50%; background: #6C43FF; }
  .method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
  .method-card { background: white; border: 1px solid #E1E4EE; border-radius: 4mm; padding: 4.5mm; }
  .method-card b { display: block; font-size: 10pt; margin-bottom: 2mm; }
  .method-card p { font-size: 8.6pt; }
  .thresholds { display: grid; grid-template-columns: repeat(3,1fr); gap: 3mm; margin-top: 5mm; }
  .threshold { background: white; border-radius: 4mm; padding: 4mm; border: 1px solid #E1E4EE; }
  .threshold strong { display: block; font-size: 11pt; }
  .threshold span { display: block; font-size: 7.5pt; color: #747C90; margin-top: 1mm; }
  .resource-row { background: white; border: 1px solid #E1E4EE; border-radius: 4mm; padding: 4mm; margin-bottom: 3mm; }
  .resource-label { display: flex; justify-content: space-between; font-size: 9pt; margin-bottom: 2.5mm; }
  .resource-label span { color: #6F778C; }
  .stack { height: 6mm; display: flex; overflow: hidden; border-radius: 999px; background: #EDF0F5; }
  .stack span { min-width: .4mm; }
  .resource-details { display: flex; flex-wrap: wrap; gap: 2mm 4mm; margin-top: 2.5mm; }
  .resource-details span { font-size: 6.9pt; color: #6F778C; }
  .resource-details i { display: inline-block; width: 2mm; height: 2mm; border-radius: 50%; margin-right: 1mm; vertical-align: -0.2mm; }
  .callout { border-left: 1.2mm solid #6C43FF; padding: 3.5mm 4mm; background: #F0ECFF; border-radius: 0 3mm 3mm 0; margin-top: 4mm; }
  .callout b { display: block; font-size: 9pt; margin-bottom: 1mm; }
  .callout p { font-size: 8.2pt; margin: 0; }
  .audit-table th, .audit-table td { padding: 3.2mm; font-size: 7.7pt; vertical-align: top; }
  .audit-table th { width: 28mm; }
  .issue { margin-bottom: 2mm; }
  .issue:last-child { margin-bottom: 0; }
  .issue b, .issue span { display: block; }
  .issue b { font-size: 7.7pt; }
  .issue span { color: #868EA2; font-size: 6.7pt; margin-top: .4mm; }
  .ok-text { color: #08785B; font-weight: 700; }
  .priority-list { display: grid; gap: 3mm; }
  .priority { display: grid; grid-template-columns: 16mm 1fr 26mm; align-items: center; gap: 3mm; background: white; border: 1px solid #E1E4EE; border-radius: 4mm; padding: 4mm; }
  .priority-code { height: 10mm; display: grid; place-items: center; background: #1E1738; color: white; border-radius: 3mm; font-size: 8pt; font-weight: 800; }
  .priority b { display: block; font-size: 9pt; }
  .priority span { display: block; margin-top: .7mm; color: #727A8F; font-size: 7.4pt; line-height: 1.35; }
  .impact { text-align: right; font-size: 7pt; font-weight: 800; color: #6C43FF; }
  .appendix-table th, .appendix-table td { padding: 1.8mm 1.4mm; font-size: 6.2pt; }
  .appendix-table thead th { background: #EDEFF5; }
  .url-list { margin-top: 4mm; display: grid; gap: 2mm; }
  .url-item { background: white; border: 1px solid #E1E4EE; border-radius: 3mm; padding: 2.8mm; font-size: 6.7pt; color: #646C80; word-break: break-all; }
  .url-item b { color: #151722; display: block; margin-bottom: .7mm; font-size: 7.2pt; }
  .fineprint { font-size: 7.3pt; color: #858DA1; line-height: 1.5; }
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
  .kpi-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 3mm; margin: 5mm 0; }
  .kpi { background: white; border: 1px solid #E1E4EE; border-radius: 4mm; padding: 3.5mm; }
  .kpi strong { display: block; font-size: 15pt; }
  .kpi span { display: block; margin-top: 1mm; color: #81899C; font-size: 7pt; }
  .change-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; margin: 2mm 0 7mm; }
  .change-card { background: white; border: 1px solid #E1E4EE; border-radius: 4mm; padding: 4mm; }
  .change-card span, .change-card b, .change-card small { display: block; }
  .change-card span { color: #747C90; font-size: 7.2pt; min-height: 8mm; }
  .change-card b { font-size: 13pt; margin: 1.5mm 0; }
  .change-card small { color: #6C43FF; font-size: 7.5pt; font-weight: 800; }
  .change-note { display: block; margin-top: .6mm; color: #6C43FF; font-size: 6.7pt; font-weight: 800; }
</style>
</head>
<body>
  <section class="page cover" data-page="01">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> IT СОФТ</div><div class="eyebrow" style="color:#D8D2EF">Технический аудит</div></div>
    <div class="eyebrow" style="color:#BDAEFF;margin-bottom:5mm">Lighthouse · SEO · вес страниц</div>
    <h1>Сравнительный аудит производительности</h1>
    <p class="lead">IT Софт после обновления против Keysoft и Rusoft: одинаковые страницы, одинаковые профили мобильных и десктопных устройств, повторные лабораторные замеры.</p>
    <div class="cover-meta"><span>26 октября 2026</span><span>Lighthouse ${esc(reportData.methodology.lighthouseVersion)}</span><span>36 свежих запусков</span></div>
    <div class="cover-grid">${Object.keys(siteMeta).map((site) => `<div class="cover-site"><b>${siteMeta[site].name}</b><span>${siteMeta[site].domain}</span></div>`).join('')}</div>
    <div class="cover-note">IT Софт, Keysoft и Rusoft повторно измерены в одной серии запусков после обновления тестового стенда. Результаты являются лабораторным снимком и не заменяют полевые данные CrUX / Search Console.</div>
  </section>

  <section class="page" data-page="02">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Итоги</div></div>
    <div class="section-title"><div><div class="eyebrow">Коротко для принятия решения</div><h2>Главные выводы</h2></div><p>Средние значения по трём типам страниц и двум повторным запускам.</p></div>
    <div class="site-cards">${Object.keys(siteMeta).map(siteScoreCard).join('')}</div>
    <div class="insight-grid">
      <div class="panel"><h3>Что показывает сравнение</h3><div class="insight-list">
        <div class="insight"><div class="insight-num">1</div><div><b>Лидер mobile Performance — ${siteMeta[mobileRanking[0]].name}</b><span>${fmtScore(mobileLeader.mobilePerformance)} балла в среднем. IT Софт: ${fmtScore(itsoft.mobilePerformance)}; разница ${Math.abs(perfGap).toFixed(1)} пункта.</span></div></div>
        <div class="insight"><div class="insight-num">2</div><div><b>Самый лёгкий набор страниц — ${siteMeta[lightestRanking[0]].name}</b><span>Средний переданный объём ${fmtBytes(lightest.bytes)}. IT Софт: ${fmtBytes(itsoft.bytes)}.</span></div></div>
        <div class="insight"><div class="insight-num">3</div><div><b>SEO Lighthouse: отрыв IT Софт — ${seoGap.toFixed(0)} пунктов</b><span>IT Софт стабильно получает ${fmtScore(itsoft.seo)}, тогда как лучший результат в группе — ${fmtScore(siteSummary[seoRanking[0]].seo)}.</span></div></div>
        <div class="insight"><div class="insight-num">4</div><div><b>Desktop уже близок к сильному уровню</b><span>Средний Performance IT Софт на десктопе — ${fmtScore(itsoft.desktopPerformance)}. Основная зона роста — мобильный LCP и точечные проверки доступности.</span></div></div>
      </div></div>
      <div class="panel decision"><span class="badge" style="background:rgba(255,255,255,.16);color:white">Вывод</span><div class="big-gap">${fmtScore(itsoft.mobilePerformance)}</div><p>Средний mobile Performance IT Софт. Сайт не является «тяжёлым провалом»: он конкурентен по части страниц, но категория и товарная карточка теряют баллы на мобильном LCP.</p><p><b style="color:white">Приоритет:</b> LCP-изображения, критический CSS, кэширование статики и точечные замечания accessibility.</p></div>
    </div>
  </section>

  ${beforeAfterSection}

  <section class="page" data-page="03">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Методика</div></div>
    <div class="section-title"><div><div class="eyebrow">Как измеряли</div><h2>Условия и ограничения</h2></div><p>Одинаковая методика важнее абсолютного балла одного случайного запуска.</p></div>
    <div class="method-grid">
      <div class="method-card"><b>3 типа страниц</b><p>Главная, категория Microsoft Office, карточка Office 2021 Pro Plus. Это покрывает ключевой путь пользователя до корзины.</p></div>
      <div class="method-card"><b>2 устройства</b><p>Стандартный профиль Lighthouse Mobile и preset Desktop с симуляцией сети и процессора.</p></div>
      <div class="method-card"><b>2 повтора</b><p>Для каждой комбинации использована медиана двух запусков. Всего выполнено 36 свежих прогонов для трёх сайтов.</p></div>
      <div class="method-card"><b>Сопоставимый период</b><p>Контрольный и повторный замеры выполнены 26.10.2026 из одного окружения, Lighthouse ${esc(reportData.methodology.lighthouseVersion)}.</p></div>
    </div>
    <div class="subsection"><h3>Контрольные пороги Lighthouse</h3><div class="thresholds">
      <div class="threshold"><strong style="color:#08785B">LCP ≤ 2,5 с</strong><span>Хорошо · основной контент появляется быстро</span></div>
      <div class="threshold"><strong style="color:#08785B">TBT ≤ 200 мс</strong><span>Лабораторный индикатор отзывчивости JavaScript</span></div>
      <div class="threshold"><strong style="color:#08785B">CLS ≤ 0,10</strong><span>Стабильная раскладка без заметных сдвигов</span></div>
    </div></div>
    <div class="callout"><b>Важно о точности</b><p>Lighthouse — синтетический лабораторный тест. Баллы могут колебаться из-за нагрузки сервера, CDN, сторонних сервисов и сетевого маршрута. Для SEO-приёмки его нужно дополнять Search Console, Яндекс Вебмастером и реальными метриками пользователей.</p></div>
    <div class="subsection"><h3>Что входит в «вес»</h3><p>Transferred bytes из сетевого журнала Lighthouse: HTML, CSS, JavaScript, изображения, шрифты и прочие запросы. Это объём передачи, а не размер исходников на серверном диске.</p></div>
  </section>

  <section class="page" data-page="04">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Performance</div></div>
    <div class="section-title"><div><div class="eyebrow">Сводные баллы</div><h2>Производительность страниц</h2></div><p>В каждой ячейке: Performance, LCP и переданный объём.</p></div>
    <div class="device-label"><i></i> Mobile</div>${scoreTable('mobile')}
    <div class="device-label" style="margin-top:7mm"><i></i> Desktop</div>${scoreTable('desktop')}
    <div class="callout"><b>Как читать</b><p>90–100 — зелёная зона; 50–89 — требуется оптимизация; ниже 50 — критично. Для интернет-магазина мобильный результат важнее, поскольку органический и рекламный трафик часто приходит со смартфонов.</p></div>
  </section>

  ${Object.keys(pageMeta).map((page, index) => `<section class="page" data-page="${String(5 + index).padStart(2, '0')}">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">${pageMeta[page].short}</div></div>
    <div class="section-title"><div><div class="eyebrow">Подробное сравнение</div><h2>${pageMeta[page].name}</h2></div><p>Ключевые временные метрики, стабильность интерфейса, вес и число запросов.</p></div>
    <div class="device-label"><i></i> Mobile</div>${detailedTable(page, 'mobile')}
    <div class="device-label" style="margin-top:7mm"><i></i> Desktop</div>${detailedTable(page, 'desktop')}
    <div class="callout"><b>Фокус для IT Софт</b><p>${page === 'home' ? 'Главная страница — самый заметный мобильный узкий участок. Оптимизировать LCP-элемент, приоритет загрузки hero-изображения и критический CSS.' : page === 'category' ? 'Категория держится конкурентно. Важно не ухудшить её при добавлении новых карточек: ленивые изображения, фиксированные размеры медиа и ограничение сторонних скриптов.' : 'Карточка товара на десктопе сильная, но мобильный LCP требует сокращения блокирующих ресурсов и более ранней загрузки изображения товара.'}</p></div>
  </section>`).join('')}

  <section class="page" data-page="08">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Вес</div></div>
    <div class="section-title"><div><div class="eyebrow">Переданные ресурсы</div><h2>Из чего состоят страницы</h2></div><p>Средний объём по трём страницам. Полоса показывает долю каждого типа ресурса.</p></div>
    <h3>Mobile</h3>${Object.keys(siteMeta).map((site) => resourceStack(site, 'mobile')).join('')}
    <h3 style="margin-top:6mm">Desktop</h3>${Object.keys(siteMeta).map((site) => resourceStack(site, 'desktop')).join('')}
    <div class="callout"><b>Практический смысл</b><p>Вес изображений напрямую влияет на LCP и расходы мобильного трафика. JavaScript влияет не только на объём, но и на время разбора и выполнение в основном потоке. Поэтому одинаковый общий вес может давать разные Performance-баллы.</p></div>
  </section>

  <section class="page" data-page="09">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">SEO</div></div>
    <div class="section-title"><div><div class="eyebrow">Технические проверки</div><h2>Lighthouse SEO</h2></div><p>Проваленные проверки объединены по трём страницам мобильного профиля.</p></div>
    <div class="kpi-row">${Object.keys(siteMeta).map((site) => `<div class="kpi"><strong>${fmtScore(siteSummary[site].seo)}</strong><span>${siteMeta[site].name} · средний SEO</span></div>`).join('')}<div class="kpi"><strong>${seoGap.toFixed(0)}</strong><span>отрыв IT Софт от лидера</span></div></div>
    <table class="audit-table"><tbody>${auditFailureRows('seoFailures')}</tbody></table>
    <div class="callout"><b>Что не измеряет Lighthouse SEO</b><p>Семантику запросов, позиции, качество контента, индексацию, ссылочный профиль, коммерческие факторы и санкции поисковиков. Для полного SEO-отчёта отдельно нужны Search Console, Яндекс Вебмастер и выгрузка сканера сайта.</p></div>
  </section>

  <section class="page" data-page="10">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Качество</div></div>
    <div class="section-title"><div><div class="eyebrow">Доступность и Best Practices</div><h2>Качество интерфейса</h2></div><p>Технические проверки помогают найти ошибки, влияющие на пользователей и устойчивость.</p></div>
    <h3>Accessibility — проваленные проверки</h3>
    <table class="audit-table"><tbody>${auditFailureRows('accessibilityFailures')}</tbody></table>
    <h3 style="margin-top:6mm">Best Practices — проваленные проверки</h3>
    <table class="audit-table"><tbody>${auditFailureRows('bestPracticeFailures')}</tbody></table>
  </section>

  <section class="page" data-page="11">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Экономия</div></div>
    <div class="section-title"><div><div class="eyebrow">Оценка Lighthouse</div><h2>Потенциальная экономия IT Софт</h2></div><p>Оценочные байты, которые можно сократить. Значения не суммируются буквально: рекомендации могут пересекаться.</p></div>
    ${opportunityTable('itsoft')}
    <div class="two-col" style="margin-top:6mm">
      <div class="panel"><h3>Быстрые победы</h3><p>Сконвертировать тяжёлые изображения, отложить невидимые медиа, удалить неиспользуемые CSS/JS, увеличить TTL статических файлов и сократить сторонние скрипты.</p></div>
      <div class="panel"><h3>Контроль после релиза</h3><p>Повторить тот же набор URL минимум три раза, сравнивать медиану и убедиться, что исправления не ухудшили CLS, корзину, поиск и мобильное меню.</p></div>
    </div>
    <div class="callout"><b>Почему экономия может быть 0 КБ</b><p>Это означает, что конкретный Lighthouse-аудит не нашёл прямой байтовой экономии, а не отсутствие проблем. Например, медленный сервер или поздний LCP-элемент ухудшают скорость без большого лишнего веса.</p></div>
  </section>

  <section class="page" data-page="12">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">План</div></div>
    <div class="section-title"><div><div class="eyebrow">Приоритеты IT Софт</div><h2>Что делать дальше</h2></div><p>Порядок сформирован по влиянию на мобильный Performance, доступность и риску внедрения.</p></div>
    <div class="priority-list">
      <div class="priority"><div class="priority-code">P0</div><div><b>Сократить мобильный LCP карточки товара</b><span>Сейчас медиана — 6,4 с. Нужны ранняя загрузка главного изображения, корректный fetchpriority и сокращение блокирующих ресурсов первого экрана.</span></div><div class="impact">Высокий эффект<br>средний риск</div></div>
      <div class="priority"><div class="priority-code">P0</div><div><b>Ускорить категорию и главную на мобильном</b><span>Проверить LCP-элементы, адаптивные размеры изображений и исключить lazy-load для первого видимого изображения.</span></div><div class="impact">Высокий эффект<br>средний риск</div></div>
      <div class="priority"><div class="priority-code">P1</div><div><b>Оптимизировать изображения и кэш статики</b><span>Lighthouse оценивает заметную экономию на изображениях и TTL. Сохранить WebP, подобрать реальные размеры и проверить заголовки Cache-Control.</span></div><div class="impact">Высокий эффект<br>низкий риск</div></div>
      <div class="priority"><div class="priority-code">P1</div><div><b>Довести accessibility до 95+</b><span>Контраст, accessible names, подписи форм, порядок заголовков и фокус клавиатуры.</span></div><div class="impact">Средний эффект<br>низкий риск</div></div>
      <div class="priority"><div class="priority-code">P2</div><div><b>Настроить постоянный контроль</b><span>CI-аудит ключевых URL, бюджеты: mobile Performance ≥ 80, SEO ≥ 98, LCP ≤ 3,5 с на тестовом сервере.</span></div><div class="impact">Защита качества<br>низкий риск</div></div>
    </div>
    <div class="callout"><b>Критерий готовности к показу заказчику</b><p>Главная, категория и карточка товара проходят одинаковый повторный прогон; нет регрессий функциональности; в отчёте отдельно подписано, что тестовый сервер и синтетический Lighthouse не равны производительности будущего продакшена.</p></div>
  </section>

  <section class="page" data-page="13">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Приложение</div></div>
    <div class="section-title"><div><div class="eyebrow">Полные данные</div><h2>Mobile — все показатели</h2></div><p>Медиана двух запусков каждой страницы.</p></div>
    <table class="appendix-table"><thead><tr><th>Страница</th><th>Сайт</th><th>Perf.</th><th>A11y</th><th>BP</th><th>SEO</th><th>FCP</th><th>LCP</th><th>TBT</th><th>CLS</th><th>Вес</th><th>Req.</th></tr></thead><tbody>${fullAppendixRows('mobile')}</tbody></table>
    <div class="section-title" style="margin-top:8mm"><div><div class="eyebrow">Полные данные</div><h2>Desktop — все показатели</h2></div></div>
    <table class="appendix-table"><thead><tr><th>Страница</th><th>Сайт</th><th>Perf.</th><th>A11y</th><th>BP</th><th>SEO</th><th>FCP</th><th>LCP</th><th>TBT</th><th>CLS</th><th>Вес</th><th>Req.</th></tr></thead><tbody>${fullAppendixRows('desktop')}</tbody></table>
  </section>

  <section class="page" data-page="14">
    <div class="page-header"><div class="brand"><span class="brand-mark">IT</span> Сравнительный аудит</div><div class="eyebrow">Источники</div></div>
    <div class="section-title"><div><div class="eyebrow">Проверенные адреса</div><h2>URL и воспроизводимость</h2></div><p>Все адреса были доступны на момент запуска аудита.</p></div>
    <div class="url-list">${Object.keys(siteMeta).flatMap((site) => Object.keys(pageMeta).map((page) => `<div class="url-item"><b>${siteMeta[site].name} · ${pageMeta[page].name}</b>${esc(urls[site][page])}</div>`)).join('')}</div>
    <div class="subsection panel"><h3>Как повторить проверку</h3><p>Chrome DevTools → Lighthouse → выбрать Mobile или Desktop → категории Performance, Accessibility, Best Practices и SEO → Analyze page load. Для сравнения использовать одинаковые URL, чистый профиль браузера и несколько повторов.</p><p class="fineprint">CLI-профиль отчёта: Lighthouse ${esc(reportData.methodology.lighthouseVersion)}, throttling-method=simulate, стандартный mobile profile и preset=desktop. Сырые JSON сохранены в рабочем каталоге проекта для повторного анализа.</p></div>
    <div class="subsection panel"><h3>Источники методики</h3><p class="fineprint">Google Chrome for Developers — Lighthouse performance scoring и Core Web Vitals; проверяемые сайты — test.itsoft.store, keysoft.store и rusoft.shop. Дата замеров: 26.10.2026, часовой пояс Europe/Moscow.</p></div>
  </section>
</body>
</html>`;

fs.writeFileSync(htmlPath, html, 'utf8');

const fileUrl = `file:///${htmlPath.replace(/\\/g, '/')}`;
execFileSync(chromePath, [
  '--headless=new',
  '--disable-gpu',
  '--no-sandbox',
  '--allow-file-access-from-files',
  '--disable-extensions',
  '--run-all-compositor-stages-before-draw',
  `--print-to-pdf=${pdfPath}`,
  '--print-to-pdf-no-header',
  fileUrl,
], { stdio: 'inherit' });

if (!fs.existsSync(pdfPath) || fs.statSync(pdfPath).size < 50_000) {
  throw new Error(`PDF was not created correctly: ${pdfPath}`);
}

console.log(JSON.stringify({ htmlPath, dataPath, pdfPath, bytes: fs.statSync(pdfPath).size }, null, 2));
