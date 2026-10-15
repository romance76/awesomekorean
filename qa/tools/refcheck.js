// Static reference check for PHP files (catches problems that `php -l` cannot, e.g. a mangled class name like AppHttpControllersFoo):
//  1. every \App\... class referenced by full name must exist as a file
//  2. static calls on our own classes must hit a defined method
//  3. route actions [\App\...\Controller::class, 'method'] must point to a real class + method
//  4. bare class names used as Name:: / Name::class must be imported, declared in the file, or live in the same namespace
// usage: node refcheck.js [extra files...]   (checks git-changed PHP files + extras)
//        node refcheck.js --selftest          (feeds known-bad snippets and expects them to be caught)
const fs = require('fs'), path = require('path'), { execSync } = require('child_process');
const ROOT = process.env.AK_ROOT || process.cwd();
const read = (f) => fs.readFileSync(path.join(ROOT, f), 'utf8');
const exists = (rel) => fs.existsSync(path.join(ROOT, rel));
const classFile = (fq) => 'app/' + fq.replace(/^App\\/, '').replace(/\\/g, '/') + '.php';
const hasMethod = (rel, m) => new RegExp('function\\s+' + m + '\\s*\\(').test(read(rel));
const BUILTIN = new Set(['self', 'static', 'parent', 'Exception', 'Throwable', 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'Closure', 'ParseError', 'PDO', 'JsonException',
  'InvalidArgumentException', 'RuntimeException', 'ReflectionClass', 'Carbon', 'Collection', 'Arr', 'Str']);
const FACADE_ALIASES = /^(Route|DB|Schema|Cache|Artisan|Schedule|Broadcast|Log|Storage|Hash|Auth|Mail|Http|Queue|Gate|Event|RateLimiter|Notification|Password|Validator|URL|File|Session|Redis|View|Blueprint)$/;

function checkSource(f, s) {
  const out = [];
  const bad = (msg) => out.push(msg);
  // 1) full names
  for (const m of s.matchAll(/\\?\bApp\\([A-Za-z0-9_\\]+)/g)) {
    const fq = 'App\\' + m[1].replace(/\\$/, '');
    if (new RegExp('namespace\\s+' + fq.replace(/\\/g, '\\\\') + '\\s*;').test(s)) continue;
    if (!exists(classFile(fq)) && !fs.existsSync(path.join(ROOT, 'app/' + fq.replace(/^App\\/, '').replace(/\\/g, '/')))) bad('class file not found for ' + fq);
  }
  // 2) static calls on full-name classes
  for (const m of s.matchAll(/(\\?App(?:\\[A-Za-z0-9_]+)+)::(\w+)\s*\(/g)) {
    const fq = m[1].replace(/^\\/, '');
    if (exists(classFile(fq)) && !hasMethod(classFile(fq), m[2])) bad(fq + '::' + m[2] + '() is not defined');
  }
  // 3) route actions
  if (f === 'routes/api.php') {
    for (const m of s.matchAll(/\[\s*\\(App\\[A-Za-z0-9_\\]+)::class\s*,\s*'(\w+)'\s*\]/g)) {
      const rel = classFile(m[1]);
      if (!exists(rel)) bad('route controller missing: ' + m[1]);
      else if (!hasMethod(rel, m[2])) bad('route action missing: ' + m[1] + '@' + m[2]);
    }
  }
  // 4) bare class names
  const ns = (s.match(/^namespace\s+([^;{\s]+)/m) || [])[1] || '';
  const uses = new Set();
  for (const m of s.matchAll(/^use\s+([^;]+);/gm)) {
    for (const part of m[1].split(',')) {
      const p = part.trim(), as = p.match(/\s+as\s+(\w+)$/i);
      uses.add(as ? as[1] : p.split('\\').pop().replace(/\{.*/, ''));
    }
  }
  const sameNsDir = ns ? 'app/' + ns.replace(/^App\\?/, '').replace(/\\/g, '/') : '';
  const seen = new Set();
  for (const m of s.matchAll(/(?<![\\\w$>])([A-Za-z][A-Za-z0-9_]*)::(?:class\b|\w+)/g)) {
    const n = m[1];
    if (seen.has(n) || BUILTIN.has(n) || uses.has(n)) continue;
    seen.add(n);
    if (new RegExp('(class|trait|interface|enum)\\s+' + n + '\\b').test(s)) continue;
    if (sameNsDir && exists(sameNsDir + '/' + n + '.php')) continue;
    if (/^(routes|database)\//.test(f) && FACADE_ALIASES.test(n)) continue;
    bad('unresolved class name "' + n + '" (not imported / not in same namespace)');
  }
  // 5) mangled names (a backslash got lost somewhere)
  for (const m of s.matchAll(/\b(AppHttp|AppSupport|AppModels|AppConsole|AppServices|IlluminateSupport|IlluminateHttp|IlluminateConsole|SymfonyComponent)[A-Za-z]*/g)) bad('mangled class name: ' + m[0]);
  return out;
}

if (process.argv.includes('--selftest')) {
  const cases = [
    ['routes/api.php', "Route::post('/x', [AppHttpControllersAPICspReportController::class, 'store']);", 'mangled'],
    ['app/Http/Controllers/API/AuthController.php', 'if ($tf = AppSupportTwoFactor::challenge($u, $c)) {}', 'mangled'],
    ['app/Http/Controllers/API/AuthController.php', "namespace App\\Http\\Controllers\\API;\nfoo(TotallyMissing::bar());", 'unresolved'],
    ['routes/api.php', "Route::post('/x', [\\App\\Http\\Controllers\\API\\TwoFactorController::class, 'nope']);", 'route action missing'],
    ['routes/api.php', "Route::post('/x', [\\App\\Http\\Controllers\\API\\NoSuchController::class, 'store']);", 'route controller missing'],
    ['app/X.php', '\\App\\Support\\TwoFactor::doesNotExist($u);', 'is not defined'],
  ];
  let ok = true;
  for (const [f, src, expect] of cases) {
    const r = checkSource(f, src), hit = r.some((x) => x.includes(expect));
    console.log(hit ? 'caught  ' : 'MISSED  ', expect.padEnd(22), '<-', src.slice(0, 70).replace(/\n/g, ' '));
    if (!hit) ok = false;
  }
  // and a clean snippet must produce nothing
  const clean = checkSource('app/Http/Controllers/API/AuthController.php', "namespace App\\Http\\Controllers\\API;\nuse App\\Support\\TwoFactor;\n$x = TwoFactor::enabled($u); $y = \\App\\Support\\Totp::step();");
  console.log(clean.length === 0 ? 'clean snippet: no false alarm' : 'FALSE ALARM: ' + clean.join('; '));
  process.exit(ok && clean.length === 0 ? 0 : 1);
}

const changed = execSync('git status --porcelain', { cwd: ROOT, encoding: 'utf8' }).split('\n').map((l) => l.slice(3).trim()).filter((f) => f.endsWith('.php'));
const files = changed.concat(process.argv.slice(2)).filter((f, i, a) => a.indexOf(f) === i);
let problems = 0;
for (const f of files) for (const msg of checkSource(f, read(f))) { problems++; console.log('PROBLEM', f, '-', msg); }
console.log('checked', files.length, 'files');
console.log(problems ? problems + ' problem(s)' : 'no problems found');
process.exit(problems ? 1 : 0);
