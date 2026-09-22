<?php
$conn = new mysqli('localhost', 'root', '', 'footstepszs');
if($conn->connect_error) die('err');
$conn->query("ALTER TABLE documents MODIFY COLUMN jenis ENUM('Upload', 'Generated', 'Template', 'Link') DEFAULT 'Upload'");
echo 'DB Altered';
