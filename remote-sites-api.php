<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$db = dirname(__DIR__) . '/data/zerro_blog.db';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создаем таблицу для хранения сайтов
$pdo->exec("CREATE TABLE IF NOT EXISTS remote_sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT NOT NULL,
    token TEXT NOT NULL,
    status TEXT DEFAULT 'offline',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'checkConnection':
        checkConnection();
        break;
        
    case 'addSite':
        addSite($pdo);
        break;
        
    case 'getSites':
        getSites($pdo);
        break;
        
    case 'removeSite':
        removeSite($pdo);
        break;
        
    case 'search':
        searchOnRemoteSite();
        break;
        
    case 'replace':
        replaceOnRemoteSite();
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

function checkConnection() {
    $domain = $_POST['domain'] ?? '';
    $token = $_POST['token'] ?? '';
    
    $url = rtrim($domain, '/') . '/remote-api.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'ping',
        'token' => $token
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && $data['ok'] === true) {
            echo json_encode(['ok' => true]);
            return;
        }
    }
    
    echo json_encode(['ok' => false, 'error' => 'Connection failed']);
}

function addSite($pdo) {
    $domain = $_POST['domain'] ?? '';
    $token = $_POST['token'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO remote_sites (domain, token, status) VALUES (?, ?, 'online')");
    $stmt->execute([$domain, $token]);
    
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

function getSites($pdo) {
    $sites = $pdo->query("SELECT * FROM remote_sites ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'sites' => $sites]);
}

function removeSite($pdo) {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM remote_sites WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
}

function searchOnRemoteSite() {
    $domain = $_POST['domain'] ?? '';
    $token = $_POST['token'] ?? '';
    $type = $_POST['type'] ?? 'files';
    $query = $_POST['query'] ?? '';
    
    $url = rtrim($domain, '/') . '/remote-api.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'search',
        'token' => $token,
        'type' => $type,
        'query' => $query
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && $data['ok']) {
        echo json_encode($data);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Search failed']);
    }
}

function replaceOnRemoteSite() {
    $domain = $_POST['domain'] ?? '';
    $token = $_POST['token'] ?? '';
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    
    $url = rtrim($domain, '/') . '/remote-api.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'replace',
        'token' => $token,
        'from' => $from,
        'to' => $to
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    echo json_encode($data ?: ['ok' => false]);
}