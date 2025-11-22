<?php
/**
 * ajax_conseiller_actions.php
 * Gestion des actions AJAX pour le Dashboard Conseiller
 * Toutes les fonctionnalités pour la gestion des clients, comptes et crédits
 */

require_once 'config.php';

// Vérification de session et du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Conseiller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérification du token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit();
}

// Récupération de la connexion
$db = DatabaseConfig::getConnection();
$conseiller_id = $_SESSION['user_id'];

// Récupération de l'action
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ========================================
        // GESTION DES CLIENTS
        // ========================================
        
        case 'add_client':
            // Validation des données
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $adresse = trim($_POST['adresse'] ?? '');
            $revenu_mensuel = floatval($_POST['revenu_mensuel'] ?? 0);
            $score_credit = intval($_POST['score_credit'] ?? 600);
            
            if (empty($nom) || empty($prenom)) {
                throw new Exception("Le nom et le prénom sont obligatoires");
            }
            
            if (empty($telephone)) {
                throw new Exception("Le téléphone est obligatoire");
            }
            
            // Vérification de l'unicité de l'email
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cet email est déjà utilisé");
                }
            }
            
            // Gestion de l'upload de photo
            $photo_profil = null;
            if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/clients/';
                
                // Créer le dossier s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file = $_FILES['photo_profil'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    throw new Exception("Format de fichier non autorisé. Utilisez JPG, PNG ou GIF");
                }
                
                if ($file['size'] > 2 * 1024 * 1024) {
                    throw new Exception("La photo ne doit pas dépasser 2MB");
                }
                
                // Générer un nom unique
                $new_filename = 'client_' . uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $photo_profil = $upload_path;
                } else {
                    throw new Exception("Erreur lors de l'upload de la photo");
                }
            }
            
            // Insertion du client
            $stmt = $db->prepare("
                INSERT INTO clients (nom, prenom, email, telephone, adresse, photo_profil, revenu_mensuel, score_credit, actif)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $nom,
                $prenom,
                $email ?: null,
                $telephone,
                $adresse ?: null,
                $photo_profil,
                $revenu_mensuel,
                $score_credit
            ]);
            
            $client_id = $db->lastInsertId();
            
            // Log de l'opération
            $stmt = $db->prepare("
                INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                VALUES (?, 'CREATE_CLIENT', 'clients', ?, ?)
            ");
            $stmt->execute([
                $conseiller_id,
                $client_id,
                json_encode(['nom' => $nom, 'prenom' => $prenom])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Client créé avec succès',
                'client_id' => $client_id
            ]);
            break;
            
        case 'get_client_details':
            $client_id = intval($_POST['id'] ?? 0);
            
            if ($client_id <= 0) {
                throw new Exception("ID client invalide");
            }
            
            // Récupérer les infos du client
            $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                throw new Exception("Client non trouvé");
            }
            
            // Récupérer les comptes du client
            $stmt = $db->prepare("
                SELECT c.*, tc.libelle as type_compte
                FROM comptes c
                LEFT JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
                WHERE c.id_client = ?
                ORDER BY c.date_creation DESC
            ");
            $stmt->execute([$client_id]);
            $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les crédits du client
            $stmt = $db->prepare("
                SELECT dc.*, tc.nom as type_credit
                FROM demandes_credit dc
                LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit
                WHERE dc.id_client = ?
                ORDER BY dc.date_demande DESC
            ");
            $stmt->execute([$client_id]);
            $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'client' => $client,
                    'comptes' => $comptes,
                    'credits' => $credits
                ]
            ]);
            break;
            
        // ========================================
        // GESTION DES COMPTES
        // ========================================
        
        case 'open_compte':
            $id_client = intval($_POST['id_client'] ?? 0);
            $id_type_compte = intval($_POST['id_type_compte'] ?? 0);
            $depot_initial = floatval($_POST['depot_initial'] ?? 0);
            
            if ($id_client <= 0 || $id_type_compte <= 0) {
                throw new Exception("Données invalides");
            }
            
            // Vérifier que le client existe et est actif
            $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ? AND actif = 1");
            $stmt->execute([$id_client]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                throw new Exception("Client non trouvé ou inactif");
            }
            
            // Vérifier le type de compte
            $stmt = $db->prepare("SELECT * FROM types_compte WHERE id_type_compte = ? AND actif = 1");
            $stmt->execute([$id_type_compte]);
            $type_compte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$type_compte) {
                throw new Exception("Type de compte invalide");
            }
            
            // Vérifier le solde minimum
            if ($depot_initial < $type_compte['solde_minimum']) {
                throw new Exception("Le dépôt initial doit être d'au moins " . number_format($type_compte['solde_minimum'], 0, ',', ' ') . " BIF");
            }
            
            $db->beginTransaction();
            
            try {
                // Générer un numéro de compte unique
                $num_compte = generateNumeroCompte($db);
                
                // Créer le compte
                $stmt = $db->prepare("
                    INSERT INTO comptes (num_compte, id_client, id_type_compte, solde, solde_disponible, statut, date_creation)
                    VALUES (?, ?, ?, ?, ?, 'Actif', NOW())
                ");
                
                $stmt->execute([
                    $num_compte,
                    $id_client,
                    $id_type_compte,
                    $depot_initial,
                    $depot_initial
                ]);
                
                $compte_id = $db->lastInsertId();
                
                // Créer une transaction pour le dépôt initial
                $stmt = $db->prepare("
                    SELECT id_type_transaction FROM types_transaction 
                    WHERE code = 'DEPOT_ESPECE' AND actif = 1 LIMIT 1
                ");
                $stmt->execute();
                $type_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($type_transaction) {
                    $stmt = $db->prepare("
                        INSERT INTO transactions (num_compte, id_type_transaction, id_agent, montant, frais, montant_total, statut, date_heure, description)
                        VALUES (?, ?, ?, ?, 0, ?, 'Terminée', NOW(), 'Dépôt initial à l\'ouverture du compte')
                    ");
                    
                    $stmt->execute([
                        $num_compte,
                        $type_transaction['id_type_transaction'],
                        $conseiller_id,
                        $depot_initial,
                        $depot_initial
                    ]);
                }
                
                // Log de l'opération
                $stmt = $db->prepare("
                    INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                    VALUES (?, 'OPEN_COMPTE', 'comptes', ?, ?)
                ");
                $stmt->execute([
                    $conseiller_id,
                    $compte_id,
                    json_encode(['num_compte' => $num_compte, 'client_id' => $id_client, 'depot_initial' => $depot_initial])
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte ouvert avec succès',
                    'num_compte' => $num_compte,
                    'compte_id' => $compte_id
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'suspend_compte':
            $id_compte = intval($_POST['id_compte'] ?? 0);
            $motif_suspension = trim($_POST['motif_suspension'] ?? '');
            
            if ($id_compte <= 0) {
                throw new Exception("ID compte invalide");
            }
            
            if (empty($motif_suspension)) {
                throw new Exception("Le motif de suspension est obligatoire");
            }
            
            // Vérifier que le compte existe et est actif
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id_compte = ? AND statut = 'Actif'");
            $stmt->execute([$id_compte]);
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$compte) {
                throw new Exception("Compte non trouvé ou déjà suspendu");
            }
            
            $db->beginTransaction();
            
            try {
                // Suspendre le compte
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET statut = 'Suspendu', motif_suspension = ?
                    WHERE id_compte = ?
                ");
                $stmt->execute([$motif_suspension, $id_compte]);
                
                // Log de l'opération
                $stmt = $db->prepare("
                    INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                    VALUES (?, 'SUSPEND_COMPTE', 'comptes', ?, ?)
                ");
                $stmt->execute([
                    $conseiller_id,
                    $id_compte,
                    json_encode(['num_compte' => $compte['num_compte'], 'motif' => $motif_suspension])
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte suspendu avec succès'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'close_compte':
            $id_compte = intval($_POST['id_compte'] ?? 0);
            $motif_fermeture = trim($_POST['motif_fermeture'] ?? '');
            
            if ($id_compte <= 0) {
                throw new Exception("ID compte invalide");
            }
            
            if (empty($motif_fermeture)) {
                throw new Exception("Le motif de fermeture est obligatoire");
            }
            
            // Vérifier que le compte existe et est actif
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id_compte = ?");
            $stmt->execute([$id_compte]);
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$compte) {
                throw new Exception("Compte non trouvé");
            }
            
            if ($compte['statut'] === 'Fermé') {
                throw new Exception("Ce compte est déjà fermé");
            }
            
            // Vérifier qu'il n'y a pas de crédit actif lié à ce compte
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM dossiers_credit 
                WHERE num_compte = ? AND statut = 'Actif'
            ");
            $stmt->execute([$compte['num_compte']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Impossible de fermer ce compte : il a des crédits actifs");
            }
            
            // Vérifier le solde
            if ($compte['solde'] != 0) {
                throw new Exception("Le solde du compte doit être à zéro avant la fermeture (Solde actuel: " . number_format($compte['solde'], 0, ',', ' ') . " BIF)");
            }
            
            $db->beginTransaction();
            
            try {
                // Fermer le compte
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET statut = 'Fermé', motif_suspension = ?, date_fermeture = NOW()
                    WHERE id_compte = ?
                ");
                $stmt->execute([$motif_fermeture, $id_compte]);
                
                // Log de l'opération
                $stmt = $db->prepare("
                    INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                    VALUES (?, 'CLOSE_COMPTE', 'comptes', ?, ?)
                ");
                $stmt->execute([
                    $conseiller_id,
                    $id_compte,
                    json_encode(['num_compte' => $compte['num_compte'], 'motif' => $motif_fermeture])
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte fermé avec succès'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'activate_compte':
            $id_compte = intval($_POST['id_compte'] ?? 0);
            
            if ($id_compte <= 0) {
                throw new Exception("ID compte invalide");
            }
            
            // Vérifier que le compte existe et est suspendu
            $stmt = $db->prepare("SELECT * FROM comptes WHERE id_compte = ? AND statut = 'Suspendu'");
            $stmt->execute([$id_compte]);
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$compte) {
                throw new Exception("Compte non trouvé ou non suspendu");
            }
            
            $db->beginTransaction();
            
            try {
                // Réactiver le compte
                $stmt = $db->prepare("
                    UPDATE comptes 
                    SET statut = 'Actif', motif_suspension = NULL
                    WHERE id_compte = ?
                ");
                $stmt->execute([$id_compte]);
                
                // Log de l'opération
                $stmt = $db->prepare("
                    INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                    VALUES (?, 'ACTIVATE_COMPTE', 'comptes', ?, ?)
                ");
                $stmt->execute([
                    $conseiller_id,
                    $id_compte,
                    json_encode(['num_compte' => $compte['num_compte']])
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte réactivé avec succès'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        // ========================================
        // GESTION DES CRÉDITS
        // ========================================
        
        case 'add_demande_credit':
            $id_client = intval($_POST['id_client'] ?? 0);
            $id_type_credit = intval($_POST['id_type_credit'] ?? 0);
            $montant = floatval($_POST['montant'] ?? 0);
            $duree_mois = intval($_POST['duree_mois'] ?? 0);
            
            if ($id_client <= 0 || $id_type_credit <= 0 || $montant <= 0 || $duree_mois <= 0) {
                throw new Exception("Données invalides");
            }
            
            // Vérifier le client
            $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ? AND actif = 1");
            $stmt->execute([$id_client]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                throw new Exception("Client non trouvé ou inactif");
            }
            
            // Vérifier le type de crédit
            $stmt = $db->prepare("SELECT * FROM types_credit WHERE id_type_credit = ?");
            $stmt->execute([$id_type_credit]);
            $type_credit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$type_credit) {
                throw new Exception("Type de crédit invalide");
            }
            
            // Validations
            if ($montant < $type_credit['montant_min']) {
                throw new Exception("Le montant minimum pour ce type de crédit est " . number_format($type_credit['montant_min'], 0, ',', ' ') . " BIF");
            }
            
            if ($montant > $type_credit['montant_max']) {
                throw new Exception("Le montant maximum pour ce type de crédit est " . number_format($type_credit['montant_max'], 0, ',', ' ') . " BIF");
            }
            
            if ($duree_mois > $type_credit['duree_max_mois']) {
                throw new Exception("La durée maximale pour ce type de crédit est " . $type_credit['duree_max_mois'] . " mois");
            }
            
            // Vérifier le score crédit (recommandation)
            if ($client['score_credit'] < 500) {
                // On permet quand même la demande mais on la met en étude
                $statut_initial = 'En étude';
            } else {
                $statut_initial = 'En attente';
            }
            
            $db->beginTransaction();
            
            try {
                // Créer la demande
                $stmt = $db->prepare("
                    INSERT INTO demandes_credit (id_client, id_type_credit, id_agent, montant, duree_mois, statut, date_demande)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $id_client,
                    $id_type_credit,
                    $conseiller_id,
                    $montant,
                    $duree_mois,
                    $statut_initial
                ]);
                
                $demande_id = $db->lastInsertId();
                
                // Log de l'opération
                $stmt = $db->prepare("
                    INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
                    VALUES (?, 'CREATE_DEMANDE_CREDIT', 'demandes_credit', ?, ?)
                ");
                $stmt->execute([
                    $conseiller_id,
                    $demande_id,
                    json_encode(['client_id' => $id_client, 'montant' => $montant, 'duree' => $duree_mois])
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Demande de crédit soumise avec succès',
                    'demande_id' => $demande_id,
                    'statut' => $statut_initial
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'get_demande_credit_details':
            $demande_id = intval($_POST['id'] ?? 0);
            
            if ($demande_id <= 0) {
                throw new Exception("ID demande invalide");
            }
            
            $stmt = $db->prepare("
                SELECT dc.*, 
                       cl.nom as client_nom, cl.prenom as client_prenom, cl.score_credit, cl.revenu_mensuel,
                       tc.nom as type_credit_nom, tc.taux_interet, tc.duree_max_mois,
                       a.first_name as agent_nom, a.last_name as agent_prenom
                FROM demandes_credit dc
                LEFT JOIN clients cl ON dc.id_client = cl.id_client
                LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit
                LEFT JOIN agents a ON dc.id_agent = a.id_agent
                WHERE dc.id_demande = ?
            ");
            $stmt->execute([$demande_id]);
            $demande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$demande) {
                throw new Exception("Demande non trouvée");
            }
            
            echo json_encode([
                'success' => true,
                'data' => $demande
            ]);
            break;
            
        case 'get_echeancier':
            $id_dossier = intval($_POST['id_dossier'] ?? 0);
            
            if ($id_dossier <= 0) {
                throw new Exception("ID dossier invalide");
            }
            
            // Récupérer les échéances
            $stmt = $db->prepare("
                SELECT * FROM echeances_credit
                WHERE id_dossier = ?
                ORDER BY numero_echeance ASC
            ");
            $stmt->execute([$id_dossier]);
            $echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les infos du dossier
            $stmt = $db->prepare("
                SELECT dcr.*, dc.montant, dc.duree_mois,
                       cl.nom as client_nom, cl.prenom as client_prenom,
                       tc.nom as type_credit_nom, tc.taux_interet
                FROM dossiers_credit dcr
                LEFT JOIN demandes_credit dc ON dcr.id_demande = dc.id_demande
                LEFT JOIN clients cl ON dc.id_client = cl.id_client
                LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit
                WHERE dcr.id_dossier = ?
            ");
            $stmt->execute([$id_dossier]);
            $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dossier) {
                throw new Exception("Dossier non trouvé");
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'dossier' => $dossier,
                    'echeances' => $echeances
                ]
            ]);
            break;
            
        // ========================================
        // HISTORIQUE
        // ========================================
        
        case 'get_historique':
            $type = $_POST['type'] ?? '';
            $date_debut = $_POST['date_debut'] ?? '';
            $date_fin = $_POST['date_fin'] ?? '';
            
            $where = ["1=1"];
            $params = [];
            
            // Filtre par type
            if (!empty($type)) {
                $where[] = "action LIKE ?";
                $params[] = '%' . strtoupper($type) . '%';
            }
            
            // Filtre par date
            if (!empty($date_debut)) {
                $where[] = "DATE(date_heure) >= ?";
                $params[] = $date_debut;
            }
            
            if (!empty($date_fin)) {
                $where[] = "DATE(date_heure) <= ?";
                $params[] = $date_fin;
            }
            
            // Limiter aux 30 derniers jours si aucune date n'est spécifiée
            if (empty($date_debut) && empty($date_fin)) {
                $where[] = "date_heure >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
            
            $sql = "
                SELECT al.*, a.username as agent_username
                FROM audit_log al
                LEFT JOIN agents a ON al.id_agent = a.id_agent
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.date_heure DESC
                LIMIT 100
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formater les données pour l'affichage
            $formatted_logs = array_map(function($log) {
                $action_labels = [
                    'CREATE_CLIENT' => 'Nouveau client créé',
                    'OPEN_COMPTE' => 'Ouverture de compte',
                    'SUSPEND_COMPTE' => 'Suspension de compte',
                    'CLOSE_COMPTE' => 'Fermeture de compte',
                    'ACTIVATE_COMPTE' => 'Réactivation de compte',
                    'CREATE_DEMANDE_CREDIT' => 'Nouvelle demande de crédit'
                ];
                
                $details = json_decode($log['details'], true);
                $description = $action_labels[$log['action']] ?? $log['action'];
                
                // Ajouter des détails si disponibles
                $details_text = '';
                if (isset($details['num_compte'])) {
                    $details_text = 'Compte: ' . $details['num_compte'];
                } elseif (isset($details['nom']) && isset($details['prenom'])) {
                    $details_text = 'Client: ' . $details['nom'] . ' ' . $details['prenom'];
                }
                
                // Déterminer le type pour l'icône
                $type = 'client';
                if (strpos($log['action'], 'COMPTE') !== false) {
                    $type = 'compte';
                } elseif (strpos($log['action'], 'CREDIT') !== false) {
                    $type = 'credit';
                }
                
                return [
                    'type' => $type,
                    'description' => $description,
                    'details' => $details_text,
                    'date' => $log['date_heure'],
                    'agent' => $log['agent_username']
                ];
            }, $logs);
            
            echo json_encode([
                'success' => true,
                'data' => $formatted_logs
            ]);
            break;
            
        default:
            throw new Exception("Action non reconnue: " . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log de l'erreur
    error_log("Erreur dans ajax_conseiller_actions.php - Action: $action - Erreur: " . $e->getMessage());
}

// ========================================
// FONCTIONS UTILITAIRES
// ========================================

/**
 * Génère un numéro de compte unique
 */
function generateNumeroCompte($db) {
    $prefix = 'FEN';
    $max_attempts = 10;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        // Générer un numéro: FEN + YYYYMMDD + 6 chiffres aléatoires
        $date_part = date('Ymd');
        $random_part = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $num_compte = $prefix . $date_part . $random_part;
        
        // Vérifier l'unicité
        $stmt = $db->prepare("SELECT COUNT(*) FROM comptes WHERE num_compte = ?");
        $stmt->execute([$num_compte]);
        
        if ($stmt->fetchColumn() == 0) {
            return $num_compte;
        }
    }
    
    // Si on n'a pas réussi à générer un numéro unique après max_attempts
    throw new Exception("Impossible de générer un numéro de compte unique");
}

/**
 * Calcule la mensualité d'un crédit
 */
function calculateMensualite($montant, $taux_annuel, $duree_mois) {
    $taux_mensuel = $taux_annuel / 100 / 12;
    
    if ($taux_mensuel == 0) {
        return $montant / $duree_mois;
    }
    
    $mensualite = $montant * ($taux_mensuel * pow(1 + $taux_mensuel, $duree_mois)) / (pow(1 + $taux_mensuel, $duree_mois) - 1);
    
    return round($mensualite, 2);
}

/**
 * Valide le format d'un email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide le format d'un numéro de téléphone burundais
 */
function isValidPhone($phone) {
    // Format burundais: commence généralement par +257 ou 257
    // Accepter différents formats
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Vérifier si c'est un numéro valide (8 chiffres minimum)
    if (preg_match('/^\+?257?\d{8,}$/', $phone)) {
        return true;
    }
    
    return strlen($phone) >= 8 && ctype_digit($phone);
}

/**
 * Nettoie et sécurise une entrée utilisateur
 */
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Vérifie si un fichier uploadé est une image valide
 */
function isValidImage($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types);
}

/**
 * Crée un dossier de crédit avec échéancier
 */
function createDossierCredit($db, $demande_id, $num_compte, $conseiller_id) {
    // Récupérer les infos de la demande
    $stmt = $db->prepare("
        SELECT dc.*, tc.taux_interet
        FROM demandes_credit dc
        LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit
        WHERE dc.id_demande = ?
    ");
    $stmt->execute([$demande_id]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$demande) {
        throw new Exception("Demande de crédit non trouvée");
    }
    
    $montant = $demande['montant'];
    $duree_mois = $demande['duree_mois'];
    $taux_annuel = $demande['taux_interet'];
    
    // Calculer la mensualité
    $mensualite = calculateMensualite($montant, $taux_annuel, $duree_mois);
    $montant_total = $mensualite * $duree_mois;
    
    // Dates
    $date_approbation = date('Y-m-d');
    $date_premier_remboursement = date('Y-m-d', strtotime('+1 month'));
    $date_dernier_remboursement = date('Y-m-d', strtotime('+' . $duree_mois . ' months'));
    
    // Créer le dossier de crédit
    $stmt = $db->prepare("
        INSERT INTO dossiers_credit (id_demande, num_compte, date_approbation, date_premier_remboursement, 
                                     date_dernier_remboursement, montant_total_du, statut)
        VALUES (?, ?, ?, ?, ?, ?, 'Actif')
    ");
    
    $stmt->execute([
        $demande_id,
        $num_compte,
        $date_approbation,
        $date_premier_remboursement,
        $date_dernier_remboursement,
        $montant_total
    ]);
    
    $dossier_id = $db->lastInsertId();
    
    // Créer l'échéancier
    $taux_mensuel = $taux_annuel / 100 / 12;
    $capital_restant = $montant;
    
    for ($i = 1; $i <= $duree_mois; $i++) {
        $date_echeance = date('Y-m-d', strtotime($date_premier_remboursement . ' +' . ($i - 1) . ' months'));
        
        // Calculer les intérêts pour ce mois
        $montant_interet = $capital_restant * $taux_mensuel;
        $montant_capital = $mensualite - $montant_interet;
        
        // Ajuster le dernier mois pour éviter les erreurs d'arrondi
        if ($i == $duree_mois) {
            $montant_capital = $capital_restant;
            $montant_total_echeance = $montant_capital + $montant_interet;
        } else {
            $montant_total_echeance = $mensualite;
        }
        
        $stmt = $db->prepare("
            INSERT INTO echeances_credit (id_dossier, numero_echeance, date_echeance, 
                                          montant_capital, montant_interet, montant_total, statut)
            VALUES (?, ?, ?, ?, ?, ?, 'A payer')
        ");
        
        $stmt->execute([
            $dossier_id,
            $i,
            $date_echeance,
            round($montant_capital, 2),
            round($montant_interet, 2),
            round($montant_total_echeance, 2)
        ]);
        
        $capital_restant -= $montant_capital;
    }
    
    // Mettre à jour le statut de la demande
    $stmt = $db->prepare("
        UPDATE demandes_credit 
        SET statut = 'Approuvé', date_traitement = NOW()
        WHERE id_demande = ?
    ");
    $stmt->execute([$demande_id]);
    
    // Log de l'opération
    $stmt = $db->prepare("
        INSERT INTO audit_log (id_agent, action, table_name, record_id, details)
        VALUES (?, 'CREATE_DOSSIER_CREDIT', 'dossiers_credit', ?, ?)
    ");
    $stmt->execute([
        $conseiller_id,
        $dossier_id,
        json_encode([
            'demande_id' => $demande_id,
            'montant' => $montant,
            'duree' => $duree_mois,
            'mensualite' => $mensualite
        ])
    ]);
    
    return $dossier_id;
}

/**
 * Vérifie l'éligibilité d'un client pour un crédit
 */
function checkCreditEligibility($db, $client_id, $montant_demande) {
    // Récupérer les infos du client
    $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        return ['eligible' => false, 'raison' => 'Client non trouvé'];
    }
    
    // Vérifier le score crédit
    if ($client['score_credit'] < 450) {
        return ['eligible' => false, 'raison' => 'Score crédit insuffisant (minimum: 450)'];
    }
    
    // Vérifier les crédits en cours
    $stmt = $db->prepare("
        SELECT COUNT(*) as nb_credits, SUM(dc.montant) as total_credits
        FROM dossiers_credit dcr
        INNER JOIN demandes_credit dc ON dcr.id_demande = dc.id_demande
        WHERE dc.id_client = ? AND dcr.statut = 'Actif'
    ");
    $stmt->execute([$client_id]);
    $credits_actifs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Limiter à 3 crédits simultanés
    if ($credits_actifs['nb_credits'] >= 3) {
        return ['eligible' => false, 'raison' => 'Nombre maximum de crédits actifs atteint (3)'];
    }
    
    // Vérifier le ratio d'endettement (max 40% du revenu)
    if ($client['revenu_mensuel'] > 0) {
        $total_engagements = floatval($credits_actifs['total_credits']) + $montant_demande;
        $ratio_endettement = ($total_engagements / $client['revenu_mensuel']) * 100;
        
        if ($ratio_endettement > 40) {
            return [
                'eligible' => false, 
                'raison' => 'Ratio d\'endettement trop élevé (' . round($ratio_endettement, 2) . '%, max: 40%)'
            ];
        }
    }
    
    // Vérifier l'historique de paiement
    $stmt = $db->prepare("
        SELECT COUNT(*) as retards
        FROM echeances_credit ec
        INNER JOIN dossiers_credit dcr ON ec.id_dossier = dcr.id_dossier
        INNER JOIN demandes_credit dc ON dcr.id_demande = dc.id_demande
        WHERE dc.id_client = ? AND ec.statut = 'En retard'
    ");
    $stmt->execute([$client_id]);
    $historique = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($historique['retards'] > 2) {
        return [
            'eligible' => false, 
            'raison' => 'Historique de paiement défavorable (' . $historique['retards'] . ' retards)'
        ];
    }
    
    return ['eligible' => true, 'raison' => 'Client éligible'];
}

/**
 * Génère un rapport de solvabilité pour un client
 */
function generateSolvabilityReport($db, $client_id) {
    $stmt = $db->prepare("
        SELECT c.*,
               COUNT(DISTINCT co.id_compte) as nb_comptes,
               SUM(co.solde) as total_solde,
               COUNT(DISTINCT dcr.id_dossier) as nb_credits_actifs
        FROM clients c
        LEFT JOIN comptes co ON c.id_client = co.id_client AND co.statut = 'Actif'
        LEFT JOIN demandes_credit dc ON c.id_client = dc.id_client
        LEFT JOIN dossiers_credit dcr ON dc.id_demande = dcr.id_demande AND dcr.statut = 'Actif'
        WHERE c.id_client = ?
        GROUP BY c.id_client
    ");
    $stmt->execute([$client_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        return null;
    }
    
    // Calculer un score de solvabilité
    $score = $data['score_credit'];
    $score += ($data['nb_comptes'] * 10); // Bonus pour plusieurs comptes
    $score += min(($data['total_solde'] / 100000) * 5, 50); // Bonus pour l'épargne
    $score -= ($data['nb_credits_actifs'] * 15); // Malus pour les crédits multiples
    
    $score = max(0, min(999, $score)); // Limiter entre 0 et 999
    
    return [
        'score_final' => round($score),
        'score_base' => $data['score_credit'],
        'nb_comptes' => $data['nb_comptes'],
        'total_solde' => $data['total_solde'],
        'nb_credits_actifs' => $data['nb_credits_actifs'],
        'revenu_mensuel' => $data['revenu_mensuel'],
        'anciennete' => date_diff(date_create($data['created_at']), date_create('now'))->days,
        'recommendation' => $score >= 750 ? 'Excellent client' : 
                          ($score >= 650 ? 'Bon client' : 
                          ($score >= 550 ? 'Client acceptable' : 
                          ($score >= 450 ? 'Client à surveiller' : 'Client à risque')))
    ];
}

/**
 * Envoie une notification (pour extension future)
 */
function sendNotification($type, $recipient_id, $message) {
    // Cette fonction peut être étendue pour envoyer des emails, SMS, etc.
    // Pour l'instant, on enregistre juste dans les logs
    error_log("NOTIFICATION [$type] pour ID $recipient_id: $message");
    
    // TODO: Implémenter l'envoi réel de notifications
    // - Email via PHPMailer
    // - SMS via une API
    // - Notifications push
    
    return true;
}

/**
 * Valide les données d'un formulaire
 */
function validateFormData($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        // Required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "Le champ {$field} est obligatoire";
            continue;
        }
        
        if (!empty($value)) {
            // Min length
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field] = "Le champ {$field} doit contenir au moins {$rule['min']} caractères";
            }
            
            // Max length
            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field] = "Le champ {$field} ne doit pas dépasser {$rule['max']} caractères";
            }
            
            // Email
            if (isset($rule['email']) && $rule['email'] && !isValidEmail($value)) {
                $errors[$field] = "Le champ {$field} doit être un email valide";
            }
            
            // Numeric
            if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
                $errors[$field] = "Le champ {$field} doit être numérique";
            }
            
            // Min value
            if (isset($rule['min_value']) && is_numeric($value) && $value < $rule['min_value']) {
                $errors[$field] = "Le champ {$field} doit être supérieur ou égal à {$rule['min_value']}";
            }
            
            // Max value
            if (isset($rule['max_value']) && is_numeric($value) && $value > $rule['max_value']) {
                $errors[$field] = "Le champ {$field} doit être inférieur ou égal à {$rule['max_value']}";
            }
        }
    }
    
    return $errors;
}

/**
 * Formate un montant pour l'affichage
 */
function formatAmount($amount, $currency = 'BIF') {
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

/**
 * Formate une date pour l'affichage
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Génère un token sécurisé
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Vérifie les permissions d'un conseiller
 */
function checkConseillerPermission($db, $conseiller_id, $action) {
    // Vérifier que le conseiller existe et est actif
    $stmt = $db->prepare("
        SELECT role, is_active 
        FROM agents 
        WHERE id_agent = ? AND role = 'Conseiller'
    ");
    $stmt->execute([$conseiller_id]);
    $conseiller = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conseiller) {
        return false;
    }
    
    if (!$conseiller['is_active']) {
        return false;
    }
    
    // Liste des actions autorisées pour un conseiller
    $allowed_actions = [
        'add_client',
        'get_client_details',
        'edit_client',
        'open_compte',
        'suspend_compte',
        'close_compte',
        'activate_compte',
        'add_demande_credit',
        'get_demande_credit_details',
        'edit_demande_credit',
        'get_echeancier',
        'get_historique'
    ];
    
    return in_array($action, $allowed_actions);
}

/**
 * Log une action dans la base de données
 */
function logAction($db, $agent_id, $action, $table_name, $record_id, $details = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_log (id_agent, action, table_name, record_id, details, date_heure)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $agent_id,
            $action,
            $table_name,
            $record_id,
            is_array($details) ? json_encode($details) : $details
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors du logging de l'action: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule l'âge d'un client
 */
function calculateAge($birthdate) {
    if (empty($birthdate)) return null;
    
    $birth = new DateTime($birthdate);
    $now = new DateTime();
    $age = $now->diff($birth);
    
    return $age->y;
}

/**
 * Vérifie si une date est dans le futur
 */
function isFutureDate($date) {
    return strtotime($date) > time();
}

/**
 * Vérifie si une date est dans le passé
 */
function isPastDate($date) {
    return strtotime($date) < time();
}
?>