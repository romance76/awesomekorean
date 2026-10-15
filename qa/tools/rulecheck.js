// Check that every Laravel validation rule string added in the working tree uses a real rule name with sane parameters.
const { execSync } = require('child_process');
const ROOT = process.env.AK_ROOT || process.cwd();
const KNOWN = new Set(['required', 'nullable', 'sometimes', 'string', 'max', 'min', 'numeric', 'integer', 'email', 'date', 'in', 'boolean', 'array', 'image', 'mimes', 'between', 'exists', 'unique', 'regex', 'confirmed', 'url', 'size', 'digits', 'file', 'present', 'filled', 'accepted', 'alpha_dash', 'not_in', 'same', 'different', 'after', 'before', 'date_format', 'json', 'ip']);
const diff = execSync('git diff -U0', { cwd: ROOT, encoding: 'utf8', maxBuffer: 1e8 });
let n = 0, bad = 0;
for (const line of diff.split('\n')) {
  if (!line.startsWith('+') || line.startsWith('+++')) continue;
  const m = line.match(/'([a-z_.*]+)'\s*=>\s*'([^']+)'/);
  if (!m || !/\b(required|nullable|sometimes|numeric|integer|string)\b/.test(m[2])) continue;
  n++;
  for (const rule of m[2].split('|')) {
    const [name, params] = rule.split(':');
    const ok = KNOWN.has(name) && (['max', 'min', 'size', 'digits'].includes(name) ? /^\d+$/.test(params || '') : true) && (name !== 'in' || /^[a-z0-9_,]+$/i.test(params || ''));
    if (!ok) { bad++; console.log('SUSPICIOUS', m[1], '=>', rule); }
  }
}
console.log('rule strings checked:', n, '| suspicious tokens:', bad);
process.exit(bad ? 1 : 0);
