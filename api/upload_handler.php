<?php
// upload_handler.php — Called from api.php
function handleUpload($body) {
    $filename = strtolower(basename($body['filename'] ?? ''));
    $filename = preg_replace('/[^a-z0-9.\-]/','-',$filename);
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext,['jpg','jpeg','png','webp','gif'])) error('Invalid file type');
    if (stripos($filename,'.php')!==false) error('Invalid filename');

    $raw  = $body['data'] ?? '';
    if (strpos($raw,',')!==false) $raw = substr($raw, strpos($raw,',')+1);
    $decoded = base64_decode($raw, true);
    if (!$decoded) error('Invalid image data');

    $mb = strlen($decoded)/(1024*1024);
    if ($mb > MAX_IMAGE_MB) error("Too large ({$mb}MB). Max ".MAX_IMAGE_MB."MB. Compress at tinypng.com");

    // Magic byte check
    $magic = substr($decoded,0,12);
    $ok = substr($magic,0,2)==="\xFF\xD8"
       || substr($magic,0,8)==="\x89PNG\r\n\x1a\n"
       || substr($magic,0,6)==='GIF87a'
       || substr($magic,0,6)==='GIF89a'
       || substr($magic,0,4)==='RIFF';
    if (!$ok) error('Not a valid image file. Possible security risk blocked.');

    $dir = IMAGES_PATH;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir.'.htaccess', "php_flag engine off\nOptions -ExecCGI\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>");
    }

    $path = $dir . $filename;
    if (file_put_contents($path, $decoded, LOCK_EX) !== false) {
        chmod($path, 0644);
        success(['filename'=>$filename,'url'=>IMAGES_URL.$filename,'size_kb'=>round(strlen($decoded)/1024,1)],'Uploaded');
    } else {
        error('Could not save image. Check images/ folder permissions.');
    }
}
?>
