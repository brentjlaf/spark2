<?php
// File: picker.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$base = rtrim(sanitize_text($_GET['base'] ?? ''), '/');
$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Image</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base . '/CMS/spark-cms.css'); ?>">
    <style>
        .picker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;padding:20px;}
        .picker-item{text-align:center;cursor:pointer;}
        .picker-item img{max-width:100%;height:80px;object-fit:cover;border-radius:4px;display:block;margin:0 auto 5px;}
    </style>
</head>
<body>
    <div class="picker-grid">
        <?php foreach ($media as $m): if (($m['type'] ?? '') !== 'images') continue; ?>
        <div class="picker-item" data-file="<?php echo htmlspecialchars($base . '/' . $m['file'], ENT_QUOTES); ?>">
            <img src="<?php echo htmlspecialchars($base . '/' . ($m['thumbnail'] ?: $m['file'])); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>">
            <div class="picker-name"><?php echo htmlspecialchars($m['name']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('click', function(e){
            var item = e.target.closest('.picker-item');
            if(item && window.opener && window.opener.__selectImageFromPicker){
                window.opener.__selectImageFromPicker(item.dataset.file);
                window.close();
            }
        });
    </script>
</body>
</html>
