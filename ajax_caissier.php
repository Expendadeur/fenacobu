<?php
// ajax_caissier.php - Gestionnaire des actions AJAX pour les caissiers
require_once 'config.php';

// Vérification de session et permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Caissier') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Application des en-têtes de sécurité
header('Content-Type: application/json');
SecurityManager::setSecurityHeaders();

// Récupération de la connexion
$db = DatabaseConfig::getConnection();

// Vérification du token CSRF
if (!isset($_POST['csrf_token']) || !SecurityManager::validateCSRF($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit();
}

// Récupération de l'action
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        // ========================================
        // RECHERCHE DE COMPTE - AMÉLIORÉE POUR LA PHOTO
        // ========================================
        case 'search_account':
            $numCompte = SecurityManager::sanitizeInput($_POST['num_compte']);
            
            if (empty($numCompte)) {
                echo json_encode(['success' => false, 'message' => 'Numéro de compte requis']);
                exit();
            }
            
            // Recherche du compte avec toutes les informations nécessaires
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    cl.nom as client_nom,
                    cl.prenom as client_prenom,
                    cl.photo_profil,
                    cl.telephone as client_telephone,
                    cl.email as client_email,
                    tc.libelle as type_compte_libelle,
                    tc.code as type_compte_code,
                    tc.decouvert_autorise,
                    tc.montant_decouvert_max,
                    ag.nom as agence_origine_nom,
                    ag.id_agence as agence_origine_id,
                    ag.adresse as agence_origine_adresse
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                INNER JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
                INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
                WHERE c.num_compte = ?
                AND cl.actif = 1
            ");
            
            $stmt->execute([$numCompte]);
            $account = $stmt->fetch();
            
            if (!$account) {
                echo json_encode(['success' => false, 'message' => 'Compte introuvable ou client inactif']);
                exit();
            }
            
            // Construction de l'URL complète de la photo de profil
            if (!empty($account['photo_profil'])) {
                if (strpos($account['photo_profil'], 'http') === 0) {
                    $account['photo_profil_url'] = $account['photo_profil'];
                } else {
                    $account['photo_profil_url'] = 'assets/uploads/profiles/' . $account['photo_profil'];
                }
            } else {
                $account['photo_profil_url'] = 'assets/images/default-avatar.png';
            }
            
            // Vérification du statut du compte
            if ($account['statut'] !== 'Actif') {
                echo json_encode([
                    'success' => true,
                    'account' => $account,
                    'warning' => 'Le compte est ' . $account['statut']
                ]);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'account' => $account
            ]);
            break;
            
        // ========================================
        // TRAITEMENT DÉPÔT
        // ========================================
        case 'process_depot':
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
            $description = !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null;
            
            // Vérification du compte
            $stmt = $db->prepare("
                SELECT c.*, cl.actif as client_actif
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                WHERE c.num_compte = ?
            ");
            $stmt->execute([$numCompte]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                echo json_encode(['success' => false, 'message' => 'Compte introuvable']);
                exit();
            }
            
            if ($compte['statut'] !== 'Actif') {
                echo json_encode(['success' => false, 'message' => 'Le compte est ' . $compte['statut']]);
                exit();
            }
            
            // Récupération du type de transaction
            $stmt = $db->prepare("
                SELECT * FROM types_transaction 
                WHERE id_type_transaction = ? 
                AND actif = 1
                AND categorie = 'DEPOT'
            ");
            $stmt->execute([$idTypeTransaction]);
            $typeTransaction = $stmt->fetch();
            
            if (!$typeTransaction) {
                echo json_encode(['success' => false, 'message' => 'Type de transaction invalide']);
                exit();
            }
            
            // Calcul des frais
            $frais = $typeTransaction['frais_fixe'] + ($montant * $typeTransaction['frais_pourcentage'] / 100);
            $montantTotal = $montant + $frais;
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // Insertion de la transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent,
                        montant, frais, montant_total, statut, description, date_heure
                    ) VALUES (?, ?, ?, ?, ?, ?, 'Terminée', ?, NOW())
                ");
                
                $stmt->execute([
                    $numCompte,
                    $idTypeTransaction,
                    $_SESSION['user_id'],
                    $montant,
                    $frais,
                    $montantTotal,
                    $description
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // Mise à jour du solde du compte
                $nouveauSolde = $compte['solde'] + $montant;
                $nouveauSoldeDisponible = $compte['solde_disponible'] + $montant;
                
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET solde = ?,
                        solde_disponible = ?,
                        date_derniere_operation = NOW()
                    WHERE num_compte = ?
                ");
                $stmt->execute([$nouveauSolde, $nouveauSoldeDisponible, $numCompte]);
                
                // Log de l'opération
                logOperation($db, 'transactions', 'INSERT', $transactionId, $_SESSION['user_id']);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Dépôt effectué avec succès',
                    'transaction_id' => $transactionId,
                    'nouveau_solde' => $nouveauSolde
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erreur dépôt: " . $e->getMessage());
                throw $e;
            }
            break;
            
        // ========================================
        // TRAITEMENT RETRAIT
        // ========================================
        case 'process_retrait':
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
            $description = !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null;
            
            // Vérification du compte
            $stmt = $db->prepare("
                SELECT c.*, cl.actif as client_actif
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                WHERE c.num_compte = ?
            ");
            $stmt->execute([$numCompte]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                echo json_encode(['success' => false, 'message' => 'Compte introuvable']);
                exit();
            }
            
            if ($compte['statut'] !== 'Actif') {
                echo json_encode(['success' => false, 'message' => 'Le compte est ' . $compte['statut']]);
                exit();
            }
            
            // Récupération du type de transaction
            $stmt = $db->prepare("
                SELECT * FROM types_transaction 
                WHERE id_type_transaction = ? 
                AND actif = 1
                AND categorie = 'RETRAIT'
            ");
            $stmt->execute([$idTypeTransaction]);
            $typeTransaction = $stmt->fetch();
            
            if (!$typeTransaction) {
                echo json_encode(['success' => false, 'message' => 'Type de transaction invalide']);
                exit();
            }
            
            // Calcul des frais
            $frais = $typeTransaction['frais_fixe'] + ($montant * $typeTransaction['frais_pourcentage'] / 100);
            $montantTotal = $montant + $frais;
            
            // Vérification du solde
            if ($compte['solde_disponible'] < $montantTotal) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Solde insuffisant. Disponible: ' . number_format($compte['solde_disponible'], 0, ',', ' ') . ' BIF'
                ]);
                exit();
            }
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // Insertion de la transaction
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent,
                        montant, frais, montant_total, statut, description, date_heure
                    ) VALUES (?, ?, ?, ?, ?, ?, 'Terminée', ?, NOW())
                ");
                
                $stmt->execute([
                    $numCompte,
                    $idTypeTransaction,
                    $_SESSION['user_id'],
                    $montant,
                    $frais,
                    $montantTotal,
                    $description
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // Mise à jour du solde du compte
                $nouveauSolde = $compte['solde'] - $montantTotal;
                $nouveauSoldeDisponible = $compte['solde_disponible'] - $montantTotal;
                
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET solde = ?,
                        solde_disponible = ?,
                        date_derniere_operation = NOW()
                    WHERE num_compte = ?
                ");
                $stmt->execute([$nouveauSolde, $nouveauSoldeDisponible, $numCompte]);
                
                // Log de l'opération
                logOperation($db, 'transactions', 'INSERT', $transactionId, $_SESSION['user_id']);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Retrait effectué avec succès',
                    'transaction_id' => $transactionId,
                    'nouveau_solde' => $nouveauSolde
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erreur retrait: " . $e->getMessage());
                throw $e;
            }
            break;
            
        // ========================================
        // CRÉATION DE NOUVEAU COMPTE - AVEC PHOTO
        // ========================================
        case 'create_account':
            // Validation des données
            $required = ['nom', 'prenom', 'telephone', 'num_compte', 'id_type_compte', 'id_agence_origine'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $nom = SecurityManager::sanitizeInput($_POST['nom']);
            $prenom = SecurityManager::sanitizeInput($_POST['prenom']);
            $email = !empty($_POST['email']) ? SecurityManager::sanitizeInput($_POST['email']) : null;
            $telephone = SecurityManager::sanitizeInput($_POST['telephone']);
            $adresse = !empty($_POST['adresse']) ? SecurityManager::sanitizeInput($_POST['adresse']) : null;
            $numCompte = SecurityManager::sanitizeInput($_POST['num_compte']);
            $idTypeCompte = intval($_POST['id_type_compte']);
            $idAgenceOrigine = intval($_POST['id_agence_origine']);
            $soldeInitial = !empty($_POST['solde_initial']) ? floatval($_POST['solde_initial']) : 0;
            
            // Récupération des informations du caissier pour vérifier l'agence
            $stmt = $db->prepare("SELECT id_agence FROM agents WHERE id_agent = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $caissierInfo = $stmt->fetch();
            
            // Vérification que l'agence sélectionnée est bien celle de l'utilisateur
            if ($idAgenceOrigine != $caissierInfo['id_agence']) {
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez créer des comptes que pour votre agence']);
                exit();
            }
            
            // Gestion de l'upload de photo
            $photo_profil = null;
            if (!empty($_FILES['photo_profil']['name'])) {
                $uploadResult = uploadProfilePhoto($_FILES['photo_profil']);
                if ($uploadResult['success']) {
                    $photo_profil = $uploadResult['filename'];
                } else {
                    echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                    exit();
                }
            }
            
            // Vérification de l'unicité du numéro de compte
            $stmt = $db->prepare("SELECT COUNT(*) FROM comptes WHERE num_compte = ?");
            $stmt->execute([$numCompte]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ce numéro de compte existe déjà']);
                exit();
            }
            
            // Vérification que le type de compte existe
            $stmt = $db->prepare("SELECT * FROM types_compte WHERE id_type_compte = ? AND actif = 1");
            $stmt->execute([$idTypeCompte]);
            $typeCompte = $stmt->fetch();
            
            if (!$typeCompte) {
                echo json_encode(['success' => false, 'message' => 'Type de compte invalide']);
                exit();
            }
            
            // Vérification du solde minimum
            if ($soldeInitial > 0 && $soldeInitial < $typeCompte['solde_minimum']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Le dépôt initial doit être d\'au moins ' . number_format($typeCompte['solde_minimum'], 0, ',', ' ') . ' BIF'
                ]);
                exit();
            }
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // 1. Création du client
                $stmt = $db->prepare("
                    INSERT INTO clients (nom, prenom, email, telephone, adresse, photo_profil, actif)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $photo_profil]);
                $idClient = $db->lastInsertId();
                
                // 2. Création du compte
                $stmt = $db->prepare("
                    INSERT INTO comptes (
                        num_compte, id_client, id_type_compte, id_agence_origine,
                        solde, solde_disponible, statut
                    ) VALUES (?, ?, ?, ?, ?, ?, 'Actif')
                ");
                $stmt->execute([
                    $numCompte,
                    $idClient,
                    $idTypeCompte,
                    $idAgenceOrigine,
                    $soldeInitial,
                    $soldeInitial
                ]);
                $idCompte = $db->lastInsertId();
                
                // 3. Si dépôt initial, créer une transaction de dépôt
                if ($soldeInitial > 0) {
                    // Récupérer le type de transaction "Dépôt en espèces"
                    $stmt = $db->prepare("
                        SELECT id_type_transaction FROM types_transaction 
                        WHERE code = 'DEPOT_ESPECE' AND actif = 1 
                        LIMIT 1
                    ");
                    $stmt->execute();
                    $idTypeTransaction = $stmt->fetchColumn();
                    
                    if ($idTypeTransaction) {
                        $stmt = $db->prepare("
                            INSERT INTO transactions (
                                num_compte, id_type_transaction, id_agent,
                                montant, frais, montant_total, statut, description
                            ) VALUES (?, ?, ?, ?, 0, ?, 'Terminée', 'Dépôt initial à l\'ouverture du compte')
                        ");
                        $stmt->execute([
                            $numCompte,
                            $idTypeTransaction,
                            $_SESSION['user_id'],
                            $soldeInitial,
                            $soldeInitial
                        ]);
                        
                        logOperation($db, 'transactions', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
                    }
                }
                
                // Logs
                logOperation($db, 'clients', 'INSERT', $idClient, $_SESSION['user_id']);
                logOperation($db, 'comptes', 'INSERT', $idCompte, $_SESSION['user_id']);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte créé avec succès',
                    'id_client' => $idClient,
                    'id_compte' => $idCompte,
                    'num_compte' => $numCompte
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erreur création compte: " . $e->getMessage());
                throw $e;
            }
            break;
            
        // ========================================
        // VIREMENT ENTRE COMPTES - CORRIGÉ POUR LA DÉTECTION AUTOMATIQUE
        // ========================================
        case 'process_virement':
            // Validation des données
            $required = ['num_compte_source', 'num_compte_dest', 'id_type_transaction', 'montant'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $numCompteSource = SecurityManager::sanitizeInput($_POST['num_compte_source']);
            $numCompteDest = SecurityManager::sanitizeInput($_POST['num_compte_dest']);
            $idTypeTransaction = intval($_POST['id_type_transaction']);
            $montant = floatval($_POST['montant']);
            $description = !empty($_POST['description']) ? SecurityManager::sanitizeInput($_POST['description']) : null;
            
            // Vérification que les comptes sont différents
            if ($numCompteSource === $numCompteDest) {
                echo json_encode(['success' => false, 'message' => 'Les comptes source et destinataire doivent être différents']);
                exit();
            }
            
            // Vérification du compte source
            $stmt = $db->prepare("
                SELECT c.*, cl.actif as client_actif, ag.id_agence as agence_id
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
                WHERE c.num_compte = ?
            ");
            $stmt->execute([$numCompteSource]);
            $compteSource = $stmt->fetch();
            
            if (!$compteSource) {
                echo json_encode(['success' => false, 'message' => 'Compte source introuvable']);
                exit();
            }
            
            if ($compteSource['statut'] !== 'Actif') {
                echo json_encode(['success' => false, 'message' => 'Le compte source est ' . $compteSource['statut']]);
                exit();
            }
            
            // Vérification du compte destinataire
            $stmt = $db->prepare("
                SELECT c.*, cl.actif as client_actif, ag.id_agence as agence_id
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
                WHERE c.num_compte = ?
            ");
            $stmt->execute([$numCompteDest]);
            $compteDest = $stmt->fetch();
            
            if (!$compteDest) {
                echo json_encode(['success' => false, 'message' => 'Compte destinataire introuvable']);
                exit();
            }
            
            if ($compteDest['statut'] !== 'Actif') {
                echo json_encode(['success' => false, 'message' => 'Le compte destinataire est ' . $compteDest['statut']]);
                exit();
            }
            
            // Vérification automatique du type de virement basé sur les agences
            $memeAgence = ($compteSource['agence_id'] == $compteDest['agence_id']);
            
            // Récupération du type de transaction sélectionné
            $stmt = $db->prepare("
                SELECT * FROM types_transaction 
                WHERE id_type_transaction = ? 
                AND actif = 1
                AND categorie = 'VIREMENT'
            ");
            $stmt->execute([$idTypeTransaction]);
            $typeTransaction = $stmt->fetch();
            
            if (!$typeTransaction) {
                echo json_encode(['success' => false, 'message' => 'Type de virement invalide']);
                exit();
            }
            
            // Vérification de cohérence entre le type sélectionné et la réalité des agences
            $estVirementInterne = (strpos($typeTransaction['code'], 'INTERNE') !== false);
            
            if ($memeAgence && !$estVirementInterne) {
                echo json_encode(['success' => false, 'message' => 'Type de virement incohérent: les deux comptes sont de la même agence']);
                exit();
            }
            
            if (!$memeAgence && $estVirementInterne) {
                echo json_encode(['success' => false, 'message' => 'Type de virement incohérent: les comptes sont de différentes agences']);
                exit();
            }
            
            // Calcul des frais
            $frais = $typeTransaction['frais_fixe'] + ($montant * $typeTransaction['frais_pourcentage'] / 100);
            $montantTotal = $montant + $frais;
            
            // Vérification du solde du compte source
            if ($compteSource['solde_disponible'] < $montantTotal) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Solde insuffisant. Disponible: ' . number_format($compteSource['solde_disponible'], 0, ',', ' ') . ' BIF'
                ]);
                exit();
            }
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // 1. Débit du compte source
                $nouveauSoldeSource = $compteSource['solde'] - $montantTotal;
                $nouveauSoldeDisponibleSource = $compteSource['solde_disponible'] - $montantTotal;
                
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET solde = ?,
                        solde_disponible = ?,
                        date_derniere_operation = NOW()
                    WHERE num_compte = ?
                ");
                $stmt->execute([$nouveauSoldeSource, $nouveauSoldeDisponibleSource, $numCompteSource]);
                
                // 2. Crédit du compte destinataire
                $nouveauSoldeDest = $compteDest['solde'] + $montant;
                $nouveauSoldeDisponibleDest = $compteDest['solde_disponible'] + $montant;
                
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET solde = ?,
                        solde_disponible = ?,
                        date_derniere_operation = NOW()
                    WHERE num_compte = ?
                ");
                $stmt->execute([$nouveauSoldeDest, $nouveauSoldeDisponibleDest, $numCompteDest]);
                
                // 3. Insertion de la transaction de débit
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent,
                        montant, frais, montant_total, statut, description, date_heure
                    ) VALUES (?, ?, ?, ?, ?, ?, 'Terminée', ?, NOW())
                ");
                
                $stmt->execute([
                    $numCompteSource,
                    $idTypeTransaction,
                    $_SESSION['user_id'],
                    $montant,
                    $frais,
                    $montantTotal,
                    "Virement vers " . $numCompteDest . ($description ? " - " . $description : "")
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // 4. Insertion de la transaction de crédit pour le destinataire
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent,
                        montant, frais, montant_total, statut, description, date_heure
                    ) VALUES (?, ?, ?, ?, 0, ?, 'Terminée', ?, NOW())
                ");
                
                // Trouver le type de transaction pour virement entrant correspondant
                $typeVirementRecu = $memeAgence ? 'VIREMENT_INTERNE_RECU' : 'VIREMENT_EXTERNE_RECU';
                
                $stmtType = $db->prepare("
                    SELECT id_type_transaction FROM types_transaction 
                    WHERE code = ? AND actif = 1 
                    LIMIT 1
                ");
                $stmtType->execute([$typeVirementRecu]);
                $idTypeTransactionRecu = $stmtType->fetchColumn();
                
                if (!$idTypeTransactionRecu) {
                    // Fallback: utiliser le même type si le type réciproque n'existe pas
                    $idTypeTransactionRecu = $idTypeTransaction;
                }
                
                $stmt->execute([
                    $numCompteDest,
                    $idTypeTransactionRecu,
                    $_SESSION['user_id'],
                    $montant,
                    $montant,
                    "Virement reçu de " . $numCompteSource . ($description ? " - " . $description : "")
                ]);
                
                // Logs des opérations
                logOperation($db, 'transactions', 'INSERT', $transactionId, $_SESSION['user_id']);
                logOperation($db, 'transactions', 'INSERT', $db->lastInsertId(), $_SESSION['user_id']);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Virement effectué avec succès',
                    'transaction_id' => $transactionId,
                    'nouveau_solde_source' => $nouveauSoldeSource,
                    'type_virement' => $memeAgence ? 'interne' : 'externe'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erreur virement: " . $e->getMessage());
                throw $e;
            }
            break;
            
        // ========================================
        // SITUATION DE COMPTE - TRANSACTIONS RÉCENTES
        // ========================================
        case 'get_recent_transactions':
            $numCompte = SecurityManager::sanitizeInput($_POST['num_compte']);
            
            if (empty($numCompte)) {
                echo json_encode(['success' => false, 'message' => 'Numéro de compte requis']);
                exit();
            }
            
            // Récupérer les 10 dernières transactions
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    tt.libelle as type_libelle,
                    tt.sens,
                    tt.categorie,
                    (SELECT solde FROM transactions t2 
                     WHERE t2.num_compte = t.num_compte 
                     AND t2.date_heure <= t.date_heure 
                     ORDER BY t2.date_heure DESC, t2.id_transaction DESC 
                     LIMIT 1) as solde_apres
                FROM transactions t
                JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
                WHERE t.num_compte = ?
                ORDER BY t.date_heure DESC, t.id_transaction DESC
                LIMIT 10
            ");
            
            $stmt->execute([$numCompte]);
            $transactions = $stmt->fetchAll();
            
            // Formater les dates pour l'affichage
            foreach ($transactions as &$transaction) {
                $transaction['date_heure'] = date('d/m/Y H:i', strtotime($transaction['date_heure']));
                $transaction['montant'] = floatval($transaction['montant']);
                $transaction['solde_apres'] = floatval($transaction['solde_apres']);
            }
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
            break;
            
        // ========================================
        // GÉNÉRATION D'HISTORIQUE AVEC FRAIS - CORRIGÉ
        // ========================================
        case 'generate_account_history':
            // Validation des données
            $required = ['num_compte', 'period'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
                    exit();
                }
            }
            
            $numCompte = SecurityManager::sanitizeInput($_POST['num_compte']);
            $period = SecurityManager::sanitizeInput($_POST['period']);
            $frais = 1000; // Frais fixes de 1,000 BIF
            $startDate = !empty($_POST['start_date']) ? SecurityManager::sanitizeInput($_POST['start_date']) : null;
            $endDate = !empty($_POST['end_date']) ? SecurityManager::sanitizeInput($_POST['end_date']) : null;
            
            // Vérification du compte
            $stmt = $db->prepare("
                SELECT c.*, cl.nom, cl.prenom, tc.libelle as type_compte
                FROM comptes c
                INNER JOIN clients cl ON c.id_client = cl.id_client
                INNER JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
                WHERE c.num_compte = ?
            ");
            $stmt->execute([$numCompte]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                echo json_encode(['success' => false, 'message' => 'Compte introuvable']);
                exit();
            }
            
            // Vérification du solde pour les frais
            if ($compte['solde_disponible'] < $frais) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Solde insuffisant pour payer les frais de génération (1,000 BIF)'
                ]);
                exit();
            }
            
            // Récupérer le type de transaction pour frais d'historique
            $stmt = $db->prepare("
                SELECT id_type_transaction FROM types_transaction 
                WHERE code = 'FRAIS_HISTORIQUE' AND actif = 1 
                LIMIT 1
            ");
            $stmt->execute();
            $idTypeTransaction = $stmt->fetchColumn();
            
            if (!$idTypeTransaction) {
                // Créer le type de transaction si inexistant
                try {
                    $stmt = $db->prepare("
                        INSERT INTO types_transaction (code, libelle, categorie, sens, frais_fixe, actif)
                        VALUES ('FRAIS_HISTORIQUE', 'Frais génération historique', 'FRAIS', 'DEBIT', 1000, 1)
                    ");
                    $stmt->execute();
                    $idTypeTransaction = $db->lastInsertId();
                } catch (Exception $e) {
                    // Si l'insertion échoue, utiliser un type de frais générique
                    $stmt = $db->prepare("
                        SELECT id_type_transaction FROM types_transaction 
                        WHERE categorie = 'FRAIS' AND actif = 1 
                        LIMIT 1
                    ");
                    $stmt->execute();
                    $idTypeTransaction = $stmt->fetchColumn();
                    
                    if (!$idTypeTransaction) {
                        echo json_encode(['success' => false, 'message' => 'Type de transaction pour frais non disponible']);
                        exit();
                    }
                }
            }
            
            // Début de transaction SQL
            $db->beginTransaction();
            
            try {
                // 1. Débiter les frais
                $nouveauSolde = $compte['solde'] - $frais;
                $nouveauSoldeDisponible = $compte['solde_disponible'] - $frais;
                
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET solde = ?,
                        solde_disponible = ?,
                        date_derniere_operation = NOW()
                    WHERE num_compte = ?
                ");
                $stmt->execute([$nouveauSolde, $nouveauSoldeDisponible, $numCompte]);
                
                // 2. Enregistrer la transaction de frais
                $stmt = $db->prepare("
                    INSERT INTO transactions (
                        num_compte, id_type_transaction, id_agent,
                        montant, frais, montant_total, statut, description, date_heure
                    ) VALUES (?, ?, ?, ?, 0, ?, 'Terminée', 'Frais de génération d\\'historique de compte', NOW())
                ");
                
                $stmt->execute([
                    $numCompte,
                    $idTypeTransaction,
                    $_SESSION['user_id'],
                    $frais,
                    $frais
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // 3. Générer l'historique (dans un vrai système, on générerait un PDF)
                $historiqueData = generateAccountHistory($db, $numCompte, $period, $startDate, $endDate);
                
                // 4. Log de l'opération
                logOperation($db, 'transactions', 'INSERT', $transactionId, $_SESSION['user_id']);
                
                $db->commit();
                
               // Dans la section generate_account_history, après le commit réussi :
$downloadUrl = "historique_generer_pdf.php?compte=" . urlencode($numCompte) . 
              "&periode=" . urlencode($period) . 
              "&transaction_id=" . $transactionId;

if ($startDate && $endDate) {
    $downloadUrl .= "&start_date=" . urlencode($startDate) . "&end_date=" . urlencode($endDate);
}
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Historique généré avec succès. Frais de 1,000 BIF appliqués.',
                    'transaction_id' => $transactionId,
                    'download_url' => $downloadUrl,
                    'nouveau_solde' => $nouveauSolde,
                    'historique' => $historiqueData
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erreur génération historique: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération de l\'historique: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        // ========================================
        // DÉTECTION AUTOMATIQUE DU TYPE DE VIREMENT
        // ========================================
        case 'detect_virement_type':
            $numCompteSource = SecurityManager::sanitizeInput($_POST['num_compte_source']);
            $numCompteDest = SecurityManager::sanitizeInput($_POST['num_compte_dest']);
            
            if (empty($numCompteSource) || empty($numCompteDest)) {
                echo json_encode(['success' => false, 'message' => 'Numéros de compte requis']);
                exit();
            }
            
            // Récupérer les agences des deux comptes
            $stmt = $db->prepare("
                SELECT c.num_compte, ag.id_agence, ag.nom as agence_nom
                FROM comptes c
                INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
                WHERE c.num_compte IN (?, ?)
            ");
            $stmt->execute([$numCompteSource, $numCompteDest]);
            $comptes = $stmt->fetchAll();
            
            if (count($comptes) !== 2) {
                echo json_encode(['success' => false, 'message' => 'Un ou plusieurs comptes introuvables']);
                exit();
            }
            
            $agenceSource = null;
            $agenceDest = null;
            
            foreach ($comptes as $compte) {
                if ($compte['num_compte'] == $numCompteSource) {
                    $agenceSource = $compte;
                } else if ($compte['num_compte'] == $numCompteDest) {
                    $agenceDest = $compte;
                }
            }
            
            $memeAgence = ($agenceSource['id_agence'] == $agenceDest['id_agence']);
            
            // Récupérer les types de virement disponibles
            $stmt = $db->prepare("
                SELECT * FROM types_transaction 
                WHERE categorie = 'VIREMENT' 
                AND actif = 1
                ORDER BY code
            ");
            $stmt->execute();
            $typesVirement = $stmt->fetchAll();
            
            // Déterminer le type de virement approprié
            $typeRecommandé = null;
            foreach ($typesVirement as $type) {
                if ($memeAgence && strpos($type['code'], 'INTERNE') !== false) {
                    $typeRecommandé = $type;
                    break;
                } else if (!$memeAgence && strpos($type['code'], 'EXTERNE') !== false) {
                    $typeRecommandé = $type;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'meme_agence' => $memeAgence,
                'type_recommande' => $typeRecommandé,
                'agence_source' => $agenceSource['agence_nom'],
                'agence_dest' => $agenceDest['agence_nom'],
                'types_disponibles' => $typesVirement
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
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur PDO dans ajax_caissier.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur dans ajax_caissier.php: " . $e->getMessage());
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

// ========================================
// FONCTION HELPER - UPLOAD PHOTO PROFIL
// ========================================
function uploadProfilePhoto($file) {
    $uploadDir = 'assets/uploads/profiles/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Vérifications de sécurité
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF'];
    }
    
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'Fichier trop volumineux. Taille maximale: 2MB'];
    }
    
    // Vérifier que c'est bien une image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        return ['success' => false, 'message' => 'Le fichier n\'est pas une image valide'];
    }
    
    // Générer un nom de fichier unique
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $filename;
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filePath];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier'];
    }
}

// ========================================
// FONCTION HELPER - GÉNÉRATION HISTORIQUE
// ========================================
function generateAccountHistory($db, $numCompte, $period, $startDate = null, $endDate = null) {
    // Construire la requête selon la période
    $whereConditions = ["t.num_compte = ?"];
    $params = [$numCompte];
    
    switch ($period) {
        case '7':
            $whereConditions[] = "t.date_heure >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30':
            $whereConditions[] = "t.date_heure >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90':
            $whereConditions[] = "t.date_heure >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $whereConditions[] = "DATE(t.date_heure) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }
            break;
        // 'all' ne nécessite pas de condition de date
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    // Récupérer l'historique des transactions
    $stmt = $db->prepare("
        SELECT 
            t.*,
            tt.libelle as type_libelle,
            tt.sens,
            tt.categorie,
            a.first_name as agent_nom,
            a.last_name as agent_prenom
        FROM transactions t
        JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        LEFT JOIN agents a ON t.id_agent = a.id_agent
        WHERE $whereClause
        ORDER BY t.date_heure DESC, t.id_transaction DESC
    ");
    
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Récupérer les informations du compte
    $stmt = $db->prepare("
        SELECT 
            c.*,
            cl.nom as client_nom,
            cl.prenom as client_prenom,
            tc.libelle as type_compte,
            ag.nom as agence_nom
        FROM comptes c
        INNER JOIN clients cl ON c.id_client = cl.id_client
        INNER JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
        INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
        WHERE c.num_compte = ?
    ");
    $stmt->execute([$numCompte]);
    $compte = $stmt->fetch();
    
    return [
        'compte' => $compte,
        'transactions' => $transactions,
        'periode' => $period,
        'date_generation' => date('d/m/Y H:i:s'),
        'total_transactions' => count($transactions)
    ];
}
?>