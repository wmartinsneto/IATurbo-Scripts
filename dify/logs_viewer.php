<?php
/**
 * dify/logs_viewer.php
 *
 * Interface simples para visualizar logs das pastas:
 * - ./dify/logs
 * - ./speech/logs
 * - ./logs
 *
 * Exibe uma lista de arquivos de log e permite visualizar seu conteúdo.
 */

$log_dirs = [
    'Dify Logs' => __DIR__ . '/logs',
    'Speech Logs' => dirname(__DIR__) . '/speech/logs',
    'General Logs' => dirname(__DIR__) . '/logs',
];

$selected_dir = $_GET['dir'] ?? 'Dify Logs';
$selected_file = $_GET['file'] ?? null;

if (!isset($log_dirs[$selected_dir])) {
    $selected_dir = 'Dify Logs';
}

$files = [];
if (is_dir($log_dirs[$selected_dir])) {
    $files = array_filter(scandir($log_dirs[$selected_dir]), function($f) {
        return is_file($GLOBALS['log_dirs'][$GLOBALS['selected_dir']] . '/' . $f);
    });
    // Sort files descending by modified time
    usort($files, function($a, $b) use ($log_dirs, $selected_dir) {
        return filemtime($log_dirs[$selected_dir] . '/' . $b) <=> filemtime($log_dirs[$selected_dir] . '/' . $a);
    });
}

$log_content = '';
if ($selected_file && in_array($selected_file, $files)) {
    $path = $log_dirs[$selected_dir] . '/' . $selected_file;
    $log_content = htmlspecialchars(file_get_contents($path));
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Visualizador de Logs - Chatbot IATurbo</title>
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: #eee; margin: 0; padding: 0; }
        .container { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #222; padding: 1rem; overflow-y: auto; }
        .sidebar h2 { margin-top: 0; color: #43d9ea; }
        .sidebar select { width: 100%; margin-bottom: 1rem; }
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-list li { padding: 0.5rem; cursor: pointer; border-bottom: 1px solid #333; }
        .file-list li:hover, .file-list li.selected { background: #43d9ea; color: #121212; }
        .content { flex: 1; background: #0d0d0d; padding: 1rem; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 14px; }
        a { color: #43d9ea; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Pastas de Logs</h2>
            <form method="get" id="dirForm">
                <select name="dir" onchange="document.getElementById('dirForm').submit()">
                    <?php foreach ($log_dirs as $label => $path): ?>
                        <option value="<?php echo htmlspecialchars($label) ?>" <?php if ($label === $selected_dir) echo 'selected' ?>>
                            <?php echo htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <ul class="file-list">
                <?php foreach ($files as $file): ?>
                    <li class="<?php echo ($file === $selected_file) ? 'selected' : '' ?>">
                        <a href="?dir=<?php echo urlencode($selected_dir) ?>&file=<?php echo urlencode($file) ?>">
                            <?php echo htmlspecialchars($file) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($files)): ?>
                    <li><em>Nenhum arquivo de log encontrado.</em></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="content">
            <?php if ($log_content): ?>
                <h3>Conteúdo do arquivo: <?php echo htmlspecialchars($selected_file) ?></h3>
                <pre><?php echo $log_content ?></pre>
            <?php else: ?>
                <p>Selecione um arquivo de log para visualizar seu conteúdo.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
