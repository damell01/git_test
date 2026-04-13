<?php
require_once __DIR__ . '/config/config.php';

header('Location: ' . SITE_URL . '/login', true, 302);
exit;
