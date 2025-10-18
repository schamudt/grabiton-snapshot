<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const PLAY_DIR = __DIR__ . '/_plays';

function require_post(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>['code'=>'method_not_allowed','msg'=>'POST required']], JSON_UNESCAPED_SLASHES);
    exit;
  }
}

function json_body(): array {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function ip_hash(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  return hash('sha256', $ip);
}

function ensure_store(): void {
  if (!is_dir(PLAY_DIR)) @mkdir(PLAY_DIR, 0775, true);
  if (!is_dir(PLAY_DIR)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'storage_unavailable','msg'=>'cannot create store']], JSON_UNESCAPED_SLASHES);
    exit;
  }
}

function play_path(string $id): string { return PLAY_DIR . '/' . basename($id) . '.json'; }

function load_play(string $id): ?array {
  $p = play_path($id);
  if (!is_file($p)) return null;
  $json = file_get_contents($p);
  $data = json_decode($json, true);
  return is_array($data) ? $data : null;
}

function save_play(string $id, array $data): bool {
  $p = play_path($id);
  return (bool)file_put_contents($p, json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
}
