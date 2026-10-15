<?php
namespace Illuminate\Console { class Command { const SUCCESS = 0; const FAILURE = 1; } }
namespace Illuminate\Support\Facades { class Log { static function __callStatic($a, $b) {} } }
namespace Symfony\Component\Process { class Process {} }
namespace {
require '/tmp/DbBackup.php';
use App\Console\Commands\DbBackup;
$ok = 0; $bad = 0;
function check($name, $cond) { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name\n"; } }

// 1) dump command
$cfg = ['host' => '127.0.0.1', 'port' => 3306, 'username' => 'u', 'database' => 'awesomekorean', 'password' => "p'a\$\"ss"];
$s = DbBackup::dumpScript($cfg, '/var/www/x/backups/db_1.sql.gz.part');
check('uses mysqldump with single-transaction', str_contains($s, 'mysqldump --single-transaction'));
check('password is NOT on the command line', !str_contains($s, 'a$"ss') && !str_contains($s, "p'a") && !str_contains($s, 'MYSQL_PWD'));
check('has host/port/user/db', str_contains($s, "-h '127.0.0.1'") && str_contains($s, "-P '3306'") && str_contains($s, "-u 'u'") && str_contains($s, "'awesomekorean'"));
check('pipes to gzip into the output file', str_contains($s, "| nice -n 19 gzip -c > '/var/www/x/backups/db_1.sql.gz.part'"));
check('full variant has routines/triggers/events', str_contains($s, '--routines --triggers --events'));
$min = DbBackup::dumpScript($cfg, '/tmp/a', false);
check('minimal variant drops routines/events/triggers', str_contains($min, '--skip-triggers') && !str_contains($min, '--routines') && !str_contains($min, '--events'));
$cfg2 = ['unix_socket' => '/var/run/mysqld/mysqld.sock', 'username' => 'u', 'database' => 'd'];
$s2 = DbBackup::dumpScript($cfg2, '/tmp/a');
check('socket variant', str_contains($s2, "--socket='/var/run/mysqld/mysqld.sock'") && !str_contains($s2, ' -h '));
$evil = DbBackup::dumpScript(['host' => 'h; rm -rf /', 'username' => 'u`id`', 'database' => 'd$(id)'], '/tmp/a b');
check('shell metacharacters are quoted', str_contains($evil, "'h; rm -rf /'") && str_contains($evil, "'u`id`'") && str_contains($evil, "'d\$(id)'") && str_contains($evil, "'/tmp/a b'"));

// 2) retention
@mkdir('/tmp/bk');
$now = time();
$mk = function ($name, $ageDays) use ($now) { $f = "/tmp/bk/$name"; file_put_contents($f, 'x'); touch($f, $now - $ageDays * 86400); return $f; };
$files = [
  $mk('db_20260101_035000.sql.gz', 40), $mk('db_20260105_035000.sql.gz', 36), $mk('db_20260201_035000.sql.gz', 20),
  $mk('db_20260301_035000.sql.gz', 10), $mk('db_20260401_035000.sql.gz', 3), $mk('db_20260402_035000.sql.gz', 2), $mk('db_20260403_035000.sql.gz', 1),
];
$del = DbBackup::pickToDelete($files, 7, $now);
sort($del);
check('deletes only old ones', array_map('basename', $del) === ['db_20260101_035000.sql.gz', 'db_20260105_035000.sql.gz', 'db_20260201_035000.sql.gz', 'db_20260301_035000.sql.gz']);
check('newest 3 are never deleted', !in_array('/tmp/bk/db_20260403_035000.sql.gz', $del) && !in_array('/tmp/bk/db_20260402_035000.sql.gz', $del) && !in_array('/tmp/bk/db_20260401_035000.sql.gz', $del));
foreach ($files as $f) touch($f, $now - 100 * 86400);
check('all old -> still keeps the 3 newest', count(DbBackup::pickToDelete($files, 7, $now)) === 4);
check('few files -> deletes nothing', DbBackup::pickToDelete(array_slice($files, 0, 3), 1, $now) === []);

// 3) latest
check('latest()', basename(DbBackup::latest('/tmp/bk')) === 'db_20260403_035000.sql.gz');
check('latest() on missing dir', DbBackup::latest('/tmp/nonexistent') === null);
echo "\n$ok passed, $bad failed\n";
}
