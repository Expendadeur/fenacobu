<?php
// ajax_actions.php - Gestionnaire des actions AJAX pour le dashboard admin
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

// Application des en-têtes de sécurité
header('Content-Type: application/json');
SecurityManager::setSecurityHeaders();

// Récupération de la connexion à la base de données
$db = DatabaseConfig::getConnection();

// Vérification du token CSRF
if (!isset($_POST['csrf_token']) || !SecurityManager::validateCSRF($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit();
}

// Récupération de l'action demandée
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        // ========================================
        // GESTION DES AGENTS
        // ========================================
        case 'add_agent':
            // Vérification des permissions (Administrateur uniquement)
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            // Validation des données
            $required = ['first_name', 'last_name', 'username', 'email', 'role', 'password', 'confirm_password'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Validation du mot de passe
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
                exit();
            }
            
            // Validation de la force du mot de passe
            if (strlen($password) < 8 || 
                !preg_match('/[A-Z]/', $password) || 
                !preg_match('/[a-z]/', $password) || 
                !preg_match('/\d/', $password) || 
                !preg_match('/[@#$%^&*!()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
                echo json_encode(['success' => false, 'message' => 'Le mot de passe ne respecte pas les critères de sécurité']);
                exit();
            }
            
            // Vérification de l'unicité du username et email
            $stmt = $db->prepare("SELECT COUNT(*) FROM agents WHERE username = ? OR email = ?");
            $stmt->execute([$_POST['username'], $_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Le username ou l\'email existe déjà']);
                exit();
            }
            
            // Construction du téléphone complet
            $telephone = null;
            if (!empty($_POST['country_code']) && !empty($_POST['phone_number'])) {
                $telephone = $_POST['country_code'] . $_POST['phone_number'];
            } elseif (!empty($_POST['telephone'])) {
                $telephone = $_POST['telephone'];
            }
            
            // Insertion du nouvel agent
            $stmt = $db->prepare("
                INSERT INTO agents (
                    username, password, email, telephone, role, 
                    first_name, last_name, id_agence, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $hashedPassword = SecurityManager::hashPassword($password);
            $id_agence = !empty($_POST['id_agence']) ? $_POST['id_agence'] : null;
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['username']),
                $hashedPassword,
                SecurityManager::sanitizeInput($_POST['email']),
                $telephone,
                $_POST['role'],
                SecurityManager::sanitizeInput($_POST['first_name']),
                SecurityManager::sanitizeInput($_POST['last_name']),
                $id_agence
            ]);
            
            // Log de l'opération
            logOperation($db, 'agents', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agent créé avec succès']);
            break;
            
        case 'edit_agent':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agentId = intval($_POST['id']);
            $required = ['first_name', 'last_name', 'username', 'email', 'role'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du username et email
            $stmt = $db->prepare("SELECT COUNT(*) FROM agents WHERE (username = ? OR email = ?) AND id_agent != ?");
            $stmt->execute([$_POST['username'], $_POST['email'], $agentId]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Le username ou l\'email existe déjà']);
                exit();
            }
            
            // Construction du téléphone
            $telephone = null;
            if (!empty($_POST['country_code']) && !empty($_POST['phone_number'])) {
                $telephone = $_POST['country_code'] . $_POST['phone_number'];
            }
            
            $stmt = $db->prepare("
                UPDATE agents SET 
                    username = ?, email = ?, telephone = ?, role = ?,
                    first_name = ?, last_name = ?, id_agence = ?
                WHERE id_agent = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['username']),
                SecurityManager::sanitizeInput($_POST['email']),
                $telephone,
                $_POST['role'],
                SecurityManager::sanitizeInput($_POST['first_name']),
                SecurityManager::sanitizeInput($_POST['last_name']),
                !empty($_POST['id_agence']) ? $_POST['id_agence'] : null,
                $agentId
            ]);
            
            logOperation($db, 'agents', 'UPDATE', $agentId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agent modifié avec succès']);
            break;
            
        case 'change_agent_password':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agentId = intval($_POST['id']);
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
                exit();
            }
            
            // Validation de la force du mot de passe
            if (strlen($newPassword) < 8 || 
                !preg_match('/[A-Z]/', $newPassword) || 
                !preg_match('/[a-z]/', $newPassword) || 
                !preg_match('/\d/', $newPassword) || 
                !preg_match('/[@#$%^&*!()_+\-=\[\]{};\':"\\|,.<>\/?]/', $newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Le mot de passe ne respecte pas les critères de sécurité']);
                exit();
            }
            
            $hashedPassword = SecurityManager::hashPassword($newPassword);
            
            $stmt = $db->prepare("UPDATE agents SET password = ? WHERE id_agent = ?");
            $stmt->execute([$hashedPassword, $agentId]);
            
            logOperation($db, 'agents', 'UPDATE', $agentId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
            break;
            
        case 'toggle_agent':
            // Vérification des permissions
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agentId = intval($_POST['id']);
            $newStatus = intval($_POST['status']);
            
            // Empêcher la désactivation de son propre compte
            if ($agentId == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas désactiver votre propre compte']);
                exit();
            }
            
            $stmt = $db->prepare("UPDATE agents SET is_active = ? WHERE id_agent = ?");
            $stmt->execute([$newStatus, $agentId]);
            
            logOperation($db, 'agents', 'UPDATE', $agentId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Statut modifié avec succès']);
            break;
            
        case 'block_agent':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agentId = intval($_POST['id']);
            
            // Empêcher le blocage de son propre compte
            if ($agentId == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas bloquer votre propre compte']);
                exit();
            }
            
            $stmt = $db->prepare("UPDATE agents SET is_blocked = 1, is_active = 0 WHERE id_agent = ?");
            $stmt->execute([$agentId]);
            
            logOperation($db, 'agents', 'UPDATE', $agentId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agent bloqué avec succès']);
            break;
            
        case 'unblock_agent':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agentId = intval($_POST['id']);
            
            $stmt = $db->prepare("UPDATE agents SET is_blocked = 0, is_active = 1 WHERE id_agent = ?");
            $stmt->execute([$agentId]);
            
            logOperation($db, 'agents', 'UPDATE', $agentId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agent débloqué avec succès']);
            break;
            
        // ========================================
        // GESTION DES CLIENTS
        // ========================================
        case 'toggle_client':
            $clientId = intval($_POST['id']);
            $newStatus = intval($_POST['status']);
            
            $stmt = $db->prepare("UPDATE clients SET actif = ? WHERE id_client = ?");
            $stmt->execute([$newStatus, $clientId]);
            
            logOperation($db, 'clients', 'UPDATE', $clientId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Statut client modifié avec succès']);
            break;
            
        case 'add_client':
            // Validation des données
            $required = ['nom', 'prenom', 'email', 'telephone'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité de l'email
            $stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Cet email existe déjà']);
                exit();
            }
            
            // Insertion du nouveau client
            $stmt = $db->prepare("
                INSERT INTO clients (
                    nom, prenom, email, telephone, adresse, 
                    revenu_mensuel, score_credit, actif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['nom']),
                SecurityManager::sanitizeInput($_POST['prenom']),
                SecurityManager::sanitizeInput($_POST['email']),
                SecurityManager::sanitizeInput($_POST['telephone']),
                !empty($_POST['adresse']) ? SecurityManager::sanitizeInput($_POST['adresse']) : null,
                !empty($_POST['revenu_mensuel']) ? floatval($_POST['revenu_mensuel']) : 0,
                !empty($_POST['score_credit']) ? intval($_POST['score_credit']) : 500
            ]);
            
            logOperation($db, 'clients', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Client créé avec succès']);
            break;
            
        case 'edit_client':
            $clientId = intval($_POST['id']);
            $required = ['nom', 'prenom', 'email', 'telephone'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité de l'email
            $stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE email = ? AND id_client != ?");
            $stmt->execute([$_POST['email'], $clientId]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Cet email existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                UPDATE clients SET 
                    nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?,
                    revenu_mensuel = ?, score_credit = ?
                WHERE id_client = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['nom']),
                SecurityManager::sanitizeInput($_POST['prenom']),
                SecurityManager::sanitizeInput($_POST['email']),
                SecurityManager::sanitizeInput($_POST['telephone']),
                !empty($_POST['adresse']) ? SecurityManager::sanitizeInput($_POST['adresse']) : null,
                !empty($_POST['revenu_mensuel']) ? floatval($_POST['revenu_mensuel']) : 0,
                !empty($_POST['score_credit']) ? intval($_POST['score_credit']) : 500,
                $clientId
            ]);
            
            logOperation($db, 'clients', 'UPDATE', $clientId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Client modifié avec succès']);
            break;
            
        // ========================================
        // GESTION DES COMPTES
        // ========================================
        case 'add_compte':
            // Validation des données
            $required = ['id_client', 'id_type_compte', 'num_compte'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du numéro de compte
            $stmt = $db->prepare("SELECT COUNT(*) FROM comptes WHERE num_compte = ?");
            $stmt->execute([$_POST['num_compte']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce numéro de compte existe déjà']);
                exit();
            }
            
            // Récupération des informations du type de compte
            $stmt = $db->prepare("SELECT * FROM types_compte WHERE id_type_compte = ? AND actif = 1");
            $stmt->execute([$_POST['id_type_compte']]);
            $typeCompte = $stmt->fetch();
            
            if (!$typeCompte) {
                echo json_encode(['success' => false, 'message' => 'Type de compte invalide']);
                exit();
            }
            
            // Insertion du nouveau compte
            $soldeInitial = !empty($_POST['solde_initial']) ? floatval($_POST['solde_initial']) : 0;
            
            $stmt = $db->prepare("
                INSERT INTO comptes (
                    num_compte, id_client, id_type_compte, 
                    solde, solde_disponible, statut
                ) VALUES (?, ?, ?, ?, ?, 'Actif')
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['num_compte']),
                intval($_POST['id_client']),
                intval($_POST['id_type_compte']),
                $soldeInitial,
                $soldeInitial
            ]);
            
            logOperation($db, 'comptes', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Compte créé avec succès']);
            break;
            
        case 'edit_compte':
            $compteId = intval($_POST['id']);
            $required = ['num_compte', 'id_type_compte', 'statut'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du numéro de compte
            $stmt = $db->prepare("SELECT COUNT(*) FROM comptes WHERE num_compte = ? AND id_compte != ?");
            $stmt->execute([$_POST['num_compte'], $compteId]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce numéro de compte existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                UPDATE comptes SET 
                    num_compte = ?, id_type_compte = ?, statut = ?
                WHERE id_compte = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['num_compte']),
                intval($_POST['id_type_compte']),
                $_POST['statut'],
                $compteId
            ]);
            
            logOperation($db, 'comptes', 'UPDATE', $compteId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Compte modifié avec succès']);
            break;
            
        // ========================================
        // GESTION DES TYPES DE COMPTE
        // ========================================
        case 'add_type_compte':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $required = ['code', 'libelle'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du code
            $stmt = $db->prepare("SELECT COUNT(*) FROM types_compte WHERE code = ?");
            $stmt->execute([$_POST['code']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce code existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                INSERT INTO types_compte (
                    code, libelle, description, taux_interet, 
                    frais_gestion_mensuel, solde_minimum, 
                    decouvert_autorise, montant_decouvert_max, actif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['code']),
                SecurityManager::sanitizeInput($_POST['libelle']),
                !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null,
                !empty($_POST['taux_interet']) ? floatval($_POST['taux_interet']) : 0,
                !empty($_POST['frais_gestion_mensuel']) ? floatval($_POST['frais_gestion_mensuel']) : 0,
                !empty($_POST['solde_minimum']) ? floatval($_POST['solde_minimum']) : 0,
                !empty($_POST['decouvert_autorise']) ? intval($_POST['decouvert_autorise']) : 0,
                !empty($_POST['montant_decouvert_max']) ? floatval($_POST['montant_decouvert_max']) : 0
            ]);
            
            logOperation($db, 'types_compte', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Type de compte créé avec succès']);
            break;
            
        case 'edit_type_compte':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $typeId = intval($_POST['id']);
            $required = ['code', 'libelle'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du code
            $stmt = $db->prepare("SELECT COUNT(*) FROM types_compte WHERE code = ? AND id_type_compte != ?");
            $stmt->execute([$_POST['code'], $typeId]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce code existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                UPDATE types_compte SET 
                    code = ?, libelle = ?, description = ?, taux_interet = ?,
                    frais_gestion_mensuel = ?, solde_minimum = ?, 
                    decouvert_autorise = ?, montant_decouvert_max = ?, actif = ?
                WHERE id_type_compte = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['code']),
                SecurityManager::sanitizeInput($_POST['libelle']),
                !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null,
                !empty($_POST['taux_interet']) ? floatval($_POST['taux_interet']) : 0,
                !empty($_POST['frais_gestion_mensuel']) ? floatval($_POST['frais_gestion_mensuel']) : 0,
                !empty($_POST['solde_minimum']) ? floatval($_POST['solde_minimum']) : 0,
                !empty($_POST['decouvert_autorise']) ? intval($_POST['decouvert_autorise']) : 0,
                !empty($_POST['montant_decouvert_max']) ? floatval($_POST['montant_decouvert_max']) : 0,
                !empty($_POST['actif']) ? intval($_POST['actif']) : 1,
                $typeId
            ]);
            
            logOperation($db, 'types_compte', 'UPDATE', $typeId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Type de compte modifié avec succès']);
            break;
            
        // ========================================
        // GESTION DES TYPES DE TRANSACTION
        // ========================================
        case 'add_type_transaction':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $required = ['code', 'libelle', 'categorie', 'sens'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du code
            $stmt = $db->prepare("SELECT COUNT(*) FROM types_transaction WHERE code = ?");
            $stmt->execute([$_POST['code']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce code existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                INSERT INTO types_transaction (
                    code, libelle, categorie, sens, frais_fixe, 
                    frais_pourcentage, montant_min, montant_max, 
                    necessite_guichet, actif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['code']),
                SecurityManager::sanitizeInput($_POST['libelle']),
                $_POST['categorie'],
                $_POST['sens'],
                !empty($_POST['frais_fixe']) ? floatval($_POST['frais_fixe']) : 0,
                !empty($_POST['frais_pourcentage']) ? floatval($_POST['frais_pourcentage']) : 0,
                !empty($_POST['montant_min']) ? floatval($_POST['montant_min']) : 0,
                !empty($_POST['montant_max']) ? floatval($_POST['montant_max']) : null,
                !empty($_POST['necessite_guichet']) ? intval($_POST['necessite_guichet']) : 0
            ]);
            
            logOperation($db, 'types_transaction', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Type de transaction créé avec succès']);
            break;
            
        case 'edit_type_transaction':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $typeId = intval($_POST['id']);
            $required = ['code', 'libelle', 'categorie', 'sens'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du code
            $stmt = $db->prepare("SELECT COUNT(*) FROM types_transaction WHERE code = ? AND id_type_transaction != ?");
            $stmt->execute([$_POST['code'], $typeId]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce code existe déjà']);
                exit();
            }
            
            $stmt = $db->prepare("
                UPDATE types_transaction SET 
                    code = ?, libelle = ?, categorie = ?, sens = ?, frais_fixe = ?,
                    frais_pourcentage = ?, montant_min = ?, montant_max = ?, 
                    necessite_guichet = ?, actif = ?
                WHERE id_type_transaction = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['code']),
                SecurityManager::sanitizeInput($_POST['libelle']),
                $_POST['categorie'],
                $_POST['sens'],
                !empty($_POST['frais_fixe']) ? floatval($_POST['frais_fixe']) : 0,
                !empty($_POST['frais_pourcentage']) ? floatval($_POST['frais_pourcentage']) : 0,
                !empty($_POST['montant_min']) ? floatval($_POST['montant_min']) : 0,
                !empty($_POST['montant_max']) ? floatval($_POST['montant_max']) : null,
                !empty($_POST['necessite_guichet']) ? intval($_POST['necessite_guichet']) : 0,
                !empty($_POST['actif']) ? intval($_POST['actif']) : 1,
                $typeId
            ]);
            
            logOperation($db, 'types_transaction', 'UPDATE', $typeId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Type de transaction modifié avec succès']);
            break;
            
        // ========================================
        // GESTION DES CRÉDITS
        // ========================================
        case 'approve_credit':
            // Vérification des permissions
            if (!in_array($_SESSION['role'], ['Administrateur', 'Conseiller'])) {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $demandeId = intval($_POST['id']);
            
            // Vérification que la demande existe et est en attente
            $stmt = $db->prepare("
                SELECT * FROM demandes_credit 
                WHERE id_demande = ? AND statut IN ('En attente', 'En étude')
            ");
            $stmt->execute([$demandeId]);
            $demande = $stmt->fetch();
            
            if (!$demande) {
                echo json_encode(['success' => false, 'message' => 'Demande introuvable ou déjà traitée']);
                exit();
            }
            
            // Mise à jour du statut
            $stmt = $db->prepare("
                UPDATE demandes_credit 
                SET statut = 'Approuvé', 
                    date_traitement = NOW(),
                    id_agent = ?
                WHERE id_demande = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $demandeId]);
            
            logOperation($db, 'demandes_credit', 'UPDATE', $demandeId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Demande approuvée avec succès']);
            break;
            
        case 'reject_credit':
            // Vérification des permissions
            if (!in_array($_SESSION['role'], ['Administrateur', 'Conseiller'])) {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $demandeId = intval($_POST['id']);
            $commentaires = !empty($_POST['commentaires']) ? SecurityManager::sanitizeInput($_POST['commentaires']) : null;
            
            $stmt = $db->prepare("
                UPDATE demandes_credit 
                SET statut = 'Rejeté', 
                    date_traitement = NOW(),
                    id_agent = ?,
                    commentaires = ?
                WHERE id_demande = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $commentaires, $demandeId]);
            
            logOperation($db, 'demandes_credit', 'UPDATE', $demandeId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Demande rejetée avec succès']);
            break;
            
        // ========================================
        // GESTION DES TRANSACTIONS
        // ========================================
        case 'add_transaction':
            // Vérification des permissions
            if (!in_array($_SESSION['role'], ['Administrateur', 'Caissier'])) {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            // Validation des données
            $required = ['num_compte', 'id_type_transaction', 'montant'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $numCompte = SecurityManager::sanitizeInput($_POST['num_compte']);
            $idTypeTransaction = intval($_POST['id_type_transaction']);
            $montant = floatval($_POST['montant']);
            $idGuichet = !empty($_POST['id_guichet']) ? intval($_POST['id_guichet']) : null;
            $description = !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null;
            
            // Vérification du compte
            $stmt = $db->prepare("SELECT * FROM comptes WHERE num_compte = ? AND statut = 'Actif'");
            $stmt->execute([$numCompte]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                echo json_encode(['success' => false, 'message' => 'Compte introuvable ou inactif']);
                exit();
            }
            
            // Récupération du type de transaction
            $stmt = $db->prepare("SELECT * FROM types_transaction WHERE id_type_transaction = ? AND actif = 1");
            $stmt->execute([$idTypeTransaction]);
            $typeTransaction = $stmt->fetch();
            
            if (!$typeTransaction) {
                echo json_encode(['success' => false, 'message' => 'Type de transaction invalide']);
                exit();
            }
            
            // Vérification des montants min/max
            if ($montant < $typeTransaction['montant_min']) {
                echo json_encode(['success' => false, 'message' => 'Montant inférieur au minimum autorisé']);
                exit();
            }
            
            if ($typeTransaction['montant_max'] && $montant > $typeTransaction['montant_max']) {
                echo json_encode(['success' => false, 'message' => 'Montant supérieur au maximum autorisé']);
                exit();
            }
            
            // Calcul des frais
            $frais = $typeTransaction['frais_fixe'] + ($montant * $typeTransaction['frais_pourcentage'] / 100);
            $montantTotal = $montant + $frais;
            
            // Vérification du solde pour les débits
            if ($typeTransaction['sens'] == 'DEBIT') {
                if ($compte['solde_disponible'] < $montantTotal) {
                    echo json_encode(['success' => false, 'message' => 'Solde insuffisant']);
                    exit();
                }
            }
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // Insertion de la transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent, id_guichet,
                        montant, frais, montant_total, statut, description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Terminée', ?)
                ");
                
                $stmt->execute([
                    $numCompte,
                    $idTypeTransaction,
                    $_SESSION['user_id'],
                    $idGuichet,
                    $montant,
                    $frais,
                    $montantTotal,
                    $description
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // Mise à jour du solde du compte
                if ($typeTransaction['sens'] == 'DEBIT') {
                    $stmt = $db->prepare("
                        UPDATE comptes 
                        SET solde = solde - ?,
                            solde_disponible = solde_disponible - ?,
                            date_derniere_operation = NOW()
                        WHERE num_compte = ?
                    ");
                    $stmt->execute([$montantTotal, $montantTotal, $numCompte]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE comptes 
                        SET solde = solde + ?,
                            solde_disponible = solde_disponible + ?,
                            date_derniere_operation = NOW()
                        WHERE num_compte = ?
                    ");
                    $stmt->execute([$montant, $montant, $numCompte]);
                }
                
                logOperation($db, 'transactions', 'INSERT', $transactionId, $_SESSION['user_id']);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Transaction effectuée avec succès',
                    'transaction_id' => $transactionId,
                    'nouveau_solde' => $compte['solde'] + ($typeTransaction['sens'] == 'CREDIT' ? $montant : -$montantTotal)
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        // ========================================
        // GESTION DES AGENCES
        // ========================================
        case 'add_agence':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $required = ['nom', 'adresse'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO agences (nom, adresse, telephone, horaires_ouverture, services_proposes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['nom']),
                SecurityManager::sanitizeInput($_POST['adresse']),
                !empty($_POST['telephone']) ? SecurityManager::sanitizeInput($_POST['telephone']) : null,
                !empty($_POST['horaires_ouverture']) ? SecurityManager::sanitizeInput($_POST['horaires_ouverture']) : null,
                !empty($_POST['services_proposes']) ? SecurityManager::sanitizeInput($_POST['services_proposes']) : null
            ]);
            
            logOperation($db, 'agences', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agence créée avec succès']);
            break;
            
        case 'edit_agence':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $agenceId = intval($_POST['id']);
            $required = ['nom', 'adresse'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $stmt = $db->prepare("
                UPDATE agences SET 
                    nom = ?, adresse = ?, telephone = ?, 
                    horaires_ouverture = ?, services_proposes = ?
                WHERE id_agence = ?
            ");
            
            $stmt->execute([
                SecurityManager::sanitizeInput($_POST['nom']),
                SecurityManager::sanitizeInput($_POST['adresse']),
                !empty($_POST['telephone']) ? SecurityManager::sanitizeInput($_POST['telephone']) : null,
                !empty($_POST['horaires_ouverture']) ? SecurityManager::sanitizeInput($_POST['horaires_ouverture']) : null,
                !empty($_POST['services_proposes']) ? SecurityManager::sanitizeInput($_POST['services_proposes']) : null,
                $agenceId
            ]);
            
            logOperation($db, 'agences', 'UPDATE', $agenceId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Agence modifiée avec succès']);
            break;
            
        // ========================================
        // GESTION DES GUICHETS
        // ========================================
        case 'add_guichet':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $required = ['id_agence', 'type_guichet'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO guichets (id_agence, numero_guichet, type_guichet, statut) 
                VALUES (?, ?, ?, 'Actif')
            ");
            
            $stmt->execute([
                intval($_POST['id_agence']),
                !empty($_POST['numero_guichet']) ? intval($_POST['numero_guichet']) : null,
                $_POST['type_guichet']
            ]);
            
            logOperation($db, 'guichets', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Guichet créé avec succès']);
            break;
            
        case 'edit_guichet':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $guichetId = intval($_POST['id']);
            $required = ['id_agence', 'type_guichet', 'statut'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $stmt = $db->prepare("
                UPDATE guichets SET 
                    id_agence = ?, numero_guichet = ?, type_guichet = ?, statut = ?
                WHERE id_guichet = ?
            ");
            
            $stmt->execute([
                intval($_POST['id_agence']),
                !empty($_POST['numero_guichet']) ? intval($_POST['numero_guichet']) : null,
                $_POST['type_guichet'],
                $_POST['statut'],
                $guichetId
            ]);
            
            logOperation($db, 'guichets', 'UPDATE', $guichetId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Guichet modifié avec succès']);
            break;
            
        case 'toggle_guichet_status':
            if ($_SESSION['role'] !== 'Administrateur') {
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                exit();
            }
            
            $guichetId = intval($_POST['id']);
            $newStatus = $_POST['status']; // 'Actif', 'Maintenance', 'Hors service'
            
            $stmt = $db->prepare("UPDATE guichets SET statut = ? WHERE id_guichet = ?");
            $stmt->execute([$newStatus, $guichetId]);
            
            logOperation($db, 'guichets', 'UPDATE', $guichetId, $_SESSION['user_id']);
            
            echo json_encode(['success' => true, 'message' => 'Statut du guichet modifié']);
            break;
            
        // ========================================
        // RÉCUPÉRATION DES DONNÉES POUR ÉDITION
        // ========================================
        case 'get_agent':
            $agentId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM agents WHERE id_agent = ?");
            $stmt->execute([$agentId]);
            $agent = $stmt->fetch();
            
            if ($agent) {
                // Séparation du téléphone en code pays et numéro
                if ($agent['telephone']) {
                    $phone = $agent['telephone'];
                    if (preg_match('/^(\+\d{1,3})(\d+)$/', $phone, $matches)) {
                        $agent['country_code'] = $matches[1];
                        $agent['phone_number'] = $matches[2];
                    }
                }
                echo json_encode(['success' => true, 'data' => $agent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Agent non trouvé']);
            }
            break;
            
        case 'get_client':
            $clientId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();
            
            if ($client) {
                echo json_encode(['success' => true, 'data' => $client]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Client non trouvé']);
            }
            break;
            
        case 'get_compte':
            $compteId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id_compte = ?");
            $stmt->execute([$compteId]);
            $compte = $stmt->fetch();
            
            if ($compte) {
                echo json_encode(['success' => true, 'data' => $compte]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Compte non trouvé']);
            }
            break;
            
        case 'get_type_compte':
            $typeId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM types_compte WHERE id_type_compte = ?");
            $stmt->execute([$typeId]);
            $type = $stmt->fetch();
            
            if ($type) {
                echo json_encode(['success' => true, 'data' => $type]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Type de compte non trouvé']);
            }
            break;
            
        case 'get_type_transaction':
            $typeId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM types_transaction WHERE id_type_transaction = ?");
            $stmt->execute([$typeId]);
            $type = $stmt->fetch();
            
            if ($type) {
                echo json_encode(['success' => true, 'data' => $type]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Type de transaction non trouvé']);
            }
            break;
            
        case 'get_agence':
            $agenceId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM agences WHERE id_agence = ?");
            $stmt->execute([$agenceId]);
            $agence = $stmt->fetch();
            
            if ($agence) {
                echo json_encode(['success' => true, 'data' => $agence]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Agence non trouvée']);
            }
            break;
            
        case 'get_guichet':
            $guichetId = intval($_POST['id']);
            $stmt = $db->prepare("SELECT * FROM guichets WHERE id_guichet = ?");
            $stmt->execute([$guichetId]);
            $guichet = $stmt->fetch();
            
            if ($guichet) {
                echo json_encode(['success' => true, 'data' => $guichet]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Guichet non trouvé']);
            }
            break;
            
        // ========================================
        // LOGS D'AUDIT
        // ========================================
        case 'get_log_details':
            $logId = intval($_POST['id']);
            
            $stmt = $db->prepare("SELECT * FROM log_operations WHERE id_log = ?");
            $stmt->execute([$logId]);
            $log = $stmt->fetch();
            
            if (!$log) {
                echo json_encode(['success' => false, 'message' => 'Log introuvable']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'old_values' => json_decode($log['old_values']),
                'new_values' => json_decode($log['new_values'])
            ]);
            break;
            
        // ========================================
        // ACTION INCONNUE
        // ========================================
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue: ' . $action]);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Erreur PDO dans ajax_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erreur dans ajax_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

// ========================================
// FONCTION HELPER - LOG DES OPÉRATIONS
// ========================================
function logOperation($db, $tableName, $operationType, $recordId, $agentId, $oldValues = null, $newValues = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO log_operations (
                table_name, operation_type, record_id, id_agent, old_values, new_values
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tableName,
            $operationType,
            $recordId,
            $agentId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors du log de l'opération: " . $e->getMessage());
    }
}
?>