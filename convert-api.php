<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
$outputDir = __DIR__ . '/gifs';
$tempDir = __DIR__ . '/temp';

// Create directories if they don't exist
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$url = $input['url'] ?? '';
$startTime = floatval($input['startTime'] ?? 0);
$endTime = floatval($input['endTime'] ?? 5);
$fps = intval($input['fps'] ?? 15);
$width = intval($input['width'] ?? 480);
$quality = $input['quality'] ?? 'medium';

// Validate YouTube URL
function extractVideoId($url) {
    $patterns = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/|youtube\.com\/shorts\/)([^&\n?#]+)/',
        '/^([a-zA-Z0-9_-]{11})$/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

$videoId = extractVideoId($url);
if (!$videoId) {
    echo json_encode(['success' => false, 'error' => 'Invalid YouTube URL']);
    exit;
}

// Validate parameters
$duration = $endTime - $startTime;
if ($duration <= 0 || $duration > 30) {
    echo json_encode(['success' => false, 'error' => 'Duration must be between 0 and 30 seconds']);
    exit;
}

// Check for required tools
$ytdlp = trim(shell_exec('which yt-dlp 2>/dev/null'));
$ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null'));

if (empty($ytdlp)) {
    echo json_encode([
        'success' => false, 
        'error' => 'yt-dlp is not installed. Install with: brew install yt-dlp'
    ]);
    exit;
}

if (empty($ffmpeg)) {
    echo json_encode([
        'success' => false, 
        'error' => 'ffmpeg is not installed. Install with: brew install ffmpeg'
    ]);
    exit;
}

// Generate unique filename
$uniqueId = uniqid('gif_', true);
$videoFile = $tempDir . '/' . $uniqueId . '.mp4';
$gifFile = $outputDir . '/' . $uniqueId . '.gif';
$paletteFile = $tempDir . '/' . $uniqueId . '_palette.png';

// Quality settings
$qualitySettings = [
    'low' => ['scale' => min($width, 320), 'colors' => 64],
    'medium' => ['scale' => min($width, 480), 'colors' => 128],
    'high' => ['scale' => min($width, 640), 'colors' => 256]
];

$settings = $qualitySettings[$quality] ?? $qualitySettings['medium'];

try {
    // Download video segment using yt-dlp
    $youtubeUrl = "https://www.youtube.com/watch?v={$videoId}";
    
    // Download the video (best quality mp4)
    $downloadCmd = sprintf(
        '%s -f "bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best" --download-sections "*%s-%s" -o %s %s 2>&1',
        escapeshellcmd($ytdlp),
        $startTime,
        $endTime,
        escapeshellarg($videoFile),
        escapeshellarg($youtubeUrl)
    );
    
    exec($downloadCmd, $downloadOutput, $downloadReturnCode);
    
    // If section download fails, try downloading full video and trimming
    if ($downloadReturnCode !== 0 || !file_exists($videoFile)) {
        // Try alternative: download full video first
        $fullVideoFile = $tempDir . '/' . $uniqueId . '_full.mp4';
        $downloadCmd = sprintf(
            '%s -f "bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[height<=720][ext=mp4]/best" -o %s %s 2>&1',
            escapeshellcmd($ytdlp),
            escapeshellarg($fullVideoFile),
            escapeshellarg($youtubeUrl)
        );
        
        exec($downloadCmd, $downloadOutput, $downloadReturnCode);
        
        if ($downloadReturnCode !== 0 || !file_exists($fullVideoFile)) {
            throw new Exception('Failed to download video: ' . implode("\n", $downloadOutput));
        }
        
        // Trim the video with ffmpeg
        $trimCmd = sprintf(
            '%s -y -ss %s -i %s -t %s -c copy %s 2>&1',
            escapeshellcmd($ffmpeg),
            $startTime,
            escapeshellarg($fullVideoFile),
            $duration,
            escapeshellarg($videoFile)
        );
        
        exec($trimCmd, $trimOutput, $trimReturnCode);
        
        // Clean up full video
        if (file_exists($fullVideoFile)) {
            unlink($fullVideoFile);
        }
        
        if (!file_exists($videoFile)) {
            throw new Exception('Failed to trim video');
        }
    }
    
    // Generate palette for better GIF quality
    $paletteCmd = sprintf(
        '%s -y -i %s -vf "fps=%d,scale=%d:-1:flags=lanczos,palettegen=max_colors=%d" %s 2>&1',
        escapeshellcmd($ffmpeg),
        escapeshellarg($videoFile),
        $fps,
        $settings['scale'],
        $settings['colors'],
        escapeshellarg($paletteFile)
    );
    
    exec($paletteCmd, $paletteOutput, $paletteReturnCode);
    
    if (!file_exists($paletteFile)) {
        throw new Exception('Failed to generate color palette');
    }
    
    // Convert to GIF using the palette
    $gifCmd = sprintf(
        '%s -y -i %s -i %s -lavfi "fps=%d,scale=%d:-1:flags=lanczos[x];[x][1:v]paletteuse=dither=bayer:bayer_scale=5" %s 2>&1',
        escapeshellcmd($ffmpeg),
        escapeshellarg($videoFile),
        escapeshellarg($paletteFile),
        $fps,
        $settings['scale'],
        escapeshellarg($gifFile)
    );
    
    exec($gifCmd, $gifOutput, $gifReturnCode);
    
    // Clean up temp files
    if (file_exists($videoFile)) {
        unlink($videoFile);
    }
    if (file_exists($paletteFile)) {
        unlink($paletteFile);
    }
    
    if (!file_exists($gifFile)) {
        throw new Exception('Failed to create GIF');
    }
    
    // Get file info
    $fileSize = filesize($gifFile);
    $gifUrl = 'gifs/' . basename($gifFile);
    
    echo json_encode([
        'success' => true,
        'gifUrl' => $gifUrl,
        'fileSize' => $fileSize,
        'fileSizeFormatted' => formatBytes($fileSize)
    ]);
    
} catch (Exception $e) {
    // Clean up on error
    foreach ([$videoFile, $paletteFile, $gifFile] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
