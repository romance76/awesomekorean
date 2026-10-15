const fs = require('fs');
const sfc = require('@vue/compiler-sfc');
let bad = 0;
for (const f of process.argv.slice(2)) {
  const src = fs.readFileSync(f, 'utf8');
  const { descriptor, errors } = sfc.parse(src, { filename: f });
  let errs = [...errors];
  if (descriptor.template) {
    const r = sfc.compileTemplate({ source: descriptor.template.content, filename: f, id: 'x' });
    errs = errs.concat(r.errors);
  }
  if (descriptor.scriptSetup || descriptor.script) {
    try { sfc.compileScript(descriptor, { id: 'x' }); } catch (e) { errs.push(e); }
  }
  if (errs.length) { bad++; console.log('FAIL', f, errs.map(e => e.message || String(e)).join(' | ')); } else console.log('ok  ', f.split('/').slice(-2).join('/'));
}
process.exit(bad ? 1 : 0);
