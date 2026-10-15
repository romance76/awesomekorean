// Tiny PHP harness on WASM: `node php.js lint <files...>` and `node php.js run <file.php>`
const fs = require('fs');
const { PHP } = require('@php-wasm/universal');
const { loadNodeRuntime } = require('@php-wasm/node');

async function boot() {
  const php = new PHP(await loadNodeRuntime('8.2', { emscriptenOptions: { processId: 1 } }));
  return php;
}
(async () => {
  const [cmd, ...args] = process.argv.slice(2);
  const php = await boot();
  if (cmd === 'version') {
    const r = await php.run({ code: '<?php echo PHP_VERSION, " openssl=", extension_loaded("openssl") ? "yes" : "no", " hash=", extension_loaded("hash") ? "yes" : "no";' });
    console.log(r.text);
  } else if (cmd === 'lint') {
    let bad = 0;
    for (const f of args) {
      const src = fs.readFileSync(f, 'utf8');
      php.writeFile('/tmp/lint_target.php', src);
      // token_get_all with TOKEN_PARSE throws ParseError on syntax errors without executing the file
      const r = await php.run({ code: '<?php try { token_get_all(file_get_contents("/tmp/lint_target.php"), TOKEN_PARSE); echo "OK"; } catch (ParseError $e) { echo "ERR line " . $e->getLine() . ": " . $e->getMessage(); }' });
      const t = r.text.trim();
      if (t !== 'OK') { bad++; console.log('FAIL', f, '-', t); } else console.log('ok  ', f);
    }
    process.exit(bad ? 1 : 0);
  } else if (cmd === 'run') {
    for (const f of args) php.writeFile('/tmp/' + require('path').basename(f), fs.readFileSync(f, 'utf8'));
    php.writeFile('/tmp/run_target.php', fs.readFileSync(args[0], 'utf8'));
    const r = await php.run({ scriptPath: '/tmp/run_target.php' });
    console.log(r.text);
    if (r.errors) console.log('STDERR:', r.errors);
  }
  process.exit(0);
})().catch((e) => { console.error('harness error:', e.message); process.exit(2); });
