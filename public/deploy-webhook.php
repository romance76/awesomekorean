<?php
// GitHub Webhook auto-deploy
$secret = "somekorean-deploy-2026";
$signature = $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? "";
$payload = file_get_contents("php://input");

if (!$signature) {
    http_response_code(403);
    die("No signature");
}

$expected = "sha256=" . hash_hmac("sha256", $payload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    die("Invalid signature");
}

$data = json_decode($payload, true);
if (($data["ref"] ?? "") !== "refs/heads/main") {
    die("Not main branch");
}

$log = "/var/www/somekorean/storage/logs/deploy.log";
// sudo로 root 권한으로 실행
$cmd = "sudo /var/www/somekorean/deploy.sh >> $log 2>&1 &";
exec($cmd);
echo "Deploy started";
