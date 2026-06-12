<?php
echo "<h2>Upload Directory Test</h2>";

$uploadDir = __DIR__ . "/uploads/";

echo "<h3>Checking uploads directory:</h3>";
echo "Path: " . $uploadDir . "<br>";
echo "Exists: " . (file_exists($uploadDir) ? "Yes" : "No") . "<br>";

if (!file_exists($uploadDir)) {
    echo "Creating directory...<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "✓ Directory created successfully<br>";
    } else {
        echo "✗ Failed to create directory<br>";
    }
}

echo "Is writable: " . (is_writable($uploadDir) ? "Yes" : "No") . "<br>";

// Try to write a test file
$testFile = $uploadDir . "test.txt";
if (file_put_contents($testFile, "Test content")) {
    echo "✓ Successfully wrote test file<br>";
    unlink($testFile);
    echo "✓ Test file deleted<br>";
} else {
    echo "✗ Cannot write to directory<br>";
}

echo "<h3>PHP Upload Settings:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
?>

<form method="post" enctype="multipart/form-data" action="">
    <input type="file" name="test_file">
    <button type="submit" name="test_upload">Test Upload</button>
</form>

<?php
if (isset($_POST['test_upload']) && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Result:</h3>";
    echo "File name: " . $_FILES['test_file']['name'] . "<br>";
    echo "File size: " . $_FILES['test_file']['size'] . " bytes<br>";
    echo "Temp file: " . $_FILES['test_file']['tmp_name'] . "<br>";
    echo "Error code: " . $_FILES['test_file']['error'] . "<br>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $target = $uploadDir . $_FILES['test_file']['name'];
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $target)) {
            echo "✓ File uploaded successfully to: " . $target . "<br>";
            echo "<img src='uploads/" . $_FILES['test_file']['name'] . "' style='max-width:200px;'><br>";
        } else {
            echo "✗ Failed to move uploaded file<br>";
        }
    } else {
        echo "✗ Upload error<br>";
    }
}
?>
