<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTMiddleware {
    private static $secretKey;
    
    public static function initialize($secretKey) {
        self::$secretKey = $secretKey;
    }
    
    public static function generateToken($userId, $email, $role) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 24 * 60 * 60;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'userId' => $userId,
            'email' => $email,
            'role' => $role
        ];
        
        return JWT::encode($payload, self::$secretKey, 'HS256');
    }
    
    public static function validateToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }
        
        $jwt = $matches[1];
        
        try {
            $decoded = JWT::decode($jwt, new Key(self::$secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
    }
}