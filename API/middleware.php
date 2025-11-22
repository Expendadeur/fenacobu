// ====== API/middleware.php ======
<?php
// Middleware pour l'authentification et les permissions

require_once '../config.php';

class AuthMiddleware {
    private static $publicRoutes = [
        'auth.php?action=login',
        'auth.php?action=check'
    ];
    
    public static function requireAuth() {
        if (!isset($_SESSION['agent_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentification requise'
            ]);
            exit;
        }
    }
    
    public static function requireRole($roles) {
        self::requireAuth();
        
        $userRole = $_SESSION['role'] ?? '';
        if (!in_array($userRole, (array)$roles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Accès refusé'
            ]);
            exit;
        }
    }
    
    public static function validateCSRF($token) {
        if (!SecurityManager::validateCSRF($token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'CSRF token invalide'
            ]);
            exit;
        }
    }
    
    public static function logAction($action, $details) {
        $db = DatabaseConfig::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO log_operations (table_name, operation_type, record_id, id_agent, new_values, timestamp)
            VALUES (?, 'ACTION', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'action_' . str_replace('/', '_', $action),
            0,
            $_SESSION['agent_id'] ?? null,
            json_encode($details)
        ]);
    }
    
    public static function setSecurityHeaders() {
        SecurityManager::setSecurityHeaders();
    }
}

// Middleware pour la validation des données
class ValidateMiddleware {
    public static function validateMontant($montant) {
        if (!is_numeric($montant) || $montant <= 0) {
            throw new Exception('Montant invalide');
        }
        return floatval($montant);
    }
    
    public static function validateCompte($compte) {
        if (empty($compte)) {
            throw new Exception('Numéro de compte requis');
        }
        
        $regex = '/^FR[0-9]{2}[0-9]{10}[0-9A-Z]{11}[0-9]{2}$/';
        if (!preg_match($regex, $compte)) {
            throw new Exception('Format de compte invalide');
        }
        
        return $compte;
    }
    
    public static function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new Exception('Format de date invalide (Y-m-d requis)');
        }
        return $date;
    }
    
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return SecurityManager::sanitizeInput($input);
    }
}

?>
