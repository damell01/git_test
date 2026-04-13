<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Location: ' . APP_URL . '/install/fieldora_install.php', true, 302);
exit;
