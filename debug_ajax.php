<?php
// debug_ajax.php - Fichier de test pour diagnostiquer les erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de diagnostic</h1>";

try {
    echo "<p>1. Test de require_once config.php...</p>";
    require_once 'config.php';
    echo "<p>✓ config.php chargé avec succès</p>";
    
    echo "<p>2. Test de connexion à la base de données...</p>";
    $db = DatabaseConfig::getConnection();
    echo "<p>✓ Connexion à la base réussie</p>";
    
    echo "<p>3. Test de la structure de la table agents...</p>";
    $stmt = $db->query("DESCRIBE agents");
    $columns = $stmt->fetchAll();
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<p>4. Test de SecurityManager...</p>";
    $testToken = SecurityManager::generateSecureToken();
    echo "<p>✓ Token généré: " . substr($testToken, 0, 10) . "...</p>";
    
    $testPassword = SecurityManager::hashPassword('test123');
    echo "<p>✓ Hash de mot de passe généré</p>";
    
    echo "<p>5. Test d'insertion simple...</p>";
    $stmt = $db->prepare("INSERT INTO agents (first_name, last_name, role, username, email, password, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $testResult = $stmt->execute(['Test', 'Debug', 'Caissier', 'debug_test_' . time(), 'debug@test.com', $testPassword]);
    
    if ($testResult) {
        $newId = $db->lastInsertId();
        echo "<p>✓ Insertion test réussie, ID: " . $newId . "</p>";
        
        // Nettoyer le test
        $stmt = $db->prepare("DELETE FROM agents WHERE id_agents = ?");
        $stmt->execute([$newId]);
        echo "<p>✓ Test nettoyé</p>";
    } else {
        echo "<p>✗ Erreur d'insertion: " . implode(', ', $stmt->errorInfo()) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>