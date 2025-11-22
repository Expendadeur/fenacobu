<?php
// diagnostic_fixed.php - Script pour diagnostiquer et corriger les problèmes
require_once 'config.php';

echo "<h1>Diagnostic et Correction FENACOBU</h1>";

// 1. Vérifier la connexion à la base de données
echo "<h2>1. Test de connexion à la base de données</h2>";
try {
    $db = DatabaseConfig::getConnection();
    echo "✅ Connexion à la base de données réussie<br>";
} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Vérifier la structure de la table agents
echo "<h2>2. Structure de la table agents</h2>";
try {
    $stmt = $db->query("DESCRIBE agents");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// 3. Vérifier les utilisateurs (avec une requête adaptée)
echo "<h2>3. Utilisateurs dans la base de données</h2>";
try {
    // D'abord, vérifier quelles colonnes existent
    $stmt = $db->query("SHOW COLUMNS FROM agents");
    $availableColumns = [];
    while ($row = $stmt->fetch()) {
        $availableColumns[] = $row['Field'];
    }
    
    // Construire une requête avec seulement les colonnes qui existent
    $selectColumns = [];
    $possibleColumns = ['id', 'username', 'email', 'role', 'is_active', 'failed_attempts', 'locked_until'];
    
    foreach ($possibleColumns as $col) {
        if (in_array($col, $availableColumns)) {
            $selectColumns[] = $col;
        }
    }
    
    if (empty($selectColumns)) {
        echo "❌ Aucune colonne standard trouvée<br>";
    } else {
        $sql = "SELECT " . implode(', ', $selectColumns) . " FROM agents";
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "❌ Aucun utilisateur trouvé dans la base de données<br>";
            echo "<strong>Action:</strong> Cliquez sur le bouton ci-dessous pour créer les utilisateurs de test.<br>";
        } else {
            echo "✅ Utilisateurs trouvés:<br>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr>";
            foreach ($selectColumns as $col) {
                echo "<th>$col</th>";
            }
            echo "</tr>";
            
            foreach ($users as $user) {
                echo "<tr>";
                foreach ($selectColumns as $col) {
                    $value = $user[$col] ?? 'N/A';
                    if ($col == 'locked_until' && $value) {
                        $value = date('Y-m-d H:i:s', strtotime($value));
                    } elseif ($col == 'is_active') {
                        $value = $value ? 'Oui' : 'Non';
                    }
                    echo "<td>$value</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la récupération des utilisateurs: " . $e->getMessage() . "<br>";
}

// 4. Boutons d'action pour corriger les problèmes
echo "<h2>4. Actions correctives</h2>";
echo "<div style='margin: 20px 0;'>";

// Bouton pour créer les utilisateurs
echo "<form method='post' style='display: inline-block; margin-right: 10px;'>";
echo "<input type='hidden' name='action' value='create_users'>";
echo "<button type='submit' style='background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Créer les utilisateurs de test</button>";
echo "</form>";

// Bouton pour débloquer les comptes
echo "<form method='post' style='display: inline-block; margin-right: 10px;'>";
echo "<input type='hidden' name='action' value='unlock_accounts'>";
echo "<button type='submit' style='background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Débloquer tous les comptes</button>";
echo "</form>";

// Bouton pour réinitialiser les mots de passe
echo "<form method='post' style='display: inline-block;'>";
echo "<input type='hidden' name='action' value='reset_passwords'>";
echo "<button type='submit' style='background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Réinitialiser les mots de passe</button>";
echo "</form>";

echo "</div>";

// Traitement des actions
if ($_POST['action'] ?? '') {
    echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>";
    
    switch ($_POST['action']) {
        case 'create_users':
            try {
                // Créer les utilisateurs de test
                $users = [
                    ['admin', 'admin123', 'admin@fenacobu.bi', 'Administrateur', 'Admin', 'FENACOBU'],
                    ['caissier', 'caiss123', 'caissier@fenacobu.bi', 'Caissier', 'Jean', 'Dupont'],
                    ['conseiller', 'cons123', 'conseiller@fenacobu.bi', 'Conseiller', 'Marie', 'Martin']
                ];
                
                foreach ($users as $user) {
                    // Vérifier si l'utilisateur existe déjà
                    $stmt = $db->prepare("SELECT username FROM agents WHERE username = ?");
                    $stmt->execute([$user[0]]);
                    
                    if (!$stmt->fetch()) {
                        $hashedPassword = SecurityManager::hashPassword($user[1]);
                        
                        $stmt = $db->prepare("
                            INSERT INTO agents (username, password, email, role, first_name, last_name, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        
                        $stmt->execute([
                            $user[0], $hashedPassword, $user[2], $user[3], $user[4], $user[5]
                        ]);
                        
                        echo "✅ Utilisateur <strong>{$user[0]}</strong> créé (mot de passe: <strong>{$user[1]}</strong>)<br>";
                    } else {
                        echo "ℹ️ Utilisateur <strong>{$user[0]}</strong> existe déjà<br>";
                    }
                }
            } catch (Exception $e) {
                echo "❌ Erreur lors de la création: " . $e->getMessage() . "<br>";
            }
            break;
            
        case 'unlock_accounts':
            try {
                $stmt = $db->prepare("UPDATE agents SET failed_attempts = 0, locked_until = NULL WHERE failed_attempts > 0 OR locked_until IS NOT NULL");
                $stmt->execute();
                $affected = $stmt->rowCount();
                echo "✅ $affected compte(s) débloqué(s)<br>";
            } catch (Exception $e) {
                echo "❌ Erreur lors du déblocage: " . $e->getMessage() . "<br>";
            }
            break;
            
        case 'reset_passwords':
            try {
                $passwords = [
                    'admin' => 'admin123',
                    'caissier' => 'caiss123',
                    'conseiller' => 'cons123'
                ];
                
                foreach ($passwords as $username => $password) {
                    $hashedPassword = SecurityManager::hashPassword($password);
                    $stmt = $db->prepare("UPDATE agents SET password = ?, failed_attempts = 0, locked_until = NULL WHERE username = ?");
                    $stmt->execute([$hashedPassword, $username]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo "✅ Mot de passe de <strong>$username</strong> réinitialisé à <strong>$password</strong><br>";
                    }
                }
            } catch (Exception $e) {
                echo "❌ Erreur lors de la réinitialisation: " . $e->getMessage() . "<br>";
            }
            break;
    }
    
    echo "</div>";
    echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
}

// 5. Test de connexion en temps réel
echo "<h2>5. Test de connexion en temps réel</h2>";
echo "<form method='post' style='background: #f9f9f9; padding: 20px; border-radius: 10px;'>";
echo "<h3>Tester la connexion :</h3>";
echo "<input type='hidden' name='action' value='test_login'>";
echo "<input type='text' name='test_username' placeholder='Nom d'utilisateur' style='padding: 10px; margin: 5px; border: 1px solid #ddd; border-radius: 5px;'>";
echo "<input type='password' name='test_password' placeholder='Mot de passe' style='padding: 10px; margin: 5px; border: 1px solid #ddd; border-radius: 5px;'>";
echo "<button type='submit' style='background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Tester</button>";
echo "</form>";

if ($_POST['action'] ?? '' == 'test_login' && $_POST['test_username'] && $_POST['test_password']) {
    echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0;'>";
    echo "<h3>Résultat du test :</h3>";
    
    try {
        $username = $_POST['test_username'];
        $password = $_POST['test_password'];
        
        $stmt = $db->prepare("SELECT username, password, role FROM agents WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            if (SecurityManager::verifyPassword($password, $user['password'])) {
                echo "✅ <strong>CONNEXION RÉUSSIE !</strong><br>";
                echo "Utilisateur: {$user['username']}<br>";
                echo "Rôle: {$user['role']}<br>";
            } else {
                echo "❌ Mot de passe incorrect<br>";
                echo "Hash dans la base: " . substr($user['password'], 0, 50) . "...<br>";
            }
        } else {
            echo "❌ Utilisateur non trouvé ou inactif<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erreur lors du test: " . $e->getMessage() . "<br>";
    }
    
    echo "</div>";
}

echo "<h2>6. Informations de connexion par défaut</h2>";
echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 10px;'>";
echo "<h3>Utilisateurs de test :</h3>";
echo "<ul>";
echo "<li><strong>Administrateur:</strong> admin / admin123</li>";
echo "<li><strong>Caissier:</strong> caissier / caiss123</li>";
echo "<li><strong>Conseiller:</strong> conseiller / cons123</li>";
echo "</ul>";
echo "</div>";
?>