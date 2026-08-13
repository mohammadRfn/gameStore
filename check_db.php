<?php
$pdo = new PDO("sqlite:" . __DIR__ . "/database/nativephp.sqlite");
$rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='shops'")->fetchAll();
echo count($rows) ? "shops table EXISTS" : "shops table NOT FOUND";
echo "\n";

// check service_jobs columns
$cols = $pdo->query("PRAGMA table_info(service_jobs)")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) echo $col['name'] . "\n";