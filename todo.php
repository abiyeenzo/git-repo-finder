<?php
declare(strict_types=1);

/**
 * Todo Pro - TODO list moderne en PHP pur
 * Stockage : fichier JSON local, aucune base de données nécessaire.
 * Icons : SVG intégrés, aucun emoji.
 */

// ---- Configuration ----
const TODO_FILE = __DIR__ . '/todo_data.json';
const MAX_TASKS = 1000;
const DATE_FORMAT = 'Y m d';

// ---- CSRF ----
$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf'] = $csrf_token;
if (!session_id()) session_start();

// ---- Helpers ----
function json_load(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function json_save(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function today_str(): string { return date(DATE_FORMAT); }
function escape_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function validate_csrf(): bool {
    $token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['csrf'] ?? '') : ($_GET['csrf'] ?? '');
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

// ---- Icones SVG ----
function svg_check(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
}
function svg_x(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
}
function svg_info(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
}
function svg_sun(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
}
function svg_moon(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
}
function svg_plus(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
}
function svg_clipboard(): string {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>';
}
function svg_trash(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
}
function svg_rotate(): string {
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15.5a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
}
function svg_calendar(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
}
function svg_upload(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
}
function svg_download(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
}
function svg_user(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
}
function svg_work(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>';
}
function svg_study(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
}
function svg_health(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
}
function svg_money(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
}
function svg_game(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="15" y1="13" x2="15.01" y2="13"/><line x1="18" y1="11" x2="18" y2="11"/><rect x="2" y="6" width="20" height="12" rx="2"/></svg>';
}
function svg_pin(): string {
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
}

function cat_svg(string $cat): string {
    return match ($cat) {
        'Personnel' => svg_user(),
        'Travail'   => svg_work(),
        'Études'    => svg_study(),
        'Santé'     => svg_health(),
        'Finances'  => svg_money(),
        'Loisirs'   => svg_game(),
        'Autre'     => svg_pin(),
        default     => svg_pin(),
    };
}

// ---- Data ----
$tasks = json_load(TODO_FILE);
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';
$sort_by = $_GET['sort'] ?? 'date_desc';

// ---- Actions POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf()) {
    $action_post = $_POST['action'] ?? '';
    switch ($action_post) {
        case 'add':
            $text = trim($_POST['task'] ?? '');
            $category = trim($_POST['category'] ?? 'Personnel');
            $priority = (int)($_POST['priority'] ?? 5);
            $due = $_POST['due'] ?? today_str();
            if ($text === '' || strlen($text) > 500) {
                $flash = ['type' => 'error', 'msg' => 'Tâche invalide (max 500 caractères).'];
            } elseif (count($tasks) >= MAX_TASKS) {
                $flash = ['type' => 'error', 'msg' => "Limite de $MAX_TASKS tâches atteinte."];
            } else {
                $tasks[] = [
                    'id'        => bin2hex(random_bytes(4)),
                    'task'      => $text,
                    'done'      => false,
                    'category'  => $category ?: 'Sans catégorie',
                    'priority'  => min(10, max(1, $priority)),
                    'due'       => $due ?: today_str(),
                    'created'   => time(),
                    'completed' => null,
                ];
                json_save(TODO_FILE, $tasks);
                $flash = ['type' => 'success', 'msg' => 'Tâche ajoutée.'];
            }
            break;
        case 'toggle':
            $id = $_POST['id'] ?? '';
            foreach ($tasks as &$t) {
                if ($t['id'] === $id && !$t['done']) {
                    $t['done'] = true;
                    $t['completed'] = time();
                    json_save(TODO_FILE, $tasks);
                    $flash = ['type' => 'success', 'msg' => 'Tâche terminée.'];
                    break;
                }
            }
            break;
        case 'untoggle':
            $id = $_POST['id'] ?? '';
            foreach ($tasks as &$t) {
                if ($t['id'] === $id && $t['done']) {
                    $t['done'] = false;
                    $t['completed'] = null;
                    json_save(TODO_FILE, $tasks);
                    $flash = ['type' => 'info', 'msg' => 'Tâche rouverte.'];
                    break;
                }
            }
            break;
        case 'delete':
            $id = $_POST['id'] ?? '';
            $tasks = array_values(array_filter($tasks, fn($t) => $t['id'] !== $id));
            json_save(TODO_FILE, $tasks);
            $flash = ['type' => 'info', 'msg' => 'Tâche supprimée.'];
            break;
        case 'clear_done':
            $tasks = array_values(array_filter($tasks, fn($t) => !$t['done']));
            json_save(TODO_FILE, $tasks);
            $flash = ['type' => 'info', 'msg' => 'Tâches terminées nettoyées.'];
            break;
        case 'import':
            $file = $_FILES['import_file'] ?? null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($file['tmp_name']);
                $imported = json_decode($content, true);
                if (is_array($imported) && count($imported) <= MAX_TASKS) {
                    $tasks = array_merge($tasks, $imported);
                    json_save(TODO_FILE, $tasks);
                    $flash = ['type' => 'success', 'msg' => count($imported) . ' tâches importées.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Fichier JSON invalide ou trop grand.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Erreur lors de l'importation.'];
            }
            break;
        case 'export':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="todo_export_' . date('Ymd') . '.json"');
            echo json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            break;
        default:
            $flash = ['type' => 'error', 'msg' => 'Action inconnue.'];
    }
}

// ---- GET actions ----
elseif (validate_csrf()) {
    $action_get = $_GET['action'] ?? '';
    if ($action_get === 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $tasks = array_values(array_filter($tasks, fn($t) => $t['id'] !== $id));
        json_save(TODO_FILE, $tasks);
        $flash = ['type' => 'info', 'msg' => 'Tâche supprimée.'];
    }
    if ($action_get === 'clear_done') {
        $tasks = array_values(array_filter($tasks, fn($t) => !$t['done']));
        json_save(TODO_FILE, $tasks);
        $flash = ['type' => 'info', 'msg' => 'Tâches terminées nettoyées.'];
    }
}

// ---- Stats ----
$total = count($tasks);
$done_count = count(array_filter($tasks, fn($t) => $t['done']));
$pending_count = $total - $done_count;
$categories = array_values(array_unique(array_column($tasks, 'category')));
sort($categories);

// ---- Filtres ----
$filtered = $tasks;
if ($filter === 'pending') {
    $filtered = array_filter($filtered, fn($t) => !$t['done']);
} elseif ($filter === 'done') {
    $filtered = array_filter($filtered, fn($t) => $t['done']);
}
if ($cat_filter !== '') {
    $filtered = array_filter($filtered, fn($t) => $t['category'] === $cat_filter);
}
if ($search !== '') {
    $needle = mb_strtolower($search, 'UTF-8');
    $filtered = array_filter($filtered, fn($t) => mb_strpos(mb_strtolower($t['task'], 'UTF-8'), $needle) !== false);
}
$filtered = array_values($filtered);

// Tri
usort($filtered, function ($a, $b) use ($sort_by) {
    return match ($sort_by) {
        'date_desc' => ($b['created'] ?? 0) <=> ($a['created'] ?? 0),
        'date_asc'  => ($a['created'] ?? 0) <=> ($b['created'] ?? 0),
        'prio_high' => $b['priority'] <=> $a['priority'],
        'prio_low'  => $a['priority'] <=> $b['priority'],
        'alpha'     => mb_strtolower($a['task'], 'UTF-8') <=> mb_strtolower($b['task'], 'UTF-8'),
        default     => ($b['created'] ?? 0) <=> ($a['created'] ?? 0),
    };
});

$csrf = $_SESSION['csrf'];
$flash_msg = $flash['msg'] ?? null;
$flash_type = $flash['type'] ?? 'info';
$dark_mode = isset($_COOKIE['todo_dark']) && $_COOKIE['todo_dark'] === '1';

$prio_color = function(int $p): string {
    return match(true) {
        $p <= 3 => '#ef4444',
        $p <= 6 => '#f59e0b',
        $p <= 7 => '#10b981',
        default  => '#6b7280',
    };
};

$prio_svg = function(int $p): string {
    $c = $prio_color($p);
    return "<svg viewBox="0 0 16 16" width="12" height="12" fill="$c" stroke="none"><circle cx="8" cy="8" r="6"/></svg>";
};

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Todo Pro</title>
<style>
:root {
  --bg: #f5f7fa;
  --card-bg: #ffffff;
  --text: #1f2937;
  --text-muted: #6b7280;
  --border: #e5e7eb;
  --primary: #4f46e5;
  --primary-hover: #4338ca;
  --success: #10b981;
  --danger: #ef4444;
  --warning: #f59e0b;
  --shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.04);
  --radius: 12px;
  --radius-sm: 8px;
}
[data-theme="dark"] {
  --bg: #0f172a;
  --card-bg: #1e293b;
  --text: #e2e8f0;
  --text-muted: #94a3b8;
  --border: #334155;
  --shadow: 0 1px 3px rgba(0,0,0,.4), 0 4px 12px rgba(0,0,0,.2);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
    "Helvetica Neue", Arial, sans-serif;
  background: var(--bg); color: var(--text);
  min-height: 100vh; padding: 0 16px 40px;
  transition: background .3s, color .3s;
}
.app { max-width: 720px; margin: 0 auto; }

.header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 0 16px; border-bottom: 1px solid var(--border);
  margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
}
.header h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; display: flex; align-items: center; gap: 10px; }
.header h1 svg { color: var(--primary); flex-shrink: 0; }
.header .brand-text { color: var(--text); }
.header .brand-text span.pro { color: var(--primary); font-weight: 600; }

.theme-toggle {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 6px 12px;
  cursor: pointer; font-size: .85rem; color: var(--text);
  display: flex; align-items: center; gap: 8px;
  transition: background .2s, border-color .2s;
}
.theme-toggle:hover { border-color: var(--primary); color: var(--primary); }
.theme-toggle svg { flex-shrink: 0; }

.stats {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 10px; margin-bottom: 20px;
}
.stat-card {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px 12px;
  text-align: center; box-shadow: var(--shadow);
}
.stat-card .num { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
.stat-card .num.done { color: var(--success); }
.stat-card .num.pending { color: var(--warning); }
.stat-card .label { font-size: .75rem; color: var(--text-muted); margin-top: 4px; }

.filters {
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
  margin-bottom: 16px;
}
.filters select, .filters input {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 8px 12px;
  font-size: .875rem; color: var(--text); font-family: inherit;
}
.filters select:focus, .filters input:focus {
  outline: none; border-color: var(--primary);
}

.form-card {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 20px; margin-bottom: 20px;
  box-shadow: var(--shadow);
}
.form-card h3 {
  font-size: 1rem; margin-bottom: 14px; font-weight: 600;
  display: flex; align-items: center; gap: 8px;
}
.form-card h3 svg { color: var(--primary); flex-shrink: 0; }

.form-row {
  display: grid; grid-template-columns: 2fr 1fr 1fr auto;
  gap: 10px; align-items: end; flex-wrap: wrap;
}
.form-row label {
  font-size: .75rem; color: var(--text-muted);
  margin-bottom: 4px; display: block;
}
.form-row input, .form-row select, .form-row textarea {
  background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 8px 12px;
  font-size: .875rem; color: var(--text); font-family: inherit;
  width: 100%;
}
.form-row textarea { resize: vertical; min-height: 36px; }
.form-row input:focus, .form-row select:focus, .form-row textarea:focus {
  outline: none; border-color: var(--primary);
}

.flash {
  padding: 12px 16px; border-radius: var(--radius-sm);
  margin-bottom: 16px; font-size: .875rem; font-weight: 500;
  display: flex; align-items: center; gap: 10px;
}
.flash.success { background: rgba(16,185,129,.12); color: var(--success); }
.flash.error   { background: rgba(239,68,68,.12); color: var(--danger); }
.flash.info    { background: rgba(79,70,229,.12); color: var(--primary); }
.flash svg { flex-shrink: 0; }

.task-list { display: flex; flex-direction: column; gap: 8px; }

.task {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px 16px;
  box-shadow: var(--shadow); transition: border-color .2s, opacity .2s;
  position: relative;
}
.task:hover { border-color: var(--primary); }
.task.done { opacity: .6; }

.task-form { display: flex; align-items: flex-start; gap: 12px; width: 100%; }
.task-checkbox {
  display: flex; align-items: center; gap: 10px;
  cursor: pointer; flex: 1; min-width: 0;
}
.task-checkbox .hidden-check {
  position: absolute; opacity: 0; width: 0; height: 0;
}
.task-checkbox .check-box {
  width: 20px; height: 20px;
  border: 2px solid var(--border); border-radius: 4px;
  background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  transition: all .2s; flex-shrink: 0; position: relative;
}
.task-checkbox .check-box svg { display: none; }
.task-checkbox .hidden-check:checked + .task-checkbox .check-box {
  background: var(--success); border-color: var(--success);
}
.task-checkbox .hidden-check:checked + .task-checkbox .check-box svg { display: block; }
.task-checkbox .check-text {
  font-size: .95rem; line-height: 1.5; word-break: break-word;
  flex: 1; min-width: 0;
}
.task.done .task-checkbox .check-text {
  text-decoration: line-through; color: var(--text-muted);
}

.task-meta {
  display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
  margin-top: 6px; font-size: .75rem; color: var(--text-muted);
}
.tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 20px; padding: 2px 8px; font-size: .7rem;
  font-weight: 500;
}
.tag.prio { padding-left: 6px; }
.tag.prio::before {
  content: '';
  width: 8px; height: 8px; border-radius: 50%;
  flex-shrink: 0;
}
.tag.prio-H::before { background: var(--danger); }
.tag.prio-M::before { background: var(--warning); }
.tag.prio-L::before { background: var(--success); }

.tag.cat { color: var(--text-muted); border-color: var(--border); background: transparent; padding-left: 2px; }
.tag.cat svg { width: 14px; height: 14px; flex-shrink: 0; }

.due {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 20px;
  background: var(--bg); border: 1px solid var(--border);
  font-size: .7rem; color: var(--text-muted);
}
.due svg { flex-shrink: 0; }
.due.overdue { background: rgba(239,68,68,.1); color: var(--danger); border-color: var(--danger); }

.task-actions { display: flex; gap: 6px; margin-top: 8px; }
.btn-icon {
  background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 4px 8px;
  cursor: pointer; font-size: .8rem; color: var(--text);
  transition: all .2s; font-family: inherit;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-icon:hover { border-color: var(--primary); color: var(--primary); }
.btn-icon.done-btn:hover { border-color: var(--success); color: var(--success); }
.btn-icon.undo-btn:hover { border-color: var(--primary); color: var(--primary); }
.btn-icon.del-btn:hover { border-color: var(--danger); color: var(--danger); }
.btn-icon svg { flex-shrink: 0; }

.empty {
  text-align: center; padding: 40px 20px;
  color: var(--text-muted); font-size: .95rem;
}
.empty h3 { font-weight: 600; margin-bottom: 8px; color: var(--text); }

.footer {
  margin-top: 32px; padding-top: 16px;
  border-top: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 12px;
  font-size: .8rem; color: var(--text-muted);
}
.footer-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.footer button {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: 8px; padding: 6px 12px; cursor: pointer;
  font-size: .78rem; color: var(--text); font-family: inherit;
  display: inline-flex; align-items: center; gap: 6px;
  transition: border-color .2s, color .2s;
}
.footer button:hover { border-color: var(--primary); color: var(--primary); }
.footer button svg { flex-shrink: 0; }

@media (max-width: 600px) {
  .stats { grid-template-columns: repeat(2, 1fr); }
  .form-row { grid-template-columns: 1fr; }
  .header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<div class="app" data-theme="<?= $dark_mode ? 'dark' : 'light' ?>">

<div class="header">
  <h1>
    <?= svg_clipboard() ?>
    <span class="brand-text">Todo <span class="pro">Pro</span></span>
  </h1>
  <button class="theme-toggle" onclick="
    document.cookie='todo_dark='+(document.cookie.includes('todo_dark=1')?'':'1')+';path=/;max-age=31536000';
    location.reload();
  ">
    <?= $dark_mode ? svg_sun() : svg_moon() ?>
    <?= $dark_mode ? 'Mode clair' : 'Mode sombre' ?>
  </button>
</div>

<?php if ($flash_msg): ?>
<div class="flash <?= $flash_type ?>">
  <?php if ($flash_type === 'success'): ?>
    <?= svg_check() ?>
  <?php elseif ($flash_type === 'error'): ?>
    <?= svg_x() ?>
  <?php else: ?>
    <?= svg_info() ?>
  <?php endif; ?>
  <span><?= escape_html($flash_msg) ?></span>
</div>
<?php endif; ?>

<div class="stats">
  <div class="stat-card">
    <div class="num"><?= $total ?></div>
    <div class="label">Total</div>
  </div>
  <div class="stat-card">
    <div class="num pending"><?= $pending_count ?></div>
    <div class="label">À faire</div>
  </div>
  <div class="stat-card">
    <div class="num done"><?= $done_count ?></div>
    <div class="label">Terminées</div>
  </div>
  <div class="stat-card">
    <div class="num" style="color:var(--primary)"><?= $total > 0 ? round($done_count / $total * 100) : 0 ?>%</div>
    <div class="label">Progression</div>
  </div>
</div>

<div class="form-card">
  <h3><?= svg_plus() ?> Nouvelle tâche</h3>
  <form method="post" id="taskForm">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="add">

    <div class="form-row" style="display:block; margin-bottom:12px;">
      <label for="task">Description</label>
      <textarea id="task" name="task" placeholder="Nouvelle tâche à faire…"
        required style="min-height:40px;"></textarea>
    </div>

    <div class="form-row">
      <div>
        <label for="category">Catégorie</label>
        <select id="category" name="category">
          <option value="Personnel">Personnel</option>
          <option value="Travail">Travail</option>
          <option value="Études">Études</option>
          <option value="Santé">Santé</option>
          <option value="Finances">Finances</option>
          <option value="Loisirs">Loisirs</option>
          <option value="Autre">Autre</option>
        </select>
      </div>
      <div>
        <label for="priority">Priorité</label>
        <select id="priority" name="priority">
          <option value="1">Urgente</option>
          <option value="3">Haute</option>
          <option value="5" selected>Moyenne</option>
          <option value="7">Basse</option>
          <option value="10">Peu importante</option>
        </select>
      </div>
      <div>
        <label for="due">Date limite</label>
        <input type="date" id="due" name="due" value="<?= today_str() ?>">
      </div>
      <div>
        <button type="submit" id="addBtn" style="width:100%; margin-top:14px; display:inline-flex; align-items:center; gap:6px;">
          <?= svg_plus() ?>
          Ajouter
        </button>
      </div>
    </div>
  </form>
</div>

<div class="filters">
  <select id="filterSelect" onchange="applyFilters()">
    <option value="all"    <?= $filter === 'all' ? 'selected' : '' ?>>Toutes</option>
    <option value="pending"<?= $filter === 'pending' ? 'selected' : '' ?>>À faire</option>
    <option value="done"   <?= $filter === 'done' ? 'selected' : '' ?>>Terminées</option>
  </select>

  <select id="catFilter" onchange="applyFilters()">
    <option value="" <?= $cat_filter === '' ? 'selected' : '' ?>>Toutes les catégories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= escape_html($c) ?>" <?= $cat_filter === $c ? 'selected' : '' ?>>
        <?= escape_html($c) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <input type="text" id="searchInput" placeholder="Rechercher…"
    value="<?= escape_html($search) ?>"
    oninput="applyFilters()">

  <select id="sortSelect" onchange="applyFilters()">
    <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Plus récent</option>
    <option value="date_asc"  <?= $sort_by === 'date_asc'  ? 'selected' : '' ?>>Plus ancien</option>
    <option value="prio_high" <?= $sort_by === 'prio_high' ? 'selected' : '' ?>>Priorité ↓</option>
    <option value="prio_low"  <?= $sort_by === 'prio_low'  ? 'selected' : '' ?>>Priorité ↑</option>
    <option value="alpha"     <?= $sort_by === 'alpha'     ? 'selected' : '' ?>>A Z</option>
  </select>
</div>

<div class="task-list">
  <?php if (empty($filtered)): ?>
    <div class="empty">
      <h3><?= $search !== '' || $filter !== 'all' || $cat_filter !== '' ? 'Aucun résultat' : 'Aucune tâche' ?></h3>
      <p><?= $search !== '' || $filter !== 'all' || $cat_filter !== '' ? 'Modifiez vos filtres.' : 'Ajoutez votre première tâche ci-dessus.' ?></p>
    </div>
  <?php else: ?>
    <?php foreach ($filtered as $t): ?>
      <div class="task <?= $t['done'] ? 'done' : '' ?>" data-id="<?= escape_html($t['id']) ?>" data-done="<?= $t['done'] ? '1' : '0' ?>">
        <form method="post" style="display:inline" class="task-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="<?= $t['done'] ? 'untoggle' : 'toggle' ?>">
          <input type="hidden" name="id" value="<?= escape_html($t['id']) ?>">
          <input type="checkbox" id="check-<?= escape_html($t['id']) ?>"
            class="hidden-check"
            <?= $t['done'] ? 'checked' : '' ?>
            onchange="this.form.submit()">
          <label for="check-<?= escape_html($t['id']) ?>" class="task-checkbox">
            <span class="check-box">
              <?= svg_check() ?>
            </span>
            <span class="check-text"><?= escape_html($t['task']) ?></span>
          </label>
        </form>

        <?php if ($t['due'] && $t['due'] !== today_str()): ?>
          <div class="task-meta">
            <span class="due <?= strtotime($t['due']) < time() && !$t['done'] ? 'overdue' : '' ?>">
              <?= svg_calendar() ?>
              <?= escape_html($t['due']) ?>
            </span>
          </div>
        <?php endif; ?>

        <div class="task-meta">
          <span class="tag prio prio-<?= match(true) {
              $t['priority'] <= 3 => 'H',
              $t['priority'] <= 6 => 'M',
              default              => 'L',
          } ?>">
            <?= $t['priority'] ?>
          </span>
          <span class="tag cat">
            <?= cat_svg($t['category']) ?>
            <?= escape_html($t['category']) ?>
          </span>
        </div>

        <div class="task-actions">
          <form method="post" style="display:inline"
            onsubmit="return confirm('Supprimer cette tâche ?');">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= escape_html($t['id']) ?>">
            <button type="submit" class="btn-icon del-btn" title="Supprimer">
              <?= svg_trash() ?>
              Supprimer
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="footer">
  <span><?= $total ?> tâches · <?= $done_count ?> terminées ·
    <?= $total > 0 ? round($done_count / $total * 100) : 0 ?>% complétées</span>

  <div class="footer-actions">
    <form method="get" style="display:inline">
      <input type="hidden" name="action" value="clear_done">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button type="submit">
        <?= svg_trash() ?>
        Nettoyer les terminées
      </button>
    </form>
    <form method="post" enctype="multipart/form-data" style="display:inline">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="import">
      <input type="file" name="import_file" accept=".json" style="display:none;"
        id="importFile" onchange="this.form.submit()">
      <button type="button"
        onclick="document.getElementById('importFile').click()">
        <?= svg_upload() ?>
        Importer
      </button>
    </form>
    <form method="post" style="display:inline">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="export">
      <button type="submit">
        <?= svg_download() ?>
        Exporter JSON
      </button>
    </form>
  </div>
</div>

</div>

<script>
function applyFilters() {
  const filter = document.getElementById('filterSelect').value;
  const cat    = document.getElementById('catFilter').value;
  const search = document.getElementById('searchInput').value;
  const sort   = document.getElementById('sortSelect').value;
  const params = new URLSearchParams({
    filter, category: cat, search, sort,
    csrf: '<?= $csrf ?>'
  });
  fetch('?' + params.toString())
    .then(r => r.text())
    .then(html => {
      const temp = document.createElement('div');
      temp.innerHTML = html;
      const newList = temp.querySelector('.task-list');
      const newStats = temp.querySelector('.stats');
      const newEmpty = temp.querySelector('.empty');
      const newFlash = temp.querySelector('.flash');
      if (newFlash) {
        const oldFlash = document.querySelector('.flash');
        if (oldFlash) oldFlash.replaceWith(newFlash);
        else document.querySelector('.app').insertBefore(newFlash,
          document.querySelector('.form-card'));
      }
      if (newList) {
        document.querySelector('.task-list').innerHTML = newList.innerHTML;
      }
      if (newStats) {
        document.querySelector('.stats').innerHTML = newStats.innerHTML;
      }
      if (newEmpty) {
        document.querySelector('.task-list').innerHTML = newEmpty.outerHTML;
      }
      if (!newList && !newEmpty) {
        document.querySelector('.task-list').innerHTML =
          temp.querySelector('.task-list').innerHTML;
      }
    })
    .catch(() => location.reload());
}

document.getElementById('taskForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('addBtn');
  btn.disabled = true;
  btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajout…';

  const formData = new FormData(this);
  fetch('', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(html => {
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajouter';
      const temp = document.createElement('div');
      temp.innerHTML = html;
      const flash = temp.querySelector('.flash');
      if (flash) {
        const old = document.querySelector('.flash');
        if (old) old.replaceWith(flash);
        else document.querySelector('.app').insertBefore(flash,
          document.querySelector('.form-card'));
      }
      if (flash && flash.classList.contains('success')) {
        applyFilters();
        document.getElementById('task').value = '';
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajouter';
      location.reload();
    });
});
</script>
</body>
</html>
