<?php
// Webhook disabled - use manual deploy: ssh root@68.183.60.70 "cd /var/www/somekorean && bash deploy.sh"
// Server memory too low for automated builds
http_response_code(200);
echo "OK (deploy disabled - run manually)";
