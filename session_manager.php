<?php
// session_manager.php - Gestion des sessions sécurisées
require_once 'config.php';

class SessionManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
        $this->configureSession();
    }
    
    private function configureSession() {
        // Configuration sécurisée des sessions
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', 1800); // 30 minutes
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function isAuthenticated() {
        if (!isset($_SESSION['agent_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Vérifier l'expiration de la session (30 minutes d'inactivité)
        if (time() - $_SESSION['login_time'] > 1800) {
            $this->destroySession();
            return false;
        }
        
        // Mettre à jour le temps de dernière activité
        $_SESSION['login_time'] = time();
        
        // Vérifier la session en base
        return $this->validateSessionInDatabase();
    }
    
    private function validateSessionInDatabase() {
        $sessionId = session_id();
        $stmt = $this->db->prepare(
            "SELECT s.agent_id FROM sessions s 
             JOIN agents a ON s.agent_id = a.id 
             WHERE s.id = ? AND a.is_active = 1"
        );
        $stmt->execute([$sessionId]);
        
        if ($stmt->fetch()) {
            // Mettre à jour la dernière activité
            $stmt = $this->db->prepare(
                "UPDATE sessions SET last_activity = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $stmt->execute([$sessionId]);
            return true;
        }
        
        return false;
    }
    
    public function requireRole($allowedRoles) {
        if (!$this->isAuthenticated()) {
            header('HTTP/1.1 401 Unauthorized');
            header('Location: index.html');
            exit;
        }
        
        if (!in_array($_SESSION['role'], (array)$allowedRoles)) {
            header('HTTP/1.1 403 Forbidden');
            echo "Accès refusé";
            exit;
        }
    }
    
    public function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Supprimer la session de la base
            if (isset($_SESSION['agent_id'])) {
                $sessionId = session_id();
                $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
            }
            
            // Détruire la session PHP
            session_unset();
            session_destroy();
            
            // Supprimer les cookies
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }
        }
    }
    
    public function cleanupOldSessions() {
        // Nettoyer les sessions expirées (plus de 24 heures)
        $stmt = $this->db->prepare(
            "DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute();
        
        // Nettoyer les tentatives de connexion anciennes (plus de 7 jours)
        $stmt = $this->db->prepare(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stmt->execute();
    }
}

?>