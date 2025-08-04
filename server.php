<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
$dataFile = __DIR__ . '/data/photos.json';
$dataDir = dirname($dataFile);

// Create data directory if it doesn't exist
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create data directory']);
        exit();
    }
}

// Handle POST request to save photos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit();
        }
        
        // Validate required fields
        if (!isset($data['photos']) || !isset($data['nextId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit();
        }
        
        // Add timestamp
        $data['lastModified'] = time() * 1000; // JavaScript timestamp (milliseconds)
        $data['serverSaved'] = date('Y-m-d H:i:s');
        
        // Create backup of existing data
        if (file_exists($dataFile)) {
            $backupFile = $dataDir . '/photos_backup_' . date('Y-m-d_H-i-s') . '.json';
            copy($dataFile, $backupFile);
            
            // Keep only last 10 backups
            $backups = glob($dataDir . '/photos_backup_*.json');
            if (count($backups) > 10) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                $toDelete = array_slice($backups, 0, count($backups) - 10);
                foreach ($toDelete as $oldBackup) {
                    unlink($oldBackup);
                }
            }
        }
        
        // Save data to file
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($dataFile, $jsonData, LOCK_EX) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save data']);
            exit();
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Photos saved successfully',
            'timestamp' => $data['lastModified'],
            'totalPhotos' => array_sum(array_map('count', $data['photos']))
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle GET request to load photos
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!file_exists($dataFile)) {
            // Return empty structure if no data file exists
            $defaultData = [
                'photos' => [
                    'ice-breaking' => [],
                    'culturelle' => [],
                    'hackathon' => [],
                    'imlil' => [],
                    'friends' => []
                ],
                'nextId' => 1,
                'lastModified' => time() * 1000,
                'serverSaved' => date('Y-m-d H:i:s')
            ];
            echo json_encode($defaultData);
            exit();
        }
        
        $jsonData = file_get_contents($dataFile);
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            echo json_encode(['error' => 'Corrupted data file']);
            exit();
        }
        
        // Add cache control headers to prevent caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($data);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>