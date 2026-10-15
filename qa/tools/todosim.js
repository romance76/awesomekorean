// Rebuild the admin_todos table state by replaying the repo's todo migrations (approximation of production)
const fs = require('fs'), path = require('path');
const dir = (process.env.AK_ROOT || process.cwd()) + '/database/migrations';
const files = fs.readdirSync(dir).filter((f) => fs.readFileSync(path.join(dir, f), 'utf8').includes('admin_todos')).sort();
const Q = "'((?:[^'\\\\]|\\\\.)*)'";
const addRe = new RegExp('^\\s*\\[\\s*' + Q + ",\\s*'(high|medium|low)',\\s*'(todo|doing|done)',\\s*" + Q, 'gm');
const markRe = new RegExp('^\\s*\\[\\s*' + Q + ",\\s*'(done|doing|todo)'", 'gm');
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const like = (pat) => { const r = new RegExp('^' + esc(pat).replace(/%/g, '.*') + '$', 'i'); return (t) => r.test(t); };
let items = [], seq = 0;
const log = [];
for (const f of files) {
  const s = fs.readFileSync(path.join(dir, f), 'utf8');
  for (const m of s.matchAll(/whereIn\('title',\s*\[([\s\S]*?)\]\)\s*->delete\(\)/g)) {
    const ts = [...m[1].matchAll(new RegExp(Q, 'g'))].map((x) => x[1]);
    const before = items.length; items = items.filter((i) => !ts.includes(i.title)); log.push(`${f}: delete ${before - items.length}/${ts.length}`);
  }
  let added = 0;
  for (const m of s.matchAll(addRe)) { const [, cat, pri, st, title] = m; if (items.some((i) => i.title === title)) continue; items.push({ cat, pri, st, title, seq: ++seq, detail: s.slice(m.index + m[0].length, m.index + m[0].length + 420).replace(/s+/g,' ') }); added++; }
  let marked = 0;
  for (const m of s.matchAll(markRe)) {
    const [, pat, st] = m, f2 = like(pat);
    for (const i of items) if (f2(i.title)) {
      if (st === 'done' && i.st !== 'done') { i.st = 'done'; marked++; }
      else if (st === 'doing' && i.st === 'todo') { i.st = 'doing'; marked++; }
    }
  }
  if (/mark_.*done/.test(f)) {
    let m2 = 0;
    for (const mm of s.matchAll(new RegExp(Q, 'g'))) {
      const pat = mm[1];
      if (pat.length < 4 || /3~9단계/.test(pat)) continue;
      const f2 = like(pat);
      for (const i of items) if (i.st !== 'done' && f2(i.title)) { i.st = 'done'; m2++; }
    }
    marked += m2;
  }
  log.push(`${f}: +${added} marks:${marked}`);
}
const open = items.filter((i) => i.st !== 'done'), done = items.filter((i) => i.st === 'done');
console.log('files', files.length, '| total', items.length, '| open', open.length, '| done', done.length);
fs.writeFileSync(path.join(__dirname, 'todosim.json'), JSON.stringify(items, null, 1));
console.log(log.join('\n'));
