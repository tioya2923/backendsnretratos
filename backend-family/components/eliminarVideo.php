<?php
require '../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$bucketName = 'familia-gouveia';
$IAM_KEY = getenv('AWS_IAM_KEY');
$IAM_SECRET = getenv('AWS_IAM_SECRET');

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'credentials' => [
        'key'    => $IAM_KEY,
        'secret' => $IAM_SECRET,
    ],
]);

$stmt = $conn->prepare("SELECT * FROM videos");
$stmt->execute();
$result = $stmt->get_result();
$videos = array();

while ($row = $result->fetch_assoc()) {
    $row['video'] = $s3->getObjectUrl($bucketName, $row['video']);
    $videos[] = $row;
}

echo json_encode($videos);

$stmt->close();
$conn->close();
?>
