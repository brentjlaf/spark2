<?php
// File: upload_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$allowed = [
    'images' => ['jpg','jpeg','png','gif','webp','svg'],
    'videos' => ['mp4','m4v','webm','mov'],
    'audio' => ['mp3'],
    'documents' => ['pdf','doc','docx','txt','xlsx','csv']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = sanitize_text($_POST['folder'] ?? '');
    $tags = sanitize_tags(explode(',', $_POST['tags'] ?? ''));
    $optimizeImages = true;
    if(isset($_POST['optimize_images'])) {
        $value = strtolower((string)$_POST['optimize_images']);
        $optimizeImages = !in_array($value, ['0', 'false', 'no'], true);
    }
    $root = dirname(__DIR__, 2);
    $baseDir = $root . '/uploads';
    if ($folder) {
        $baseDir .= '/' . basename($folder);
    }
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }

    $mediaFile = $root . '/data/media.json';
    $media = read_json_file($mediaFile);

    $maxOrder = -1;
    foreach ($media as $m) {
        if (isset($m['order']) && $m['order'] > $maxOrder) $maxOrder = $m['order'];
    }
    $order = $maxOrder + 1;

    header('Content-Type: application/json');

    if (!isset($_FILES['files'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No files uploaded.'
        ]);
        exit;
    }

    $errors = [];
    $newEntries = [];

    foreach ($_FILES['files']['name'] as $i => $name) {
        $originalName = $name;
        $displayName = sanitize_text($originalName) ?: $originalName;
        $errorCode = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = upload_error_message($displayName, $errorCode);
            continue;
        }

        $tmp = $_FILES['files']['tmp_name'][$i];
        $size = $_FILES['files']['size'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $category = null;
        foreach ($allowed as $cat => $exts) {
            if (in_array($ext, $exts)) { $category = $cat; break; }
        }
        if (!$category) {
            $errors[] = $displayName . ' is not a supported file type.';
            continue;
        }

        if (!is_uploaded_file($tmp)) {
            $errors[] = 'Could not validate the upload for ' . $displayName . '.';
            continue;
        }

        $safe = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $dest = $baseDir . '/' . $safe;
        if (!move_uploaded_file($tmp, $dest)) {
            $errors[] = 'Failed to save ' . $displayName . '.';
            continue;
        }

        if ($category === 'images' && $optimizeImages) {
            optimize_image($dest, 15 * 1024 * 1024);
            clearstatcache(true, $dest);
            $optimizedSize = @filesize($dest);
            if ($optimizedSize !== false) {
                $size = $optimizedSize;
            }
        }

        // Feature 9: Convert JPEG/PNG to WebP for better performance
        if ($category === 'images') {
            $newDest = convert_to_webp($dest);
            if ($newDest !== null) {
                $dest = $newDest;
                $safe = basename($dest);
                clearstatcache(true, $dest);
                $convertedSize = @filesize($dest);
                if ($convertedSize !== false) {
                    $size = $convertedSize;
                }
            }
        }

        $thumbPath = null;
        $responsiveSizes = [];
        if ($category === 'images') {
            $thumbDir = $baseDir . '/thumbs';
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
            $thumbPath = $thumbDir . '/' . $safe;
            create_thumbnail($dest, $thumbPath, 200);
            if (file_exists($thumbPath)) {
                $thumbPath = str_replace($root . '/', '', $thumbPath);
            } else {
                $thumbPath = null;
            }
            // Feature 10: Generate responsive size variants (480w, 800w, 1200w)
            $responsiveSizes = generate_responsive_sizes($dest, $thumbDir, $safe, $root);
        }

        $newEntries[] = [
            'id' => uniqid(),
            'name' => $originalName,
            'file' => str_replace($root . '/', '', $dest),
            'folder' => $folder,
            'size' => $size,
            'type' => $category,
            'uploaded_at' => time(),
            'thumbnail' => $thumbPath,
            'tags' => $tags,
            'order' => $order++,
            'sizes' => $responsiveSizes,
        ];
    }

    if (!empty($newEntries)) {
        $media = array_merge($media, $newEntries);
        write_json_file($mediaFile, $media);
    }

    $response = [
        'status' => !empty($newEntries) ? 'success' : 'error',
        'uploaded' => count($newEntries)
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
        if (!empty($newEntries)) {
            $response['partial'] = true;
            $response['message'] = 'Some files could not be uploaded.';
        } else {
            $response['message'] = 'No files were uploaded.';
        }
    } elseif (empty($newEntries)) {
        $response['message'] = 'No files to upload.';
    }

    echo json_encode($response);
    exit;
}

function optimize_image(string $path, int $maxSizeBytes = 15728640): void {
    if (!file_exists($path)) {
        return;
    }

    clearstatcache(true, $path);
    $currentSize = @filesize($path);
    if ($currentSize === false || $currentSize <= $maxSizeBytes) {
        return;
    }

    $info = @getimagesize($path);
    if (!$info) {
        return;
    }

    [$width, $height, $type] = $info;

    $createMap = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    $saveMap = [
        IMAGETYPE_JPEG => 'imagejpeg',
        IMAGETYPE_PNG  => 'imagepng',
        IMAGETYPE_WEBP => 'imagewebp',
    ];

    if (!isset($createMap[$type], $saveMap[$type])) {
        return;
    }

    $createFn = $createMap[$type];
    $saveFn = $saveMap[$type];

    if (!function_exists($createFn) || !function_exists($saveFn)) {
        return;
    }

    $image = @$createFn($path);
    if (!$image) {
        return;
    }

    $currentImage = $image;
    $currentWidth = $width;
    $currentHeight = $height;
    $currentSize = @filesize($path) ?: $currentSize;

    $quality = 85;
    $compression = 6;
    $maxIterations = 12;
    $iteration = 0;
    $tmpPath = $path . '.tmp';

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($currentImage, false);
        imagesavealpha($currentImage, true);
    }

    while ($iteration < $maxIterations && $currentSize > $maxSizeBytes) {
        $iteration++;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $saveFn($currentImage, $tmpPath, max(10, $quality));
                break;
            case IMAGETYPE_WEBP:
                $saveFn($currentImage, $tmpPath, max(10, $quality));
                break;
            case IMAGETYPE_PNG:
                $saveFn($currentImage, $tmpPath, min(9, max(0, $compression)));
                break;
        }

        if (!file_exists($tmpPath)) {
            break;
        }

        clearstatcache(true, $tmpPath);
        $newSize = @filesize($tmpPath);
        if ($newSize !== false && ($newSize < $currentSize || $newSize <= $maxSizeBytes)) {
            @rename($tmpPath, $path);
            $currentSize = $newSize;
        } else {
            @unlink($tmpPath);
        }

        if ($currentSize <= $maxSizeBytes) {
            break;
        }

        if (($type === IMAGETYPE_JPEG || $type === IMAGETYPE_WEBP) && $quality > 50) {
            $quality -= 5;
            continue;
        }

        if ($type === IMAGETYPE_PNG && $compression < 9) {
            $compression++;
            continue;
        }

        $newWidth = (int) round($currentWidth * 0.9);
        $newHeight = (int) round($currentHeight * 0.9);

        if ($newWidth < 1 || $newHeight < 1) {
            break;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled(
            $resized,
            $currentImage,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $currentWidth,
            $currentHeight
        );

        if ($currentImage !== $image) {
            imagedestroy($currentImage);
        }

        $currentImage = $resized;
        $currentWidth = $newWidth;
        $currentHeight = $newHeight;
    }

    if (file_exists($tmpPath)) {
        @unlink($tmpPath);
    }

    if ($currentImage !== $image) {
        imagedestroy($currentImage);
    }

    imagedestroy($image);
}

function create_thumbnail($src, $dest, $maxWidth) {
    $info = @getimagesize($src);
    if (!$info) return;
    list($width, $height, $type) = $info;
    $ratio = $maxWidth / $width;
    $newW = $maxWidth;
    $newH = (int)($height * $ratio);
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return;
    }
    if (!$img) return;
    $thumb = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($thumb, $img, 0,0,0,0,$newW,$newH,$width,$height);
    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($thumb, $dest, 80); break;
        case IMAGETYPE_PNG:  imagepng($thumb, $dest); break;
        case IMAGETYPE_GIF:  imagegif($thumb, $dest); break;
        case IMAGETYPE_WEBP: imagewebp($thumb, $dest); break;
    }
    imagedestroy($img);
    imagedestroy($thumb);
}

/**
 * Convert a JPEG or PNG file to WebP format in-place.
 * Deletes the original file on success and returns the new .webp path.
 * Returns null if the file is not JPEG/PNG, GD is unavailable, or conversion fails.
 */
function convert_to_webp(string $path): ?string {
    if (!file_exists($path)) {
        return null;
    }
    $info = @getimagesize($path);
    if (!$info) {
        return null;
    }
    $type = $info[2];
    // Only convert JPEG and PNG; skip GIF (may be animated), WebP (already), SVG (vector)
    if ($type !== IMAGETYPE_JPEG && $type !== IMAGETYPE_PNG) {
        return null;
    }
    if (!function_exists('imagewebp')) {
        return null;
    }
    if ($type === IMAGETYPE_JPEG) {
        if (!function_exists('imagecreatefromjpeg')) {
            return null;
        }
        $img = @imagecreatefromjpeg($path);
    } else {
        if (!function_exists('imagecreatefrompng')) {
            return null;
        }
        $img = @imagecreatefrompng($path);
        if ($img) {
            // Preserve PNG transparency
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
    }
    if (!$img) {
        return null;
    }
    // Replace extension with .webp
    $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    if ($webpPath === $path) {
        // Extension was not replaced — unexpected, bail out
        imagedestroy($img);
        return null;
    }
    if (!imagewebp($img, $webpPath, 85)) {
        imagedestroy($img);
        return null;
    }
    imagedestroy($img);
    @unlink($path);
    return $webpPath;
}

/**
 * Generate responsive WebP variants (480w, 800w, 1200w) in $thumbDir.
 * Skips breakpoints that are wider than the original image.
 * Returns an associative array of width => relative path (relative to $root).
 */
function generate_responsive_sizes(string $src, string $thumbDir, string $baseName, string $root): array {
    $info = @getimagesize($src);
    if (!$info) {
        return [];
    }
    [$origWidth] = $info;
    $breakpoints = [480, 800, 1200];
    $result = [];
    foreach ($breakpoints as $w) {
        if ($origWidth <= $w) {
            // Original is smaller than or equal to this breakpoint — skip
            continue;
        }
        $destPath = $thumbDir . '/' . $w . 'w_' . $baseName;
        create_thumbnail($src, $destPath, $w);
        if (file_exists($destPath)) {
            $result[$w] = str_replace($root . '/', '', $destPath);
        }
    }
    return $result;
}

function upload_error_message(string $name, int $code): string {
    $limit = get_php_upload_limit();
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $limitText = $limit ? format_bytes($limit) : 'the server limit';
            return $name . ' exceeds the maximum upload size of ' . $limitText . '.';
        case UPLOAD_ERR_PARTIAL:
            return $name . ' was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded for ' . $name . '.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder for ' . $name . '. Contact support.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write ' . $name . ' to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension stopped the upload of ' . $name . '.';
        default:
            return 'An unknown error occurred while uploading ' . $name . '.';
    }
}

function get_php_upload_limit(): ?int {
    $limits = [ini_get('upload_max_filesize'), ini_get('post_max_size')];
    $bytes = array_filter(array_map('convert_shorthand_to_bytes', $limits));
    if (empty($bytes)) {
        return null;
    }
    return (int) min($bytes);
}

function convert_shorthand_to_bytes($value): ?int {
    if (!$value) return null;
    $value = trim($value);
    if ($value === '') return null;
    $last = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($last) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
            break;
    }
    return (int) round($number);
}

function format_bytes(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $value = $bytes / pow(1024, $i);
    return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$i];
}
