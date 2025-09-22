<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Конфигурация токена (генерируется при экспорте)
define('API_TOKEN', '{{GENERATED_TOKEN}}');

// Проверка токена
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if ($token !== API_TOKEN) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'ping':
        echo json_encode(['ok' => true, 'message' => 'Connection successful']);
        break;
        
    case 'search':
        searchContent();
        break;
        
    case 'replace':
        replaceContent();
        break;
        
    case 'upload':
        uploadFile();
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

function searchContent() {
    $type = $_POST['type'] ?? 'files';
    $query = $_POST['query'] ?? '';
    
    $results = [];
    $found = [];
    
    // Сканируем HTML файлы
    $files = glob('*.html');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $pageName = str_replace('.html', '', basename($file));
        if ($pageName === 'index') $pageName = 'Главная';
        
        if ($type === 'files') {
            // Ищем ТОЛЬКО в кнопках-файлах (filebtn)
            // Паттерн для поиска элементов filebtn
            preg_match_all('/<div[^>]+class="el filebtn"[^>]*>.*?<a[^>]+href="([^"]+)"[^>]*download="([^"]*)".*?>(.*?)<\/a>.*?<\/div>/s', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $fileUrl = $match[1];
                $fileName = $match[2] ?: basename($fileUrl);
                $buttonText = strip_tags($match[3]);
                
                // Фильтруем по запросу
                if (empty($query) || 
                    stripos($fileUrl, $query) !== false || 
                    stripos($fileName, $query) !== false ||
                    stripos($buttonText, $query) !== false) {
                    
                    $key = $fileUrl;
                    if (!isset($found[$key])) {
                        $found[$key] = [
                            'url' => $fileUrl,
                            'name' => $fileName,
                            'text' => $buttonText,
                            'pages' => []
                        ];
                    }
                    $found[$key]['pages'][] = $pageName;
                }
            }
        } else {
            // Ищем ТОЛЬКО в кнопках-ссылках (linkbtn)
            // Паттерн для поиска элементов linkbtn
            preg_match_all('/<div[^>]+class="el linkbtn"[^>]*>.*?<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>.*?<\/div>/s', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $linkUrl = $match[1];
                $buttonText = strip_tags($match[2]);
                
                // Фильтруем по запросу
                if (empty($query) || 
                    stripos($linkUrl, $query) !== false || 
                    stripos($buttonText, $query) !== false) {
                    
                    $key = $linkUrl;
                    if (!isset($found[$key])) {
                        $found[$key] = [
                            'url' => $linkUrl,
                            'text' => $buttonText,
                            'pages' => []
                        ];
                    }
                    $found[$key]['pages'][] = $pageName;
                }
            }
        }
    }
    
    // Преобразуем в массив для вывода
    foreach ($found as $item) {
        $results[] = $item;
    }
    
    echo json_encode(['ok' => true, 'results' => $results]);
}

function replaceContent() {
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    $type = $_POST['type'] ?? 'auto'; // auto, files, links
    
    if (!$from) {
        echo json_encode(['ok' => false, 'error' => 'Empty search string']);
        return;
    }
    
    $replaced = 0;
    $affectedFiles = [];
    
    // Обновляем все HTML файлы
    $files = glob('*.html');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $originalContent = $content;
        $fileReplaced = 0;
        
        if ($type === 'files' || $type === 'auto') {
            // Заменяем в кнопках-файлах
            $content = preg_replace_callback(
                '/(<div[^>]+class="el filebtn"[^>]*>.*?<a[^>]+href=")([^"]+)("[^>]*>)/s',
                function($matches) use ($from, $to, &$fileReplaced) {
                    if ($matches[2] === $from || stripos($matches[2], $from) !== false) {
                        $fileReplaced++;
                        $newUrl = ($matches[2] === $from) ? $to : str_ireplace($from, $to, $matches[2]);
                        return $matches[1] . $newUrl . $matches[3];
                    }
                    return $matches[0];
                },
                $content
            );
        }
        
        if ($type === 'links' || $type === 'auto') {
            // Заменяем в кнопках-ссылках
            $content = preg_replace_callback(
                '/(<div[^>]+class="el linkbtn"[^>]*>.*?<a[^>]+href=")([^"]+)("[^>]*>)/s',
                function($matches) use ($from, $to, &$fileReplaced) {
                    if ($matches[2] === $from || stripos($matches[2], $from) !== false) {
                        $fileReplaced++;
                        $newUrl = ($matches[2] === $from) ? $to : str_ireplace($from, $to, $matches[2]);
                        return $matches[1] . $newUrl . $matches[3];
                    }
                    return $matches[0];
                },
                $content
            );
        }
        
        if ($fileReplaced > 0) {
            file_put_contents($file, $content);
            $replaced += $fileReplaced;
            $affectedFiles[] = str_replace('.html', '', basename($file));
        }
    }
    
    echo json_encode([
        'ok' => true, 
        'replaced' => $replaced,
        'pages' => $affectedFiles
    ]);
}

function uploadFile() {
    if (empty($_FILES['file'])) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        return;
    }
    
    $uploadDir = 'assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = basename($_FILES['file']['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        // Обновляем все ссылки на этот файл
        $oldUrl = $_POST['oldUrl'] ?? '';
        if ($oldUrl) {
            $files = glob('*.html');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $content = str_replace($oldUrl, $targetPath, $content);
                file_put_contents($file, $content);
            }
        }
        
        echo json_encode(['ok' => true, 'url' => $targetPath]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Upload failed']);
    }
}