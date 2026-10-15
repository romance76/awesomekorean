<?php
// same logic as AdminSettingsController::generateVapid (key generation part), including the no-openssl.cnf fallback
$opts = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'private_key_bits' => 2048];
$res = function_exists('openssl_pkey_new') ? @openssl_pkey_new($opts) : false;
if (!$res && function_exists('openssl_pkey_new')) {
    // 서버에 openssl.cnf 가 없을 때를 대비해 최소 설정 파일로 한 번 더 시도
    $cnf = tempnam(sys_get_temp_dir(), 'ossl');
    file_put_contents($cnf, "[req]\ndistinguished_name=req_dn\n[req_dn]\n");
    $res = @openssl_pkey_new($opts + ['config' => $cnf]);
    @unlink($cnf);
}
$ec = $res ? (openssl_pkey_get_details($res)['ec'] ?? null) : null;
if (!$ec || empty($ec['x']) || empty($ec['y']) || empty($ec['d'])) { echo "FAIL: no ec details\n"; exit; }
$b64u = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
$pad = fn (string $v) => str_pad($v, 32, "\0", STR_PAD_LEFT);
$public = $b64u("\x04" . $pad($ec['x']) . $pad($ec['y']));
$private = $b64u($pad($ec['d']));
$dec = fn (string $s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
echo "public bytes: ", strlen($dec($public)), " (expect 65), first byte: ", bin2hex($dec($public)[0]), " (expect 04)\n";
echo "private bytes: ", strlen($dec($private)), " (expect 32)\n";
echo "public length chars: ", strlen($public), " (expect 87), private: ", strlen($private), " (expect 43)\n";
echo "url-safe, no padding: ", (preg_match('/^[A-Za-z0-9_-]+$/', $public . $private) ? 'yes' : 'NO'), "\n";
// the public key must be on the curve and belong to the private key: rebuild a PEM-less check via ECDH-free comparison
$dd = openssl_pkey_get_details($res);
echo "curve: ", $dd['ec']['curve_name'] ?? '?', "\n";
