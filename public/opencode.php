<?php
error_reporting(0);
define('REPO_PATH', dirname(__DIR__) . '/repo');

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// --- Database + AI setup ---
function env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val === false || $val === '') return $default;
    $lower = strtolower($val);
    if (in_array($lower, ['true', '(true)'])) return true;
    if (in_array($lower, ['false', '(false)'])) return false;
    if (in_array($lower, ['null', '(null)'])) return null;
    return $val;
}
$db = null;
try {
    $dbConfig = require dirname(__DIR__) . '/config/database.php';
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\Throwable $e) {
    // DB not available — commands only, no AI
}

function run_cmd(string $cmd): ?string {
    $out = null; $rc = -1;
    if (function_exists('shell_exec')) {
        $o = @shell_exec($cmd);
        if ($o !== null) return $o;
    }
    if (function_exists('exec')) {
        @exec($cmd, $lines, $rc);
        if ($lines) return implode("\n", $lines);
    }
    if (function_exists('proc_open')) {
        $spec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $p = @proc_open($cmd, $spec, $pipes, null, null, ['bypass_shell' => true]);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($p);
            if ($out !== false && $out !== '') return $out;
        }
    }
    return null;
}

function site_val(string $key, string $default = ''): string {
    global $db;
    if (!$db) return $default;
    try {
        $s = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $s->execute([$key]);
        $r = $s->fetch();
        return $r ? ($r['setting_value'] ?? $default) : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

$httpResponseCode = 0;

function ai_chat(array $history): ?string {
    global $httpResponseCode;
    $provider = site_val('opencode_ai_provider', '');
    $apiKey = site_val('opencode_ai_key', '');
    $model = site_val('opencode_ai_model', '');
    $baseUrl = rtrim(site_val('opencode_ai_base_url', ''), '/');
    if (!$provider || !$apiKey) return null;

    $systemPrompt = "You are OpenCode, an AI coding assistant for the SUGGAWAYZ e-commerce project — a futuristic streetwear brand, running on custom PHP/MySQL (no framework). You have full filesystem access to the repo at /www/wwwroot/suggawayz/repo/. Be concise and helpful.";

    if ($provider === 'openai' || $provider === 'custom') {
        $url = $baseUrl ? "$baseUrl/v1/chat/completions" : 'https://api.openai.com/v1/chat/completions';
        $m = $model ?: ($provider === 'openai' ? 'gpt-4o' : 'gpt-4o-mini');
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history
        );
        $payload = json_encode(['model' => $m, 'messages' => $messages, 'max_tokens' => 2048]);
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey",
            'content' => $payload,
            'timeout' => 60,
        ]]);
        $result = @file_get_contents($url, false, $ctx);
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header ?? [];
        if ($result === false) {
            $httpCode = '?';
            if (!empty($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m2)) $httpCode = $m2[1];
            $errMsg = error_get_last()['message'] ?? 'connection failed';
            return "⚠️ AI error (HTTP $httpCode): $errMsg";
        }
        $data = json_decode($result, true);
        if (isset($data['error'])) {
            return "⚠️ AI API error: " . ($data['error']['message'] ?? json_encode($data['error']));
        }
        return $data['choices'][0]['message']['content'] ?? 'AI: no response.';
    }

    if ($provider === 'anthropic') {
        $url = 'https://api.anthropic.com/v1/messages';
        $m = $model ?: 'claude-sonnet-4-20250514';
        $messages = [];
        foreach ($history as $h) {
            if ($h['role'] === 'system') continue;
            $messages[] = $h;
        }
        $payload = json_encode([
            'model' => $m,
            'system' => $systemPrompt,
            'messages' => $messages,
            'max_tokens' => 2048,
        ]);
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nx-api-key: $apiKey\r\nanthropic-version: 2023-06-01",
            'content' => $payload,
            'timeout' => 60,
        ]]);
        $result = @file_get_contents($url, false, $ctx);
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header ?? [];
        if ($result === false) {
            $httpCode = '?';
            if (!empty($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m2)) $httpCode = $m2[1];
            $errMsg = error_get_last()['message'] ?? 'connection failed';
            return "⚠️ AI error (HTTP $httpCode): $errMsg";
        }
        $data = json_decode($result, true);
        if (isset($data['error'])) {
            return "⚠️ AI API error: " . ($data['error']['message'] ?? json_encode($data['error']));
        }
        return $data['content'][0]['text'] ?? 'AI: no response.';
    }

    return null; // No provider configured
}

$tab = $_GET['tab'] ?? 'browse';

// Chat POST endpoint
if ($tab === 'chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $message = trim($_POST['message'] ?? '');
    $historyJson = trim($_POST['history'] ?? '[]');
    $history = json_decode($historyJson, true) ?: [];
    if (!$message) { echo json_encode(['reply' => 'Say something.']); exit; }

    // Try AI first (history already includes the user message from the client)
    $aiReply = ai_chat($history);
    if ($aiReply !== null) {
        // Execute any [EXEC: command] markers in the AI reply
        $reply = $aiReply;
        $execResults = [];
        $execCount = 0;
        while (preg_match('/\[EXEC:\s*(.+?)\s*\]/s', $reply, $m) && $execCount++ < 20) {
            $cmdResult = handle_chat(trim($m[1]));
            $output = $cmdResult['reply'] ?? '';
            $execResults[] = "> \$ " . trim($m[1]) . "\n\n" . $output;
            $reply = str_replace($m[0], '', $reply);
        }
        if ($execResults) {
            $reply = trim($reply) . "\n\n---\n" . implode("\n\n", $execResults);
        }
        echo json_encode(['reply' => $reply]);
        exit;
    }

    // Fallback to command mode
    echo json_encode(handle_chat($message));
    exit;
}

$route = trim(ltrim(str_replace('\\', '/', $_GET['file'] ?? ''), '/'));
if ($route && str_contains($route, '..')) die('Invalid path');

$fullPath = REPO_PATH . '/' . ltrim($route, '/');
$fullPath = rtrim($fullPath, '/');

if ($route) {
    $rp1 = realpath($fullPath);
    $rp2 = realpath(REPO_PATH);
    if ($rp1 === false || $rp2 === false || !str_starts_with($rp1, $rp2)) die('Access denied');
}

$ext = $fullPath ? strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) : '';

if ($route && file_exists($fullPath) && is_file($fullPath) && in_array($ext, ['png','jpg','jpeg','gif','webp','svg','ico','woff2','woff','ttf','eot'])) {
    $mime = match($ext) {
        'png' => 'image/png', 'jpg','jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico'=>'image/x-icon',
        'woff2'=>'font/woff2','woff'=>'font/woff','ttf'=>'font/ttf','eot'=>'application/vnd.ms-fontobject',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($fullPath);
    exit;
}

$pageTitle = $route ? basename($fullPath) . ' — ' : '';
$pageTitle .= $tab === 'chat' ? 'Chat' : 'Browse';
$pageTitle .= ' — SUGGAWAYZ OpenCode';

$isTextFile = in_array($ext, ['php','html','htm','css','scss','less','js','jsx','ts','tsx','vue','json','md','sql','yml','yaml','xml','sh','bash','bat','ps1','env','gitignore','txt','conf','ini','lock','py','rb','go','rs','c','cpp','h','hpp','java','kt','swift','yaml','yml']);

function safe_path(string $path): ?string {
    $full = $path[0] === '/' || str_starts_with($path, REPO_PATH) ? $path : REPO_PATH . '/' . $path;
    $full = rtrim($full, '/');
    $real = realpath(dirname($full)) . '/' . basename($full);
    if (!str_starts_with($real, realpath(REPO_PATH))) return null;
    return $full;
}

function handle_chat(string $msg): array {
    $msg = trim($msg);
    $lower = strtolower($msg);
    
    if (in_array($lower, ['hi','hello','hey','yo','sup','help','?'])) {
        return ['reply' => "👾 **OpenCode** — SUGGAWAYZ dev assistant\n\n**Browse:** `ls`, `tree`, `cat <file>`, `find <name>`, `search <term>`\n**Edit:** `create <path>`, `write <path> | <content>`, `append <path> | <content>`, `edit <path> | <old> | <new>`\n**Manage:** `rm <path>`, `mkdir <path>`, `mv <src> <dst>`, `cp <src> <dst>`\n**Git:** `git <args>`, `info <file>`, `count`, `php -v`"];
    }
    
    // Resolve path helper for file ops
    $resolvePath = function(string $p): ?string {
        $full = ($p[0] === '/' ? $p : REPO_PATH . '/' . $p);
        $full = rtrim($full, '/');
        $dir = dirname($full);
        $base = basename($full);
        $rp = realpath($dir);
        if ($rp === false || !str_starts_with($rp, realpath(REPO_PATH))) return null;
        $resolved = $rp . '/' . $base;
        if (str_contains($resolved, '/..') || str_contains($resolved, '\\..')) return null;
        return $resolved;
    };
    
    // --- CREATE / TOUCH ---
    if (str_starts_with($lower, 'create ') || str_starts_with($lower, 'touch ')) {
        $path = trim(substr($msg, strpos($msg, ' ') + 1));
        if (!$path) return ['reply' => 'Specify a file path.'];
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path (must be within repo).'];
        if (file_exists($full)) return ['reply' => "File already exists: `$path`"];
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($full, '');
        return ['reply' => "✅ Created empty file `$path`"];
    }
    
    // --- WRITE / SAVE ---
    if (str_starts_with($lower, 'write ') || str_starts_with($lower, 'save ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $pipePos = strpos($rest, ' | ');
        if ($pipePos === false) {
            $full = $resolvePath($rest);
            if (!$full) return ['reply' => 'Invalid path.'];
            $dir = dirname($full);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($full, '');
            return ['reply' => "✅ Created empty file `$rest`"];
        }
        $path = trim(substr($rest, 0, $pipePos));
        $content = trim(substr($rest, $pipePos + 3));
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path.'];
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($full, $content);
        $lines = substr_count($content, "\n") + 1;
        return ['reply' => "✅ Written " . strlen($content) . " bytes / $lines lines to `$path`"];
    }
    
    // --- APPEND / ADD ---
    if (str_starts_with($lower, 'append ') || str_starts_with($lower, 'add ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $pipePos = strpos($rest, ' | ');
        if ($pipePos === false) return ['reply' => 'Usage: `append <path> | <content>`'];
        $path = trim(substr($rest, 0, $pipePos));
        $content = "\n" . trim(substr($rest, $pipePos + 3));
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path.'];
        if (!file_exists($full)) return ['reply' => "File not found: `$path`"];
        file_put_contents($full, $content, FILE_APPEND);
        return ['reply' => "✅ Appended to `$path`"];
    }
    
    // --- EDIT / REPLACE ---
    if (str_starts_with($lower, 'edit ') || str_starts_with($lower, 'replace ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $parts = explode(' | ', $rest);
        if (count($parts) < 3) return ['reply' => 'Usage: `edit <path> | <old text> | <new text>`'];
        $path = trim($parts[0]);
        $old = $parts[1];
        $new = implode(' | ', array_slice($parts, 2));
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path.'];
        if (!file_exists($full)) return ['reply' => "File not found: `$path`"];
        $content = file_get_contents($full);
        if (!str_contains($content, $old)) return ['reply' => "Text not found in `$path`: `$old`"];
        $newContent = str_replace($old, $new, $content);
        $count = substr_count($content, $old);
        $changed = $count > 1 ? substr_count($content, $old) - substr_count($newContent, $old) : 1;
        file_put_contents($full, $newContent);
        return ['reply' => "✅ Replaced $changed occurrence(s) in `$path`"];
    }
    
    // --- RM / DELETE ---
    if (str_starts_with($lower, 'rm ') || str_starts_with($lower, 'delete ') || str_starts_with($lower, 'del ')) {
        $path = trim(substr($msg, strpos($msg, ' ') + 1));
        if (!$path) return ['reply' => 'Specify a file path.'];
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path.'];
        if (!file_exists($full)) return ['reply' => "Not found: `$path`"];
        if (str_contains($full, '/.git/') || str_ends_with($full, '/.git')) return ['reply' => 'Cannot delete .git directory.'];
        if (is_dir($full)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            rmdir($full);
            return ['reply' => "✅ Deleted directory `$path`"];
        }
        unlink($full);
        return ['reply' => "✅ Deleted file `$path`"];
    }
    
    // --- MKDIR ---
    if (str_starts_with($lower, 'mkdir ')) {
        $path = trim(substr($msg, strpos($msg, ' ') + 1));
        if (!$path) return ['reply' => 'Specify a directory path.'];
        $full = $resolvePath($path);
        if (!$full) return ['reply' => 'Invalid path.'];
        if (file_exists($full)) return ['reply' => "Already exists: `$path`"];
        mkdir($full, 0755, true);
        return ['reply' => "✅ Created directory `$path`"];
    }
    
    // --- MV / RENAME ---
    if (str_starts_with($lower, 'mv ') || str_starts_with($lower, 'rename ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $parts = preg_split('/\s+/', $rest, 2);
        if (count($parts) < 2) return ['reply' => 'Usage: `mv <src> <dst>`'];
        $src = $resolvePath($parts[0]);
        $dst = $resolvePath($parts[1]);
        if (!$src || !$dst) return ['reply' => 'Invalid path (must be within repo).'];
        if (!file_exists($src)) return ['reply' => "Not found: `{$parts[0]}`"];
        if (file_exists($dst)) return ['reply' => "Destination exists: `{$parts[1]}`"];
        $dir = dirname($dst);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        rename($src, $dst);
        return ['reply' => "✅ Moved `{$parts[0]}` → `{$parts[1]}`"];
    }
    
    // --- CP / COPY ---
    if (str_starts_with($lower, 'cp ') || str_starts_with($lower, 'copy ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $parts = preg_split('/\s+/', $rest, 2);
        if (count($parts) < 2) return ['reply' => 'Usage: `cp <src> <dst>`'];
        $src = $resolvePath($parts[0]);
        $dst = $resolvePath($parts[1]);
        if (!$src || !$dst) return ['reply' => 'Invalid path (must be within repo).'];
        if (!file_exists($src)) return ['reply' => "Not found: `{$parts[0]}`"];
        $dir = dirname($dst);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (is_dir($src)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $f) {
                $rel = str_replace($src, '', $f->getPathname());
                $dest = $dst . $rel;
                $f->isDir() ? mkdir($dest, 0755, true) : copy($f->getPathname(), $dest);
            }
        } else {
            copy($src, $dst);
        }
        return ['reply' => "✅ Copied `{$parts[0]}` → `{$parts[1]}`"];
    }
    
    // --- CHMOD ---
    if (str_starts_with($lower, 'chmod ')) {
        $rest = trim(substr($msg, strpos($msg, ' ') + 1));
        $parts = preg_split('/\s+/', $rest, 2);
        if (count($parts) < 2) return ['reply' => 'Usage: `chmod <perms> <path>` (perms like 755)'];
        $full = $resolvePath($parts[1]);
        if (!$full) return ['reply' => 'Invalid path.'];
        if (!file_exists($full)) return ['reply' => "Not found: `{$parts[1]}`"];
        $perms = intval($parts[0], 8);
        chmod($full, $perms);
        return ['reply' => "✅ Changed mode of `{$parts[1]}` to " . $parts[0]];
    }
    
    // --- LS ---
    if (str_starts_with($lower, 'ls')) {
        $parts = explode(' ', $msg, 2);
        $path = isset($parts[1]) ? trim($parts[1]) : '';
        if ($path && !str_starts_with($path, '/')) $path = REPO_PATH . '/' . $path;
        if (!$path) $path = REPO_PATH;
        if (!is_dir($path)) return ['reply' => "Not a directory: $path"];
        $items = array_diff(scandir($path), ['.', '..', '.git']);
        $dirs = []; $files = [];
        foreach ($items as $item) {
            $full = $path . '/' . $item;
            if (is_dir($full)) $dirs[] = $item;
            else $files[] = $item;
        }
        sort($dirs); sort($files);
        $out = "📁 **" . count($dirs) . " dirs**  📄 **" . count($files) . " files**\n\n";
        foreach ($dirs as $d) $out .= "📁 `$d/`\n";
        foreach ($files as $f) {
            $s = filesize($path . '/' . $f);
            $sz = $s > 1048576 ? number_format($s/1048576,1).'MB' : ($s > 1024 ? number_format($s/1024,1).'KB' : $s.'B');
            $ic = match(strtolower(pathinfo($f,PATHINFO_EXTENSION))){'php'=>'🐘','css'=>'🎹','js'=>'⚡','md'=>'📵','sql'=>'🗄️','json'=>'📋','yml'=>'⚙️','html'=>'🌐',default=>'📄'};
            $out .= "$ic `$f` ($sz)\n";
        }
        return ['reply' => $out];
    }
    
    // --- CAT / VIEW ---
    if (str_starts_with($lower, 'cat ') || str_starts_with($lower, 'view ')) {
        $parts = explode(' ', $msg, 2);
        $path = isset($parts[1]) ? trim($parts[1]) : '';
        if (!$path) return ['reply' => 'Specify a file path.'];
        $full = $path[0] === '/' ? $path : REPO_PATH . '/' . $path;
        if (!file_exists($full) || !is_file($full)) return ['reply' => "File not found: $path"];
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php','html','css','js','md','sql','json','yml','yaml','xml','sh','txt','conf','ini','py','rb','go','rs','c','cpp','h','hpp','java','kt','swift','ts','tsx','jsx','vue','env','gitignore','bat','ps1','lock'])) {
            return ['reply' => "Binary file (.$ext). Use the file browser instead."];
        }
        $content = file_get_contents($full);
        $lines = explode("\n", $content);
        $maxLines = 60;
        $truncated = count($lines) > $maxLines;
        $out = "**`" . basename($full) . "** (" . count($lines) . " lines, " . number_format(strlen($content)) . " bytes)\n\n```" . $ext . "\n";
        $out .= implode("\n", array_slice($lines, 0, $maxLines));
        if ($truncated) $out .= "\n... (" . (count($lines) - $maxLines) . " more lines)";
        $out .= "\n```";
        if ($truncated) $out .= "\n\n> Open in browser: `/opencode.php?file=" . e($path) . "`";
        return ['reply' => $out];
    }
    
    // --- FIND ---
    if (str_starts_with($lower, 'find ')) {
        $parts = explode(' ', $msg, 2);
        $name = isset($parts[1]) ? trim($parts[1]) : '';
        if (!$name) return ['reply' => 'Specify a filename to find.'];
        $results = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_contains(strtolower($f->getFilename()), strtolower($name))) {
                $rel = str_replace(REPO_PATH . '/', '', $f->getPathname());
                $results[] = $rel;
            }
        }
        if (!$results) return ['reply' => "No files matching `$name` found."];
        $out = "**" . count($results) . " files matching `$name`:**\n\n";
        foreach (array_slice($results, 0, 30) as $r) $out .= "- `$r`\n";
        if (count($results) > 30) $out .= "\n... and " . (count($results) - 30) . " more";
        return ['reply' => $out];
    }
    
    // --- SEARCH ---
    if (str_starts_with($lower, 'search ')) {
        $parts = explode(' ', $msg, 2);
        $term = isset($parts[1]) ? trim($parts[1]) : '';
        if (!$term) return ['reply' => 'Specify a search term.'];
        $results = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getSize() < 500000) {
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, ['php','html','css','js','md','sql','json','yml','yaml','xml','sh','txt','conf','ini','py','rb','go','rs','c','cpp','h','hpp','java','kt','swift','ts','tsx','jsx','vue'])) continue;
                $content = file_get_contents($f->getPathname());
                if (str_contains($content, $term)) {
                    $rel = str_replace(REPO_PATH . '/', '', $f->getPathname());
                    $results[] = $rel;
                }
            }
        }
        if (!$results) return ['reply' => "No results for `$term`."];
        $out = "**" . count($results) . " files containing `$term`:**\n\n";
        foreach (array_slice($results, 0, 20) as $r) $out .= "- `$r`\n";
        if (count($results) > 20) $out .= "\n... and " . (count($results) - 20) . " more";
        return ['reply' => $out];
    }
    
    // --- GIT ---
    if (str_starts_with($lower, 'git ')) {
        $cmd = substr($msg, 4);
        if (str_contains($cmd, ';') || str_contains($cmd, '|') || str_contains($cmd, '&&')) return ['reply' => 'Command blocked for safety.'];
        $fullCmd = "cd " . escapeshellarg(REPO_PATH) . " && git " . escapeshellcmd($cmd) . " 2>&1";
        $output = run_cmd($fullCmd);
        if ($output === null) return ['reply' => '⚠️ Server shell execution is disabled. Git commands are not available in the web chat. Use SSH or the terminal instead.'];
        $output = trim($output);
        if (strlen($output) > 3000) $output = substr($output, 0, 3000) . "\n... (truncated)";
        return ['reply' => "```\n$ " . e("git $cmd") . "\n" . e($output) . "\n```"];
    }
    
    // --- TREE ---
    if (str_starts_with($lower, 'tree')) {
        $parts = explode(' ', $msg, 2);
        $dir = isset($parts[1]) ? trim($parts[1]) : '';
        if ($dir && !str_starts_with($dir, '/')) $dir = REPO_PATH . '/' . $dir;
        if (!$dir) $dir = REPO_PATH;
        if (!is_dir($dir)) return ['reply' => "Not a directory."];
        $out = '';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $max = 100; $count = 0;
        foreach ($it as $f) {
            if ($count++ >= $max) break;
            $depth = $it->getDepth();
            $prefix = str_repeat('  ', $depth) . ($f->isDir() ? '📁' : '📄');
            $out .= "$prefix " . $f->getFilename() . "\n";
        }
        return ['reply' => "```\n$out```\n" . ($count >= $max ? "(truncated at $max items)" : '')];
    }
    
    // --- COUNT ---
    if (str_starts_with($lower, 'count')) {
        $exts = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $e = strtolower($f->getExtension());
                $exts[$e] = ($exts[$e] ?? 0) + 1;
            }
        }
        arsort($exts);
        $total = array_sum($exts);
        $out = "**$total total files**\n\n";
        foreach (array_slice($exts, 0, 20) as $e => $c) {
            $pct = round($c / $total * 100);
            $bar = str_repeat('█', max(1, (int)($pct / 5))) . str_repeat('░', max(0, 20 - (int)($pct / 5)));
            $out .= "`.$e`  $bar  $c ($pct%)\n";
        }
        return ['reply' => $out];
    }
    
    // --- INFO ---
    if (str_starts_with($lower, 'info ')) {
        $path = substr($msg, 5);
        $full = $path[0] === '/' ? $path : REPO_PATH . '/' . $path;
        if (!file_exists($full)) return ['reply' => "Not found: $path"];
        $stat = stat($full);
        $out = "**`$path`**\n\n";
        if (is_dir($full)) {
            $count = count(array_diff(scandir($full), ['.','..']));
            $out .= "- Type: 📁 Directory\n- Items: $count\n";
        } else {
            $out .= "- Type: 📄 File\n- Size: " . number_format($stat['size']) . " bytes (" . round($stat['size']/1024,1) . " KB)\n";
            $out .= "- Modified: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
            $out .= "- Permissions: " . substr(sprintf('%o', $stat['mode']), -4) . "\n";
        }
        return ['reply' => $out];
    }
    
    // Safe shell commands
    $safeCommands = ['php -v', 'php -m', 'composer --version', 'node --version', 'npm --version', 'which ', 'pwd', 'whoami', 'id', 'date', 'uptime', 'df -h', 'free -h', 'uname -a', 'lsb_release -a', 'cat /etc/os-release'];
    foreach ($safeCommands as $sc) {
        if ($msg === $sc || str_starts_with($lower, $sc)) {
            $output = run_cmd($msg . ' 2>&1');
            if ($output === null) return ['reply' => '⚠️ Server shell execution is disabled.'];
            return ['reply' => "```\n$ " . e($msg) . "\n" . e(trim($output)) . "\n```"];
        }
    }
    
    // --- SQL query ---
    if (str_starts_with($lower, 'sql ')) {
        global $db;
        if (!$db) return ['reply' => '⚠️ Database not available.'];
        $q = trim(substr($msg, 4));
        if (preg_match('/^\s*(DROP|ALTER|TRUNCATE|CREATE)\b/i', $q)) return ['reply' => '⚠️ Destructive queries blocked.'];
        try {
            $stmt = $db->query($q);
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $q)) {
                return ['reply' => "✅ Query executed. {$stmt->rowCount()} rows affected."];
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) return ['reply' => 'Query returned 0 rows.'];
            $out = "**{$stmt->rowCount()} rows**\n\n```\n";
            $cols = array_keys($rows[0]);
            $out .= implode(' | ', $cols) . "\n";
            $out .= str_repeat('-', strlen(implode(' | ', $cols))) . "\n";
            foreach (array_slice($rows, 0, 30) as $r) {
                $vals = array_map(fn($v) => substr((string)$v, 0, 40), array_values($r));
                $out .= implode(' | ', $vals) . "\n";
            }
            if (count($rows) > 30) $out .= "... and " . (count($rows)-30) . " more rows\n";
            $out .= "```";
            return ['reply' => $out];
        } catch (\Throwable $e) {
            return ['reply' => '⚠️ SQL error: ' . $e->getMessage()];
        }
    }
    
    // --- SET site setting ---
    if (str_starts_with($lower, 'set ')) {
        global $db;
        if (!$db) return ['reply' => '⚠️ Database not available.'];
        $rest = trim(substr($msg, 4));
        $pipe = strpos($rest, ' | ');
        if ($pipe === false) return ['reply' => 'Usage: `set <key> | <value>`'];
        $key = trim(substr($rest, 0, $pipe));
        $val = trim(substr($rest, $pipe + 3));
        $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $val]);
        return ['reply' => "✅ Site setting `$key` updated."];
    }
    
    return ['reply' => "I don't understand that command. Try `help` to see what I can do.\n\nAvailable: `create`, `write`, `append`, `edit`, `rm`, `mkdir`, `mv`, `cp`, `cat`, `ls`, `find`, `search`, `git`, `tree`, `count`, `info`, `chmod`, `sql`, `set`"];
}

$isFileView = $route && file_exists($fullPath) && is_file($fullPath);
if ($isFileView && $isTextFile) {
    $fileContent = file_get_contents($fullPath);
    $fileLines = explode("\n", $fileContent);
    $totalLines = count($fileLines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0a0a0f;--surface:#12121a;--surface2:#1a1a26;--border:#2a2a3a;--text:#e0e0e0;--text2:#8888a0;--accent:#00bcd4;--accent2:#7c3aed;--green:#22c55e;--red:#ef4444;--orange:#f59e0b;--font:'Inter',sans-serif;--mono:'JetBrains Mono',monospace}
body{background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh;display:flex;flex-direction:column}
header{background:var(--surface);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
header h1{font-size:16px;font-weight:600;color:var(--accent);cursor:pointer}
header h1 span{color:var(--text2);font-weight:400}
header nav{display:flex;gap:4px;margin-left:auto;align-items:center}
header nav a{color:var(--text2);text-decoration:none;font-size:13px;padding:6px 14px;border-radius:4px;transition:.15s}
header nav a:hover{color:var(--text);background:var(--surface2)}
header nav a.active{color:var(--accent);background:rgba(0,188,212,0.1)}
.breadcrumb{background:var(--surface);padding:8px 24px;border-bottom:1px solid var(--border);font-size:13px;display:flex;flex-wrap:wrap;gap:4px}
.breadcrumb a{color:var(--accent);text-decoration:none}
.breadcrumb span{color:var(--text2)}
.container{display:grid;grid-template-columns:260px 1fr;flex:1;min-height:0}
.sidebar{background:var(--surface);border-right:1px solid var(--border);overflow-y:auto;padding:6px;font-size:13px}
.sidebar .section-title{color:var(--text2);font-size:10px;text-transform:uppercase;letter-spacing:.08em;padding:10px 10px 4px;font-weight:600}
.sidebar a{display:flex;align-items:center;gap:6px;padding:3px 10px;color:var(--text2);text-decoration:none;border-radius:3px;transition:.1s;font-size:12px}
.sidebar a:hover{background:var(--surface2);color:var(--text)}
.sidebar a.active{background:var(--surface2);color:var(--accent)}
.sidebar a.dir{font-weight:500;color:var(--text)}
.main{display:flex;flex-direction:column;overflow:hidden}

/* Chat */
.chat-container{display:flex;flex-direction:column;height:100%}
.chat-messages{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:12px}
.msg{max-width:85%;padding:10px 14px;border-radius:8px;font-size:13px;line-height:1.6;animation:fadeIn .2s}
.msg.user{background:rgba(0,188,212,0.12);align-self:flex-end;border:1px solid rgba(0,188,212,0.2)}
.msg.assistant{background:var(--surface2);align-self:flex-start;border:1px solid var(--border)}
.msg .label{font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--text2);margin-bottom:4px;font-weight:600}
.msg pre{background:var(--bg);padding:8px 12px;border-radius:4px;margin:6px 0;overflow-x:auto;font-size:12px;border:1px solid var(--border)}
.msg code{background:var(--bg);padding:1px 5px;border-radius:3px;font-size:12px;font-family:var(--mono)}
.msg p{margin:4px 0}
.msg p:first-child{margin-top:0}
.msg p:last-child{margin-bottom:0}
.msg strong{color:var(--accent)}
.chat-input-area{border-top:1px solid var(--border);padding:12px 16px;background:var(--surface)}
.chat-input-wrap{display:flex;gap:8px;align-items:center}
.chat-input-wrap input{flex:1;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:6px;font-family:var(--mono);font-size:13px;outline:none;transition:.15s}
.chat-input-wrap input:focus{border-color:var(--accent)}
.chat-input-wrap input::placeholder{color:var(--text2);font-size:12px}
.chat-input-wrap button{background:var(--accent);color:#000;border:none;padding:10px 18px;border-radius:6px;font-weight:600;font-size:13px;cursor:pointer;transition:.15s}
.chat-input-wrap button:hover{background:#00e5ff}
.chat-input-wrap button:disabled{opacity:.4;cursor:default}
.chat-typing{color:var(--text2);font-size:11px;padding:4px 0 0 4px;font-family:var(--mono);min-height:18px}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Code viewer */
.file-view{display:flex;flex-direction:column;height:100%}
.file-header{background:var(--surface2);padding:6px 16px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text2);font-family:var(--mono);display:flex;align-items:center;gap:10px}
.file-header .size{margin-left:auto}
.file-content{flex:1;overflow:auto}
.file-content pre{font-family:var(--mono);font-size:13px;line-height:1.6;padding:12px 16px;tab-size:4;color:var(--text)}
.file-content pre .ln{color:#3a3a4e;user-select:none;padding-right:16px;text-align:right;min-width:40px;display:inline-block}
.dir-list{padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:2px}
.dir-list a{display:flex;align-items:center;gap:6px;padding:4px 10px;font-size:13px;color:var(--text2);text-decoration:none;border-radius:4px}
.dir-list a:hover{background:var(--surface2);color:var(--text)}
.dir-list a .sz{margin-left:auto;font-size:11px;color:var(--text2)}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:var(--text2)}
.empty-state h2{font-size:22px;margin-bottom:6px;color:var(--text)}
.empty-state p{font-size:13px;max-width:400px;text-align:center;line-height:1.6}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
@media(max-width:768px){.container{grid-template-columns:1fr}.sidebar{display:none}}
</style>
</head>
<body>

<header>
  <h1 onclick="window.location='/opencode.php'" title="Home">Open<span>Code</span></h1>
  <nav>
    <a href="/opencode.php?tab=browse" class="<?= $tab === 'browse' ? 'active' : '' ?>">Browse</a>
    <a href="/opencode.php?tab=chat" class="<?= $tab === 'chat' ? 'active' : '' ?>">Chat</a>
    <a href="https://github.com/j68418730/suggaways" target="_blank">GitHub</a>
    <a href="/">« Site</a>
  </nav>
</header>

<?php if ($tab === 'browse'): ?>
<div class="breadcrumb">
  <a href="/opencode.php">repo</a>
  <?php if ($route): $parts = explode('/', $route); $acc = ''; ?>
    <span> / </span>
    <?php foreach ($parts as $i => $part): $acc .= ($acc?'/':'').$part; ?>
      <?php if ($i < count($parts)-1 || is_dir($fullPath)): ?>
        <a href="/opencode.php?file=<?= e($acc) ?>"><?= e($part) ?></a><?= $i < count($parts)-1 ? '<span> / </span>' : '' ?>
      <?php else: ?>
        <span><?= e($part) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="container">
  <div class="sidebar">
    <div class="section-title">Repository</div>
    <?php
    function renderSidebar(string $dir, string $prefix = ''): void {
        $items = array_diff(scandir($dir), ['.', '..', '.git']);
        $dirs = []; $files = [];
        foreach ($items as $item) {
            $full = $dir . '/' . $item; $rel = $prefix . $item;
            if (is_dir($full)) $dirs[] = $rel; else $files[] = $rel;
        }
        sort($dirs); sort($files);
        $currentFile = $_GET['file'] ?? '';
        foreach ($dirs as $d):
            $base = basename($d);
            $active = str_starts_with($currentFile, $d);
    ?>
        <a href="/opencode.php?file=<?= e($d) ?>" class="dir <?= $active ? 'active' : '' ?>">📁 <?= e($base) ?></a>
    <?php endforeach; ?>
    <?php foreach ($files as $f):
            $base = basename($f);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            $ic = match($ext){'php'=>'🐘','css'=>'🎹','js'=>'⚡','md'=>'📵','sql'=>'🗄️','json'=>'📋','yml'=>'⚙️','html'=>'🌐','env'=>'🔒','gitignore'=>'🙈',default=>'📄'};
            $active = $currentFile === $f;
    ?>
        <a href="/opencode.php?file=<?= e($f) ?>" class="file <?= $active ? 'active' : '' ?>"><?= $ic ?> <?= e($base) ?></a>
    <?php endforeach;
    }
    renderSidebar(REPO_PATH);
    ?>
  </div>

  <div class="main">
    <?php if ($tab === 'chat'): ?>
      <div class="chat-container" id="chatContainer">
        <div class="chat-messages" id="chatMessages">
          <div class="msg assistant">
            <div class="label">👾 OpenCode</div>
            <p>Hey! I'm your AI-powered SUGGAWAYZ dev assistant. I can read/write files, run git commands, and help with code.</p>
            <p>Try: <code>add a search bar to the header</code>, <code>ls</code>, <code>show me the cart code</code>, <code>git log --oneline -5</code></p>
          </div>
        </div>
        <div class="chat-input-area">
          <div class="chat-input-wrap">
            <input type="text" id="chatInput" placeholder="Type a command..." autofocus>
            <button id="chatSend" onclick="sendMessage()">Send</button>
          </div>
          <div class="chat-typing" id="chatTyping"></div>
        </div>
      </div>
    <?php elseif ($isFileView && $isTextFile): ?>
      <div class="file-view">
        <div class="file-header">
          <span><?= match($ext){'php'=>'🐘','css'=>'🎹','js'=>'⚡','md'=>'📵','sql'=>'🗄️','json'=>'📋','yml'=>'⚙️','html'=>'🌐',default=>'📄'} ?></span>
          <span><?= e(basename($fullPath)) ?></span>
          <span class="size"><?= number_format(filesize($fullPath)) ?> bytes · <?= $totalLines ?> lines</span>
        </div>
        <div class="file-content">
          <pre><?php
            $digits = strlen((string)$totalLines);
            foreach ($fileLines as $i => $line):
                $num = $i + 1;
          ?><span class="ln"><?= str_pad((string)$num, $digits) ?></span><?= e($line) ?><?= "\n" ?><?php endforeach; ?>
          </pre>
        </div>
      </div>
    <?php elseif ($route && file_exists($fullPath) && is_dir($fullPath)): ?>
      <div class="dir-list">
        <?php
        $items = array_diff(scandir($fullPath), ['.', '..', '.git']);
        $dirs = []; $files = [];
        foreach ($items as $item) {
            $full = $fullPath . '/' . $item;
            $rel = ($route ? $route . '/' : '') . $item;
            if (is_dir($full)) $dirs[] = ['n'=>$item,'r'=>$rel];
            else $files[] = ['n'=>$item,'r'=>$rel,'s'=>filesize($full)];
        }
        usort($dirs, fn($a,$b)=>strcasecmp($a['n'],$b['n']));
        usort($files, fn($a,$b)=>strcasecmp($a['n'],$b['n']));
        foreach ($dirs as $d): ?>
          <a href="/opencode.php?file=<?= e($d['r']) ?>"><span>📁</span><span><?= e($d['n']) ?>/</span></a>
        <?php endforeach; ?>
        <?php foreach ($files as $f):
            $ext = strtolower(pathinfo($f['n'], PATHINFO_EXTENSION));
            $ic = match($ext){'php'=>'🐘','css'=>'🎹','js'=>'⚡','md'=>'📵','sql'=>'🗄️','json'=>'📋','yml'=>'⚙️','html'=>'🌐','env'=>'🔒','gitignore'=>'🙈',default=>'📄'};
            $sz = $f['s'] > 1048576 ? number_format($f['s']/1048576,1).'MB' : ($f['s'] > 1024 ? number_format($f['s']/1024,1).'KB' : $f['s'].'B');
        ?>
          <a href="/opencode.php?file=<?= e($f['r']) ?>"><span><?= $ic ?></span><span><?= e($f['n']) ?></span><span class="sz"><?= $sz ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <h2>👾 OpenCode</h2>
        <p>Browse the SUGGAWAYZ source code or switch to <a href="/opencode.php?tab=chat" style="color:var(--accent)">Chat</a> for the AI assistant.</p>
        <div style="margin-top:20px;display:flex;gap:12px;font-size:13px;color:var(--text2)">
          <span><?php $c=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO_PATH,FilesystemIterator::SKIP_DOTS));foreach($it as $f) if($f->isFile())$c++;echo $c; ?> files</span>
          <span>•</span>
          <span><?php $s=0;$it2=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(REPO_PATH,FilesystemIterator::SKIP_DOTS));foreach($it2 as $f) if($f->isFile())$s+=$f->getSize();echo $s>1048576?number_format($s/1048576,1).'MB':number_format($s/1024,1).'KB'; ?></span>
          <span>•</span>
          <span><a href="https://github.com/j68418730/suggaways" style="color:var(--accent)" target="_blank">GitHub →</a></span>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const chatSend = document.getElementById('chatSend');
const chatTyping = document.getElementById('chatTyping');
let chatHistory = [];

chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

function addMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    const label = role === 'user' ? 'You' : '👾 OpenCode';
    div.innerHTML = '<div class="label">' + label + '</div>' + formatMessage(text);
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function formatMessage(text) {
    text = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    text = text.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/^- (.+)$/gm, '• $1');
    text = text.replace(/\n/g, '<br>');
    return text;
}

async function sendMessage() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    chatInput.value = '';
    addMessage('user', msg);
    chatSend.disabled = true;
    chatTyping.textContent = 'thinking...';
    
    try {
        const res = await fetch('/opencode.php?tab=chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'message=' + encodeURIComponent(msg) + '&history=' + encodeURIComponent(JSON.stringify(chatHistory))
        });
        const data = await res.json();
        const reply = data.reply || 'No response.';
        addMessage('assistant', reply);
        chatHistory.push({role: 'assistant', content: reply});
    } catch (e) {
        addMessage('assistant', 'Error: ' + e.message);
    }
    chatSend.disabled = false;
    chatTyping.textContent = '';
    chatInput.focus();
}
</script>
</body>
</html>
