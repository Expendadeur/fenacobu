<?php
// ajax_actions_simple.php - Version simplifiée pour tester
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    require_once 'config.php';
    
    if (!isset($_POST['action'])) {
        throw new Exception('Action manquante');
    }
    
    $action = $_POST['action'];
    
    if ($action === 'add_agent') {
        // Validation basique
        $required = ['first_name', 'last_name', 'role', 'username', 'email', 'password'];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Champ requis manquant: $field");
            }
        }
        
        // Récupération des données
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $role = trim($_POST['role']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validation email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email invalide');
        }
        
        // Hash du mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Connexion DB
        $db = DatabaseConfig::getConnection();
        
        // Vérifier unicité
        $stmt = $db->prepare("SELECT COUNT(*) FROM agents WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username ou email déjà existant');
        }
        
        // Insertion
        $sql = "INSERT INTO agents (first_name, last_name, role, username, email, password, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = $db->prepare($sql);
        
        $result = $stmt->execute([$firstName, $lastName, $role, $username, $email, $hashedPassword]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Agent créé avec succès',
                'agent_id' => $db->lastInsertId()
            ]);
        } else {
            throw new Exception('Erreur insertion: ' . implode(', ', $stmt->errorInfo()));
        }
        
    } else {
        throw new Exception('Action non supportée: ' . $action);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>