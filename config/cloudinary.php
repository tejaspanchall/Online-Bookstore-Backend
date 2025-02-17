<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$required_vars = [
    'CLOUDINARY_CLOUD_NAME',
    'CLOUDINARY_API_KEY',
    'CLOUDINARY_API_SECRET'
];

foreach ($required_vars as $var) {
    if (!isset($_ENV[$var])) {
        throw new RuntimeException("Missing required environment variable: {$var}");
    }
}

try {
    $config = new Configuration([
        'cloud' => [
            'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
            'api_key' => $_ENV['CLOUDINARY_API_KEY'],
            'api_secret' => $_ENV['CLOUDINARY_API_SECRET']
        ],
        'url' => [
            'secure' => true
        ]
    ]);

    $cloudinary = new Cloudinary($config);

} catch (Exception $e) {
    error_log('Cloudinary configuration error: ' . $e->getMessage());
    throw new RuntimeException('Failed to initialize Cloudinary: ' . $e->getMessage());
}

return $cloudinary;