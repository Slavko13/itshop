import fs from 'node:fs';

const [beforePath, afterPath, outputPath] = process.argv.slice(2);
if (!beforePath || !afterPath) {
  throw new Error('Usage: node compare-lighthouse-snapshots.mjs <before.json> <after.json> [output.json]');
}

const before = JSON.parse(fs.readFileSync(beforePath, 'utf8')).results.itsoft;
const after = JSON.parse(fs.readFileSync(afterPath, 'utf8')).results.itsoft;
const pages = ['home', 'category', 'product'];
const profiles = ['mobile', 'desktop'];
const metrics = ['performance', 'accessibility', 'bestPractices', 'seo', 'fcp', 'lcp', 'tbt', 'cls', 'totalBytes', 'requests'];
const round = (value, digits = 2) => Number(value.toFixed(digits));

const details = pages.flatMap((page) => profiles.map((profile) => {
  const oldValue = before[page][profile];
  const newValue = after[page][profile];
  const values = Object.fromEntries(metrics.map((metric) => [metric, {
    before: round(oldValue[metric], metric === 'cls' ? 4 : 2),
    after: round(newValue[metric], metric === 'cls' ? 4 : 2),
    delta: round(newValue[metric] - oldValue[metric], metric === 'cls' ? 4 : 2),
  }]));
  return { page, profile, ...values };
}));

const average = (snapshot, profile, metric) => pages.reduce((sum, page) => sum + snapshot[page][profile][metric], 0) / pages.length;
const averageAll = (snapshot, metric) => pages.flatMap((page) => profiles.map((profile) => snapshot[page][profile][metric])).reduce((a, b) => a + b, 0) / 6;
const summary = {
  mobilePerformance: { before: round(average(before, 'mobile', 'performance')), after: round(average(after, 'mobile', 'performance')) },
  desktopPerformance: { before: round(average(before, 'desktop', 'performance')), after: round(average(after, 'desktop', 'performance')) },
  mobileLcpMs: { before: round(average(before, 'mobile', 'lcp')), after: round(average(after, 'mobile', 'lcp')) },
  desktopLcpMs: { before: round(average(before, 'desktop', 'lcp')), after: round(average(after, 'desktop', 'lcp')) },
  averageBytes: { before: round(averageAll(before, 'totalBytes')), after: round(averageAll(after, 'totalBytes')) },
  averageSeo: { before: round(averageAll(before, 'seo')), after: round(averageAll(after, 'seo')) },
  averageAccessibility: { before: round(averageAll(before, 'accessibility')), after: round(averageAll(after, 'accessibility')) },
};

for (const value of Object.values(summary)) value.delta = round(value.after - value.before);
const result = { summary, details };
if (outputPath) fs.writeFileSync(outputPath, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
