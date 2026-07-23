<?php
$dirs = ["user", "admin"];
foreach ($dirs as $dir) {
    $files = glob(__DIR__ . "/$dir/*.php");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $pattern = "/<nav class=\"top-nav\">.*?<\/nav>/s";
        $replacement = "<?php include __DIR__ . '/../includes/top_nav.php'; ?>";
        if (preg_match($pattern, $content)) {
            $new_content = preg_replace($pattern, $replacement, $content);
            file_put_contents($file, $new_content);
            echo "Updated $file\n";
        }
    }
}
?>
