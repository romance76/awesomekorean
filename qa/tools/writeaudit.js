// For every public write-ish controller method, report whether it validates input, filters bad words / cleans HTML, checks ownership.
const fs = require('fs'), path = require('path');
const dir = (process.env.AK_ROOT || process.cwd()) + '/app/Http/Controllers/API';
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.php') && !/^Admin/.test(f));
const rows = [];
for (const f of files) {
  const s = fs.readFileSync(path.join(dir, f), 'utf8');
  const re = /public function (\w+)\s*\(([^)]*)\)\s*(?::\s*[\w\\|?]+)?\s*\{/g;
  const starts = [...s.matchAll(re)];
  starts.forEach((m, i) => {
    const name = m[1], params = m[2];
    if (!/Request/.test(params)) return;
    if (!/^(store|update|send|post|create|submit|reply|answer|comment|add|edit|upload|apply|review|report|save|set|change|toggle|accept|claim|request)/i.test(name)) return;
    const body = s.slice(m.index, starts[i + 1] ? starts[i + 1].index : s.length);
    const validates = /->validate\(|Validator::make|\$request->validateWithBag|Rule::|validateRequest/.test(body);
    const writes = /::create\(|->create\(|->update\(|->save\(|->fill\(|forceFill|->insert\(|updateOrCreate|->delete\(|->increment\(|addPoints/.test(body);
    if (!writes) return;
    const usesRequestData = /\$request->(only|input|get|\w+)\b|\$request->all/.test(body);
    rows.push({
      file: f.replace('Controller.php', ''), name, validates,
      badWords: /BadWordFilter/.test(body), clean: /PlainText::clean|strip_tags|e\(|htmlspecialchars/.test(body),
      owner: /findOwned|user_id\s*(!==|!=|===|==)\s*auth|auth\(\)->id\(\)\s*(!==|!=)|authorize|Gate::|->where\('user_id',\s*auth|abort\(403|403\)/.test(body),
      throttledRoute: null, lines: body.split('\n').length, usesRequestData,
    });
  });
}
const noVal = rows.filter((r) => !r.validates && r.usesRequestData);
console.log('write-ish public methods (non-admin):', rows.length, '| without any validate():', noVal.length);
for (const r of noVal) console.log('  NO-VALIDATE', (r.file + '::' + r.name).padEnd(44), 'owner-check:' + (r.owner ? 'y' : 'n'), 'badwords:' + (r.badWords ? 'y' : 'n'), 'lines:' + r.lines);
console.log('\nmethods that store free text but never run the bad-word filter or HTML cleaning (validated ones included):');
const text = rows.filter((r) => /store|update|send|post|comment|answer|reply|review|report/i.test(r.name) && !r.badWords && !r.clean);
for (const r of text) console.log('  ', (r.file + '::' + r.name).padEnd(44), 'validates:' + (r.validates ? 'y' : 'n'));
