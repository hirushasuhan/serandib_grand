<?php
declare(strict_types=1);

/**
 * Wasmer / PHP CLI Built-in Web Server Router
 * 
 * Provides routing and access control equivalent to Apache .htaccess
 * when running inside Wasmer Edge or via `php -S`.
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($rawUri, PHP_URL_PATH) ?: '/';
$uriPath = rawurldecode($uriPath);

// Prevent path traversal
if (str_contains($uriPath, '..')) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

// Block access to sensitive directories, hidden files, and internal file types
$blockedDirectories = ['config', 'src', 'storage', 'database', 'docs', 'includes'];
$blockedExtensions = ['sql', 'log', 'md', 'ini', 'sample', 'bak', 'old', 'dist', 'yml', 'yaml', 'toml', 'docx', 'lock'];

$patternDirs = '#^/(' . implode('|', $blockedDirectories) . ')(/|$)#i';
$patternExts = '#\.(' . implode('|', $blockedExtensions) . ')$#i';
$isDotFile = preg_match('#(^|/)\.#', $uriPath);

if ($isDotFile || preg_match($patternDirs, $uriPath) || preg_match($patternExts, $uriPath)) {
    http_response_code(403);
    if (file_exists(__DIR__ . '/403.php')) {
        require __DIR__ . '/403.php';
    } else {
        echo '<h1>403 Forbidden</h1><p>Access denied.</p>';
    }
    exit;
}

$target = __DIR__ . $uriPath;

// If targeting a directory, look for index.php
if (is_dir($target)) {
    $indexPath = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($indexPath)) {
        $_SERVER['SCRIPT_FILENAME'] = $indexPath;
        $_SERVER['SCRIPT_NAME']     = rtrim($uriPath, '/') . '/index.php';
        $_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'];
        require $indexPath;
        exit;
    }
}

// If targeting an existing file
if (is_file($target)) {
    // If it's a PHP file, execute it
    if (str_ends_with($target, '.php')) {
        $_SERVER['SCRIPT_FILENAME'] = $target;
        $_SERVER['SCRIPT_NAME']     = $uriPath;
        $_SERVER['PHP_SELF']        = $uriPath;
        require $target;
        exit;
    }

    // Static asset (CSS, JS, image, font, media) -> return false so the built-in server serves it
    return false;
}

// Not found
http_response_code(404);
if (file_exists(__DIR__ . '/404.php')) {
    require __DIR__ . '/404.php';
} else {
    echo '<h1>404 Not Found</h1>';
}
exit;
