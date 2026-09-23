<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\MyApp\AppInfo\Application::APP_ID, OCA\MyApp\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\MyApp\AppInfo\Application::APP_ID, OCA\MyApp\AppInfo\Application::APP_ID . '-main');

?>

<div id="myapp"></div>
