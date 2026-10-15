// Extract every first path segment the SPA router can serve, from resources/js/router/index.js
const fs = require('fs');
const src = fs.readFileSync((process.env.AK_ROOT || process.cwd()) + '/resources/js/router/index.js', 'utf8');
const segs = new Set();
// absolute paths: path: '/xxx...' (also redirect targets are ignored)
for (const m of src.matchAll(/\bpath:\s*'(\/[^']*)'/g)) {
  const p = m[1];
  if (p.startsWith('/:')) continue;               // catch-all / param at top level
  const first = p.split('/').filter(Boolean)[0];
  if (first && !first.startsWith(':')) segs.add(first);
}
// redirect-only legacy paths: { path: '/old', redirect: ... } are matched by the same regex above
const list = [...segs].sort();
console.log(list.length, 'segments');
console.log(JSON.stringify(list));
// sanity: which sections do we already treat specially?
const known = ['community', 'qa', 'jobs', 'market', 'realestate', 'directory', 'news', 'recipes', 'events', 'clubs', 'groupbuy', 'money', 'shopping', 'about', 'contact', 'terms', 'privacy', 'info', 'shorts', 'music', 'login', 'register', 'search', 'admin', 'games', 'elder', 'poker7', 'stocks', 'sweepstakes', 'chat', 'new'];
console.log('router lacks (expected from sitemap/SEO lists):', known.filter((k) => !segs.has(k)));
// how are children declared under admin etc. (relative paths) — only first segment matters, parents are absolute
console.log('relative-only top-level parents?', [...src.matchAll(/\n\s{2}\{\s*path:\s*'([a-z0-9-]+)'/g)].map((m) => m[1]).slice(0, 10));
