<?php
// File: list_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);
$query = strtolower(sanitize_text($_GET['q'] ?? ''));
$folder = sanitize_text($_GET['folder'] ?? '');
$sort = strtolower(sanitize_text($_GET['sort'] ?? 'custom'));
$allowedSorts = ['custom','name','date','type','size','tags','dimensions'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'custom';
}
$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

usort($media, function($a,$b){
    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
});

$results = array_filter($media, function($item) use ($query, $folder) {
    if ($folder !== '' && $item['folder'] !== $folder) return false;
    if ($query && stripos($item['name'], $query) === false &&
        (!isset($item['tags']) || stripos(implode(',', $item['tags']), $query) === false)) return false;
    return true;
});

$root = dirname(__DIR__,2);
$default = $root.'/uploads/General';
if(!is_dir($default)) mkdir($default, 0777, true);
$folderDirs = array_filter(glob($root.'/uploads/*'), 'is_dir');
$folders = [];
foreach ($folderDirs as $dir) {
    $name = basename($dir);
    $thumb = null;
    foreach ($media as $m) {
        if (($m['folder'] ?? '') === $name && ($m['type'] ?? '') === 'images') {
            $thumb = $m['thumbnail'] ?: $m['file'];
            break;
        }
    }
    if (!$thumb) {
        $candidates = glob($dir . '/thumbs/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!$candidates) $candidates = glob($dir.'/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if ($candidates) {
            $thumb = str_replace($root.'/', '', $candidates[0]);
        }
    }
    $folders[] = ['name' => $name, 'thumbnail' => $thumb];
}

foreach ($results as &$item) {
    $path = $root . '/' . $item['file'];
    if (is_file($path)) {
        $item['modified_at'] = filemtime($path);
        if ($item['type'] === 'images') {
            $info = @getimagesize($path);
            if ($info) {
                $item['width'] = $info[0];
                $item['height'] = $info[1];
            }
        }
    }
}
unset($item);

switch ($sort) {
    case 'name':
        usort($results, function($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
        break;
    case 'date':
        usort($results, function($a, $b) {
            $aTime = $a['modified_at'] ?? ($a['uploaded_at'] ?? 0);
            $bTime = $b['modified_at'] ?? ($b['uploaded_at'] ?? 0);
            return $aTime <=> $bTime;
        });
        break;
    case 'type':
        usort($results, function($a, $b) {
            return strcasecmp($a['type'] ?? '', $b['type'] ?? '');
        });
        break;
    case 'size':
        usort($results, function($a, $b) {
            return ((int)($a['size'] ?? 0)) <=> ((int)($b['size'] ?? 0));
        });
        break;
    case 'tags':
        usort($results, function($a, $b) {
            $aTags = isset($a['tags']) ? implode(',', (array)$a['tags']) : '';
            $bTags = isset($b['tags']) ? implode(',', (array)$b['tags']) : '';
            return strcasecmp($aTags, $bTags);
        });
        break;
    case 'dimensions':
        usort($results, function($a, $b) {
            $aDim = ((int)($a['width'] ?? 0)) * ((int)($a['height'] ?? 0));
            $bDim = ((int)($b['width'] ?? 0)) * ((int)($b['height'] ?? 0));
            return $aDim <=> $bDim;
        });
        break;
    default:
        usort($results, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
}

if ($order === 'desc') {
    $results = array_reverse($results);
}

$results = array_values($results);
$totalCount = count($results);
$totalBytes = array_reduce($results, function($carry, $item) {
    return $carry + (int)($item['size'] ?? 0);
}, 0);
$lastModified = 0;
foreach ($results as $resultItem) {
    if (isset($resultItem['modified_at']) && $resultItem['modified_at'] > $lastModified) {
        $lastModified = $resultItem['modified_at'];
    }
}

$pagedResults = $results;
if ($limit > 0) {
    $pagedResults = array_slice($results, $offset, $limit);
} elseif ($offset > 0) {
    $pagedResults = array_slice($results, $offset);
}

header('Content-Type: application/json');
echo json_encode([
    'media' => array_values($pagedResults),
    'folders' => $folders,
    'total' => $totalCount,
    'total_size' => $totalBytes,
    'last_modified' => $lastModified
]);
?>
