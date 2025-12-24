<?php
header('Content-Type: application/json; charset=UTF-8');
// Minimal health info without DB dependency to avoid cascading failures
$resp = [
  'status' => 'ok',
  'time' => gmdate('c')
];
echo json_encode($resp);
