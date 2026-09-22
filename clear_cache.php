<?php
// Disable opache for this file and check settings
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared!\n";
} else {
    echo "OPcache not active.\n";
}
phpinfo(INFO_MODULES);
