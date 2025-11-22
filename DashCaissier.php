<?php
// DashCaissier.php - Interface professionnelle pour caissiers
require_once 'config.php';

// Vérification de session et des permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Caissier') {
    header('Location: login.php');
    exit();
}

SecurityManager::setSecurityHeaders();
$db = DatabaseConfig::getConnection();

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityManager::generateSecureToken();
}
$csrf_token = $_SESSION['csrf_token'];

// Récupération des informations du caissier connecté
try {
    $stmt = $db->prepare("
        SELECT a.*, ag.nom as agence_nom, ag.id_agence
        FROM agents a 
        LEFT JOIN agences ag ON a.id_agence = ag.id_agence 
        WHERE a.id_agent = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $caissierInfo = $stmt->fetch();
} catch(PDOException $e) {
    $caissierInfo = [];
}

// Récupération des types de transactions
try {
    $typesTransaction = $db->query("
        SELECT * FROM types_transaction 
        WHERE actif = 1 
        AND categorie IN ('DEPOT', 'RETRAIT', 'VIREMENT', 'PAIEMENT', 'FRAIS')
        ORDER BY categorie, code
    ")->fetchAll();
} catch(PDOException $e) {
    $typesTransaction = [];
}

// Récupération des types de comptes
try {
    $typesCompte = $db->query("SELECT * FROM types_compte WHERE actif = 1 ORDER BY code")->fetchAll();
} catch(PDOException $e) {
    $typesCompte = [];
}

// Récupération des agences - UNIQUEMENT L'AGENCE DE L'UTILISATEUR CONNECTÉ
try {
    $agences = [];
    if (!empty($caissierInfo['id_agence'])) {
        $stmt = $db->prepare("SELECT * FROM agences WHERE id_agence = ? ORDER BY nom");
        $stmt->execute([$caissierInfo['id_agence']]);
        $agences = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    $agences = [];
}

// Récupération des guichets
try {
    $guichets = $db->query("
        SELECT g.*, a.nom as agence_nom 
        FROM guichets g 
        LEFT JOIN agences a ON g.id_agence = a.id_agence 
        WHERE g.statut = 'Actif'
        ORDER BY g.numero_guichet
    ")->fetchAll();
} catch(PDOException $e) {
    $guichets = [];
}

// Statistiques du jour pour le caissier
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM transactions 
        WHERE id_agent = ? AND DATE(date_heure) = CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $transTotal = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(t.montant), 0) 
        FROM transactions t
        JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        WHERE t.id_agent = ? AND DATE(t.date_heure) = CURDATE() 
        AND tt.categorie = 'DEPOT'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $depotsMontant = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(t.montant), 0) 
        FROM transactions t
        JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        WHERE t.id_agent = ? AND DATE(t.date_heure) = CURDATE() 
        AND tt.categorie = 'RETRAIT'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $retraitsMontant = $stmt->fetchColumn();
    
    $statsJour = [
        'transactions_total' => $transTotal,
        'depots_montant' => $depotsMontant,
        'retraits_montant' => $retraitsMontant
    ];
} catch(PDOException $e) {
    $statsJour = ['transactions_total' => 0, 'depots_montant' => 0, 'retraits_montant' => 0];
}

// Récupération des transactions récentes
try {
    $mesTransactions = $db->prepare("
        SELECT t.*, tt.libelle as type_libelle, tt.categorie, tt.sens
        FROM transactions t 
        JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        WHERE t.id_agent = ? 
        ORDER BY t.date_heure DESC 
        LIMIT 20
    ");
    $mesTransactions->execute([$_SESSION['user_id']]);
    $mesTransactions = $mesTransactions->fetchAll();
} catch(PDOException $e) {
    $mesTransactions = [];
}

// Traitement de la création de compte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    $response = [];
    
    try {
        // Validation des données
        $required = ['nom', 'prenom', 'telephone', 'num_compte', 'id_type_compte', 'id_agence_origine'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est requis");
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
        
        // Vérification que l'agence sélectionnée est bien celle de l'utilisateur
        if ($idAgenceOrigine != $caissierInfo['id_agence']) {
            throw new Exception("Vous ne pouvez créer des comptes que pour votre agence");
        }
        
        // Gestion de l'upload de photo de profil
        $photo_profil = null;
        if (!empty($_FILES['photo_profil']['name'])) {
            $uploadResult = uploadProfilePhoto($_FILES['photo_profil']);
            if ($uploadResult['success']) {
                $photo_profil = $uploadResult['filename'];
            } else {
                throw new Exception($uploadResult['message']);
            }
        }
        
        // Vérification de l'unicité du numéro de compte
        $stmt = $db->prepare("SELECT COUNT(*) FROM comptes WHERE num_compte = ?");
        $stmt->execute([$numCompte]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de compte existe déjà");
        }
        
        // Vérification que le type de compte existe
        $stmt = $db->prepare("SELECT * FROM types_compte WHERE id_type_compte = ? AND actif = 1");
        $stmt->execute([$idTypeCompte]);
        $typeCompte = $stmt->fetch();
        
        if (!$typeCompte) {
            throw new Exception("Type de compte invalide");
        }
        
        // Vérification du solde minimum
        if ($soldeInitial > 0 && $soldeInitial < $typeCompte['solde_minimum']) {
            throw new Exception("Le dépôt initial doit être d'au moins " . number_format($typeCompte['solde_minimum'], 0, ',', ' ') . ' BIF');
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
                        ) VALUES (?, ?, ?, ?, 0, ?, 'Terminée', 'Dépôt initial à l\\'ouverture du compte')
                    ");
                    $stmt->execute([
                        $numCompte,
                        $idTypeTransaction,
                        $_SESSION['user_id'],
                        $soldeInitial,
                        $soldeInitial
                    ]);
                }
            }
            
            $db->commit();
            
            $response = [
                'success' => true,
                'message' => 'Compte créé avec succès',
                'id_client' => $idClient,
                'id_compte' => $idCompte,
                'num_compte' => $numCompte
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// Fonction d'upload de photo de profil
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poste de Caisse - FENACOBU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        :root {
            --primary-red: #d32f2f;
            --primary-green: #2e7d32;
            --primary-blue: #1976d2;
            --primary-orange: #f57c00;
            --primary-purple: #7b1fa2;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            font-family: 'Times New Roman', Times, serif !important;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Header Styles */
        .navbar {
            background: linear-gradient(90deg, var(--primary-red) 0%, var(--primary-green) 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            height: var(--header-height);
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
            z-index: 1000;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a237e 0%, #283593 100%);
            color: white;
            z-index: 1001;
            overflow-y: auto;
            transition: transform 0.3s;
            box-shadow: 4px 0 20px rgba(0,0,0,0.2);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            margin-bottom: 8px;
        }
        
        .nav-link {
            color: #bdc3c7;
            padding: 18px 25px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
            border-radius: 0 12px 12px 0;
            margin: 0 10px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: linear-gradient(90deg, rgba(255,255,255,0.15) 0%, transparent 100%);
            border-left-color: var(--primary-green);
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 25px;
            margin-right: 12px;
            font-size: 1.2rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px;
            transition: margin-left 0.3s;
            min-height: calc(100vh - var(--header-height));
        }
        
        /* Operation Cards */
        .operation-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Client Profile Cards */
        .client-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            display: none;
        }
        
        .client-card.active {
            display: block;
            animation: slideInUp 0.6s ease-out;
        }
        
        .client-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #e9ecef;
            object-fit: cover;
            margin-right: 20px;
        }
        
        .client-info h4 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .client-info p {
            margin: 5px 0 0 0;
            color: #7f8c8d;
        }
        
        .account-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .balance-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-top: 15px;
        }
        
        .balance-amount {
            font-size: 2.2rem;
            font-weight: bold;
            margin: 10px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        /* Search Container */
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-input {
            height: 70px;
            font-size: 1.3rem;
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding-left: 70px;
            transition: all 0.3s;
            font-weight: 500;
            background: white;
        }
        
        .search-input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.3rem rgba(46, 125, 50, 0.15);
            transform: translateY(-2px);
        }
        
        .search-icon {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.8rem;
            color: var(--primary-green);
            z-index: 2;
        }
        
        /* Transaction Forms */
        .transaction-form {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
        }
        
        /* SECTION BILLETAGE CORRIGÉE ET VISIBLE */
        .billetage-section {
            background: #fff3cd !important;
            border: 2px solid #ffc107 !important;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .billetage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .billet-item {
            text-align: center;
            background: white;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .billet-item:hover {
            border-color: #ffc107;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }
        
        .billet-item label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }
        
        .billet-input {
            text-align: center;
            font-weight: bold;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            font-size: 1.1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .billet-input:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
            background: white;
        }
        
        .billet-total {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: #155724;
            transition: all 0.3s ease;
        }
        
        .billet-total.highlight {
            background: #c3e6cb;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .summary-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 2px solid #bbdefb;
        }
        
        /* Transfer Specific Styles */
        .transfer-container {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 25px;
            margin-bottom: 25px;
            align-items: start;
        }
        
        .transfer-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary-blue);
            margin-top: 50px;
        }
        
        /* Account Creation Form */
        .creation-form {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .form-row-horizontal {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.15);
        }
        
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.15);
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* Buttons */
        .btn-operation {
            height: 60px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s;
            border: none;
            padding: 0 30px;
        }
        
        .btn-operation:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        /* Dashboard Stats */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border: 1px solid rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            height: 100%;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        /* Quick Actions */
        .quick-action-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: #e9ecef;
        }
        
        .action-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            color: white;
        }
        
        /* Guides et étapes */
        .operation-guide {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 6px solid var(--primary-blue);
        }
        
        .operation-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        
        .operation-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 15px;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        
        .step-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-placeholder {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            margin: 20px 0;
            color: #6c757d;
        }
        
        .form-placeholder i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        
        /* Styles pour l'upload de photo */
        .photo-upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .photo-upload-container:hover {
            border-color: var(--primary-blue);
            background: #e3f2fd;
        }
        
        .photo-upload-container.dragover {
            border-color: var(--primary-green);
            background: #e8f5e8;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
            margin: 0 auto 15px;
            display: none;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .file-input {
            display: none;
        }
        
        .upload-text {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .upload-hint {
            font-size: 0.875rem;
            color: #adb5bd;
        }
        
        .remove-photo {
            margin-top: 10px;
            display: none;
        }
        
        /* Styles pour l'historique */
        .transaction-history {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .transaction-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 8px;
        }
        
        .transaction-item.credit {
            border-left-color: #28a745;
        }
        
        .transaction-item.debit {
            border-left-color: #dc3545;
        }
        
        .history-period-selector {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .period-option {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .period-option:hover {
            border-color: var(--primary-blue);
            background: #e3f2fd;
        }
        
        .period-option.active {
            border-color: var(--primary-blue);
            background: var(--primary-blue);
            color: white;
        }
        
        /* Animations */
        @keyframes slideInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .navbar, .main-content {
                margin-left: 0;
            }
            
            .transfer-container {
                grid-template-columns: 1fr;
            }
            
            .transfer-arrow {
                transform: rotate(90deg);
                margin: 10px 0;
            }
            
            .client-card-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .form-row-horizontal {
                grid-template-columns: 1fr;
            }
            
            .operation-steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .operation-steps::before {
                display: none;
            }
            
            .billetage-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
            }
        }

        @media (max-width: 1200px) {
            .form-row-horizontal {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-2">
                <i class="fas fa-university me-2"></i>FENACOBU
            </h4>
            <p class="text-muted mb-0">Poste de Caisse</p>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="#" data-tab="dashboard">
                        <i class="fas fa-tachometer-alt"></i>Tableau de Bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="depot">
                        <i class="fas fa-money-bill-wave text-success"></i>Dépôt d'Espèces
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="retrait">
                        <i class="fas fa-hand-holding-usd text-danger"></i>Retrait d'Espèces
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="virement">
                        <i class="fas fa-exchange-alt text-primary"></i>Virement Bancaire
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="creation-compte">
                        <i class="fas fa-user-plus text-info"></i>Création de Compte
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="situation-compte">
                        <i class="fas fa-file-invoice-dollar text-warning"></i>Situation de Compte
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="generation-historique">
                        <i class="fas fa-file-pdf text-danger"></i>Génération Historique
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="paiement">
                        <i class="fas fa-credit-card text-warning"></i>Paiement Factures
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="historique">
                        <i class="fas fa-history text-secondary"></i>Historique Transactions
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer p-3 mt-auto">
            <div class="text-center">
                <div class="fw-bold"><?php echo htmlspecialchars($caissierInfo['first_name'] . ' ' . $caissierInfo['last_name']); ?></div>
                <small class="text-muted">Caissier</small>
                <div class="mt-2">
                    <small class="text-muted"><?php echo htmlspecialchars($caissierInfo['agence_nom'] ?? 'Agence Principale'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-light me-3 d-md-none" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="text-white mb-0">
                    <i class="fas fa-cash-register me-2"></i>Poste de Caisse
                </h5>
            </div>
            
            <div class="ms-auto d-flex align-items-center text-white">
                <div class="me-4">
                    <i class="fas fa-user-circle me-2"></i>
                    <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                </div>
                <div class="me-4">
                    <i class="fas fa-calendar-day me-2"></i>
                    <?php echo date('d/m/Y'); ?>
                </div>
                <div class="me-4">
                    <i class="fas fa-clock me-2"></i>
                    <span id="liveClock"><?php echo date('H:i:s'); ?></span>
                </div>
                <a href="logout.php" class="btn btn-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenu Principal -->
    <div class="main-content" id="mainContent">
        <!-- Tableau de Bord -->
        <div class="tab-content" id="dashboard">
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>Tableau de Bord du Caissier
                    </h3>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-value text-success"><?php echo number_format($statsJour['transactions_total']); ?></div>
                        <div class="stat-label">Transactions Aujourd'hui</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-value text-primary"><?php echo number_format($statsJour['depots_montant'], 0, ',', ' '); ?> BIF</div>
                        <div class="stat-label">Total Dépôts</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-value text-danger"><?php echo number_format($statsJour['retraits_montant'], 0, ',', ' '); ?> BIF</div>
                        <div class="stat-label">Total Retraits</div>
                    </div>
                </div>
            </div>

            <!-- Actions Rapides -->
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-4">
                        <i class="fas fa-bolt me-2 text-warning"></i>Actions Rapides
                    </h4>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="quick-action-card" onclick="switchTab('depot')">
                        <div class="action-icon bg-success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h5>Dépôt</h5>
                        <p class="text-muted">Effectuer un dépôt d'espèces</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="quick-action-card" onclick="switchTab('retrait')">
                        <div class="action-icon bg-danger">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h5>Retrait</h5>
                        <p class="text-muted">Traiter un retrait d'espèces</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="quick-action-card" onclick="switchTab('virement')">
                        <div class="action-icon bg-primary">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <h5>Virement</h5>
                        <p class="text-muted">Effectuer un virement</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="quick-action-card" onclick="switchTab('situation-compte')">
                        <div class="action-icon bg-warning">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h5>Situation</h5>
                        <p class="text-muted">Consulter un compte</p>
                    </div>
                </div>
            </div>
        </div>
  
        <!-- Section Dépôt - AVEC BILLETAGE VISIBLE -->
        <div class="tab-content d-none" id="depot">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-money-bill-wave me-2 text-success"></i>Dépôt d'Espèces
                    </h3>
                </div>
            </div>

            <!-- Guide d'utilisation -->
            <div class="operation-guide">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Comment effectuer un dépôt ?</h5>
                <p class="mb-0">Recherchez le compte client, vérifiez les informations, saisissez le montant et confirmez l'opération.</p>
            </div>

            <!-- Étapes de l'opération -->
            <div class="operation-steps">
                <div class="step active" id="step1-depot">
                    <div class="step-number">1</div>
                    <div class="step-label">Recherche Compte</div>
                </div>
                <div class="step" id="step2-depot">
                    <div class="step-number">2</div>
                    <div class="step-label">Vérification</div>
                </div>
                <div class="step" id="step3-depot">
                    <div class="step-number">3</div>
                    <div class="step-label">Saisie Montant</div>
                </div>
                <div class="step" id="step4-depot">
                    <div class="step-number">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <div class="operation-card">
                <!-- Étape 1: Recherche du compte -->
                <div class="search-container" id="searchStepDepot">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="form-control search-input" 
                           id="searchAccountDepot" 
                           placeholder="Saisir le numéro de compte du client..."
                           autocomplete="off">
                    <div class="form-text text-center mt-2">
                        <i class="fas fa-lightbulb me-1"></i>
                        Saisissez le numéro de compte et appuyez sur Entrée
                    </div>
                </div>

                <!-- Étape 2: Affichage des informations du client -->
                <div class="client-card" id="clientCardDepot">
                    <div class="client-card-header">
                        <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoDepot">
                        <div class="client-info">
                            <h4 id="clientNameDepot">Nom du Client</h4>
                            <p id="clientContactDepot">Contact information</p>
                        </div>
                    </div>
                    
                    <div class="account-details">
                        <div class="detail-item">
                            <div class="detail-label">N° Compte</div>
                            <div class="detail-value" id="accountNumberDepot">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type de Compte</div>
                            <div class="detail-value" id="accountTypeDepot">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Agence</div>
                            <div class="detail-value" id="accountAgenceDepot">-</div>
                        </div>
                    </div>
                    
                    <div class="balance-display">
                        <div class="detail-label text-white-50">Solde Actuel</div>
                        <div class="balance-amount" id="accountBalanceDepot">0 BIF</div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button class="btn btn-success me-2" onclick="proceedToDepotForm()">
                            <i class="fas fa-arrow-right me-1"></i>Continuer vers le dépôt
                        </button>
                        <button class="btn btn-outline-secondary" onclick="resetDepotSearch()">
                            <i class="fas fa-redo me-1"></i>Changer de compte
                        </button>
                    </div>
                </div>

                <!-- Étape 3: Formulaire de dépôt AVEC BILLETAGE VISIBLE -->
                <div class="transaction-form d-none" id="depotFormSection">
                    <form id="depotForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="num_compte" id="depotNumCompte">
                        <input type="hidden" name="ajax_action" value="process_depot">
                        
                        <div class="form-section">
                            <h5 class="mb-3"><i class="fas fa-cog me-2"></i>Configuration du Dépôt</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Type de Dépôt *</label>
                                    <select class="form-select" name="id_type_transaction" id="depotType" required>
                                        <option value="">Sélectionnez...</option>
                                        <?php foreach ($typesTransaction as $type): ?>
                                            <?php if ($type['categorie'] == 'DEPOT'): ?>
                                            <option value="<?php echo $type['id_type_transaction']; ?>"
                                                    data-frais-fixe="<?php echo $type['frais_fixe']; ?>"
                                                    data-frais-pourcent="<?php echo $type['frais_pourcentage']; ?>">
                                                <?php echo htmlspecialchars($type['libelle']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Montant (BIF) *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="depotMontant"
                                           name="montant"
                                           min="0" 
                                           step="100" 
                                           placeholder="0"
                                           required>
                                </div>
                            </div>
                            
                            <!-- SECTION BILLETAGE VISIBLE ET FONCTIONNELLE -->
                            <div class="billetage-section" id="billetageSection">
                                <h6 class="mb-3"><i class="fas fa-money-bill-wave me-2 text-warning"></i>Billetage (Optionnel)</h6>
                                <p class="text-muted mb-3">Saisissez le nombre de billets pour calculer automatiquement le total</p>
                                
                                <div class="billetage-grid">
                                    <div class="billet-item">
                                        <label>10,000 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet10000" min="0" value="0" data-valeur="10000">
                                    </div>
                                    <div class="billet-item">
                                        <label>5,000 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet5000" min="0" value="0" data-valeur="5000">
                                    </div>
                                    <div class="billet-item">
                                        <label>2,000 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet2000" min="0" value="0" data-valeur="2000">
                                    </div>
                                    <div class="billet-item">
                                        <label>1,000 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet1000" min="0" value="0" data-valeur="1000">
                                    </div>
                                    <div class="billet-item">
                                        <label>500 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet500" min="0" value="0" data-valeur="500">
                                    </div>
                                    <div class="billet-item">
                                        <label>100 BIF</label>
                                        <input type="number" class="form-control billet-input" id="billet100" min="0" value="0" data-valeur="100">
                                    </div>
                                </div>
                                
                                <div class="billet-total" id="billetTotalDepot">
                                    <i class="fas fa-calculator me-2"></i>Total Billetage: <span id="billetTotalValue">0</span> BIF
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-warning btn-sm me-2" onclick="appliquerBilletage()">
                                        <i class="fas fa-check me-1"></i>Appliquer au montant
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="reinitialiserBilletage()">
                                        <i class="fas fa-redo me-1"></i>Réinitialiser
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <label class="form-label fw-bold">Description (optionnel)</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Description de l'opération..."></textarea>
                            </div>
                        </div>

                        <!-- Étape 4: Récapitulatif -->
                        <div class="summary-card d-none" id="depotSummary">
                            <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>Récapitulatif du Dépôt</h5>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div>Montant du dépôt :</div>
                                    <div class="fs-4 fw-bold text-success" id="summaryDepotMontant">0 BIF</div>
                                </div>
                                <div class="col-6">
                                    <div>Frais :</div>
                                    <div class="fs-4 fw-bold" id="summaryDepotFrais">0 BIF</div>
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fs-5">Nouveau solde :</span>
                                        <span class="fs-3 fw-bold text-success" id="summaryDepotNewBalance">0 BIF</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-3 mt-4">
                            <button type="submit" class="btn btn-success btn-operation pulse-animation" id="depotSubmitBtn">
                                <i class="fas fa-check-circle me-2"></i>Confirmer le Dépôt
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetDepotForm()">
                                <i class="fas fa-times me-2"></i>Annuler l'Opération
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Placeholder initial -->
                <div class="form-placeholder" id="depotPlaceholder">
                    <i class="fas fa-search"></i>
                    <h5>Recherche de Compte</h5>
                    <p class="mb-0">Veuillez saisir un numéro de compte dans la barre de recherche ci-dessus pour commencer l'opération de dépôt.</p>
                </div>
            </div>
        </div>

        <!-- Section Retrait -->
        <div class="tab-content d-none" id="retrait">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-hand-holding-usd me-2 text-danger"></i>Retrait d'Espèces
                    </h3>
                </div>
            </div>

            <!-- Guide d'utilisation -->
            <div class="operation-guide">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Comment effectuer un retrait ?</h5>
                <p class="mb-0">Recherchez le compte client, vérifiez le solde disponible, saisissez le montant et confirmez l'opération.</p>
            </div>

            <!-- Étapes de l'opération -->
            <div class="operation-steps">
                <div class="step active" id="step1-retrait">
                    <div class="step-number">1</div>
                    <div class="step-label">Recherche Compte</div>
                </div>
                <div class="step" id="step2-retrait">
                    <div class="step-number">2</div>
                    <div class="step-label">Vérification Solde</div>
                </div>
                <div class="step" id="step3-retrait">
                    <div class="step-number">3</div>
                    <div class="step-label">Saisie Montant</div>
                </div>
                <div class="step" id="step4-retrait">
                    <div class="step-number">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <div class="operation-card">
                <!-- Étape 1: Recherche du compte -->
                <div class="search-container" id="searchStepRetrait">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="form-control search-input" 
                           id="searchAccountRetrait" 
                           placeholder="Saisir le numéro de compte du client..."
                           autocomplete="off">
                    <div class="form-text text-center mt-2">
                        <i class="fas fa-lightbulb me-1"></i>
                        Saisissez le numéro de compte et appuyez sur Entrée
                    </div>
                </div>

                <!-- Étape 2: Affichage des informations du client -->
                <div class="client-card" id="clientCardRetrait">
                    <div class="client-card-header">
                        <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoRetrait">
                        <div class="client-info">
                            <h4 id="clientNameRetrait">Nom du Client</h4>
                            <p id="clientContactRetrait">Contact information</p>
                        </div>
                    </div>
                    
                    <div class="account-details">
                        <div class="detail-item">
                            <div class="detail-label">N° Compte</div>
                            <div class="detail-value" id="accountNumberRetrait">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type de Compte</div>
                            <div class="detail-value" id="accountTypeRetrait">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Solde Disponible</div>
                            <div class="detail-value" id="accountBalanceRetrait">0 BIF</div>
                        </div>
                    </div>
                    
                    <div class="balance-display bg-danger">
                        <div class="detail-label text-white-50">Solde Disponible</div>
                        <div class="balance-amount" id="accountBalanceMainRetrait">0 BIF</div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button class="btn btn-danger me-2" onclick="proceedToRetraitForm()">
                            <i class="fas fa-arrow-right me-1"></i>Continuer vers le retrait
                        </button>
                        <button class="btn btn-outline-secondary" onclick="resetRetraitSearch()">
                            <i class="fas fa-redo me-1"></i>Changer de compte
                        </button>
                    </div>
                </div>

                <!-- Étape 3: Formulaire de retrait -->
                <div class="transaction-form d-none" id="retraitFormSection">
                    <form id="retraitForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="num_compte" id="retraitNumCompte">
                        <input type="hidden" name="ajax_action" value="process_retrait">
                        
                        <div class="form-section">
                            <h5 class="mb-3"><i class="fas fa-cog me-2"></i>Configuration du Retrait</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Type de Retrait *</label>
                                    <select class="form-select" name="id_type_transaction" id="retraitType" required>
                                        <option value="">Sélectionnez...</option>
                                        <?php foreach ($typesTransaction as $type): ?>
                                            <?php if ($type['categorie'] == 'RETRAIT'): ?>
                                            <option value="<?php echo $type['id_type_transaction']; ?>"
                                                    data-frais-fixe="<?php echo $type['frais_fixe']; ?>"
                                                    data-frais-pourcent="<?php echo $type['frais_pourcentage']; ?>">
                                                <?php echo htmlspecialchars($type['libelle']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Montant (BIF) *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="retraitMontant"
                                           name="montant"
                                           min="0" 
                                           step="100" 
                                           placeholder="0"
                                           required>
                                    <div class="form-text">
                                        Solde disponible: <span id="availableBalanceRetrait" class="fw-bold">0 BIF</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <label class="form-label fw-bold">Description (optionnel)</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Description de l'opération..."></textarea>
                            </div>
                        </div>

                        <!-- Étape 4: Récapitulatif -->
                        <div class="summary-card d-none" id="retraitSummary">
                            <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>Récapitulatif du Retrait</h5>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div>Montant du retrait :</div>
                                    <div class="fs-4 fw-bold text-danger" id="summaryRetraitMontant">0 BIF</div>
                                </div>
                                <div class="col-6">
                                    <div>Frais :</div>
                                    <div class="fs-4 fw-bold" id="summaryRetraitFrais">0 BIF</div>
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fs-5">Nouveau solde :</span>
                                        <span class="fs-3 fw-bold" id="summaryRetraitNewBalance">0 BIF</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-3 mt-4">
                            <button type="submit" class="btn btn-danger btn-operation pulse-animation" id="retraitSubmitBtn">
                                <i class="fas fa-check-circle me-2"></i>Confirmer le Retrait
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetRetraitForm()">
                                <i class="fas fa-times me-2"></i>Annuler l'Opération
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Placeholder initial -->
                <div class="form-placeholder" id="retraitPlaceholder">
                    <i class="fas fa-search"></i>
                    <h5>Recherche de Compte</h5>
                    <p class="mb-0">Veuillez saisir un numéro de compte dans la barre de recherche ci-dessus pour commencer l'opération de retrait.</p>
                </div>
            </div>
        </div>

        <!-- Section Virement -->
        <div class="tab-content d-none" id="virement">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-exchange-alt me-2 text-primary"></i>Virement Bancaire
                    </h3>
                </div>
            </div>

            <!-- Guide d'utilisation -->
            <div class="operation-guide">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Comment effectuer un virement ?</h5>
                <p class="mb-0">Recherchez le compte source et le compte destinataire, vérifiez les soldes, saisissez le montant et confirmez l'opération.</p>
            </div>

            <!-- Étapes de l'opération -->
            <div class="operation-steps">
                <div class="step active" id="step1-virement">
                    <div class="step-number">1</div>
                    <div class="step-label">Compte Source</div>
                </div>
                <div class="step" id="step2-virement">
                    <div class="step-number">2</div>
                    <div class="step-label">Compte Destinataire</div>
                </div>
                <div class="step" id="step3-virement">
                    <div class="step-number">3</div>
                    <div class="step-label">Saisie Montant</div>
                </div>
                <div class="step" id="step4-virement">
                    <div class="step-number">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <div class="operation-card">
                <div class="transfer-container">
                    <!-- Compte Source -->
                    <div>
                        <h5 class="mb-3 text-center">Compte Source</h5>
                        <div class="search-container mb-3">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   class="form-control search-input" 
                                   id="searchAccountVirementSource" 
                                   placeholder="Numéro compte source..."
                                   autocomplete="off">
                        </div>
                        <div class="client-card" id="clientCardVirementSource">
                            <div class="client-card-header">
                                <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoVirementSource">
                                <div class="client-info">
                                    <h4 id="clientNameVirementSource">Nom Client</h4>
                                    <p id="clientContactVirementSource">Contact</p>
                                </div>
                            </div>
                            <div class="account-details">
                                <div class="detail-item">
                                    <div class="detail-label">N° Compte</div>
                                    <div class="detail-value" id="accountNumberVirementSource">-</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Type Compte</div>
                                    <div class="detail-value" id="accountTypeVirementSource">-</div>
                                </div>
                            </div>
                            <div class="balance-display bg-primary">
                                <div class="detail-label text-white-50">Solde Disponible</div>
                                <div class="balance-amount" id="accountBalanceVirementSource">0 BIF</div>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="focusDestInput()">
                                    <i class="fas fa-arrow-down me-1"></i>Continuer vers destinataire
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Flèche de transfert -->
                    <div class="transfer-arrow">
                        <i class="fas fa-arrow-right fa-2x"></i>
                    </div>

                    <!-- Compte Destinataire -->
                    <div>
                        <h5 class="mb-3 text-center">Compte Destinataire</h5>
                        <div class="search-container mb-3">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   class="form-control search-input" 
                                   id="searchAccountVirementDest" 
                                   placeholder="Numéro compte destinataire..."
                                   autocomplete="off">
                        </div>
                        <div class="client-card" id="clientCardVirementDest">
                            <div class="client-card-header">
                                <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoVirementDest">
                                <div class="client-info">
                                    <h4 id="clientNameVirementDest">Nom Client</h4>
                                    <p id="clientContactVirementDest">Contact</p>
                                </div>
                            </div>
                            <div class="account-details">
                                <div class="detail-item">
                                    <div class="detail-label">N° Compte</div>
                                    <div class="detail-value" id="accountNumberVirementDest">-</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Type Compte</div>
                                    <div class="detail-value" id="accountTypeVirementDest">-</div>
                                </div>
                            </div>
                            <div class="balance-display bg-success">
                                <div class="detail-label text-white-50">Solde Actuel</div>
                                <div class="balance-amount" id="accountBalanceVirementDest">0 BIF</div>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-success btn-sm" onclick="proceedToVirementForm()">
                                    <i class="fas fa-arrow-right me-1"></i>Continuer vers virement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de Virement -->
                <div class="transaction-form d-none" id="virementFormSection">
                    <form id="virementForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="num_compte_source" id="virementNumCompteSource">
                        <input type="hidden" name="num_compte_dest" id="virementNumCompteDest">
                        <input type="hidden" name="ajax_action" value="process_virement">
                        
                        <div class="form-section">
                            <h5 class="mb-3"><i class="fas fa-cog me-2"></i>Configuration du Virement</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Type de Virement *</label>
                                    <select class="form-select" name="id_type_transaction" id="virementType" required>
                                        <option value="">Sélectionnez...</option>
                                        <?php foreach ($typesTransaction as $type): ?>
                                            <?php if ($type['categorie'] == 'VIREMENT'): ?>
                                            <option value="<?php echo $type['id_type_transaction']; ?>"
                                                    data-frais-fixe="<?php echo $type['frais_fixe']; ?>"
                                                    data-frais-pourcent="<?php echo $type['frais_pourcentage']; ?>">
                                                <?php echo htmlspecialchars($type['libelle']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Montant (BIF) *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="virementMontant"
                                           name="montant"
                                           min="0" 
                                           step="100" 
                                           placeholder="0"
                                           required>
                                    <div class="form-text">
                                        Solde source disponible: <span id="availableBalanceVirement" class="fw-bold">0 BIF</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <label class="form-label fw-bold">Description (optionnel)</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Description du virement..."></textarea>
                            </div>
                        </div>

                        <div class="summary-card d-none" id="virementSummary">
                            <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>Récapitulatif du Virement</h5>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div>Montant du virement :</div>
                                    <div class="fs-4 fw-bold text-primary" id="summaryVirementMontant">0 BIF</div>
                                </div>
                                <div class="col-6">
                                    <div>Frais :</div>
                                    <div class="fs-4 fw-bold" id="summaryVirementFrais">0 BIF</div>
                                </div>
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fs-5">Nouveau solde source :</span>
                                        <span class="fs-3 fw-bold" id="summaryVirementNewBalance">0 BIF</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-3 mt-4">
                            <button type="submit" class="btn btn-primary btn-operation pulse-animation" id="virementSubmitBtn">
                                <i class="fas fa-check-circle me-2"></i>Confirmer le Virement
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetVirementForm()">
                                <i class="fas fa-times me-2"></i>Annuler l'Opération
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Placeholder initial -->
                <div class="form-placeholder" id="virementPlaceholder">
                    <i class="fas fa-exchange-alt"></i>
                    <h5>Recherche des Comptes</h5>
                    <p class="mb-0">Veuillez saisir les numéros de compte source et destinataire dans les barres de recherche ci-dessus pour commencer l'opération de virement.</p>
                </div>
            </div>
        </div>

        <!-- Section Création de Compte - MODIFIÉE -->
        <div class="tab-content d-none" id="creation-compte">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-user-plus me-2 text-info"></i>Création de Nouveau Compte
                    </h3>
                </div>
            </div>

            <div class="operation-card">
                <div class="creation-form">
                    <form id="createAccountForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="create_account">
                        
                        <!-- Section Photo de Profil -->
                        <div class="form-section">
                            <h5 class="mb-4"><i class="fas fa-camera me-2"></i>Photo de Profil</h5>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="photo-upload-container" id="photoUploadContainer">
                                        <img id="photoPreview" class="photo-preview" alt="Aperçu photo">
                                        <div id="uploadPlaceholder">
                                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                            <div class="upload-text">Cliquer pour uploader une photo</div>
                                            <div class="upload-hint">JPG, PNG, GIF - Max 2MB</div>
                                        </div>
                                        <input type="file" id="photoInput" name="photo_profil" class="file-input" accept="image/*">
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-photo" id="removePhotoBtn">
                                        <i class="fas fa-times me-1"></i>Supprimer la photo
                                    </button>
                                </div>
                                <div class="col-md-8">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        La photo de profil est optionnelle. Formats acceptés: JPG, PNG, GIF. Taille maximale: 2MB.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section Informations Personnelles -->
                        <div class="form-section">
                            <h5 class="mb-4"><i class="fas fa-user me-2"></i>Informations Personnelles du Client</h5>
                            
                            <!-- Ligne 1: Nom et Prénom -->
                            <div class="form-row-horizontal">
                                <div class="form-group">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="nom" required placeholder="Entrez le nom">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="prenom" required placeholder="Entrez le prénom">
                                </div>
                            </div>
                            
                            <!-- Ligne 2: Téléphone et Email -->
                            <div class="form-row-horizontal">
                                <div class="form-group">
                                    <label class="form-label">Téléphone *</label>
                                    <input type="tel" class="form-control" name="telephone" required placeholder="Ex: +257 XX XX XX XX">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="Entrez l'email">
                                </div>
                            </div>
                            
                            <!-- Ligne 3: Adresse (pleine largeur) -->
                            <div class="form-group-full">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="adresse" rows="2" placeholder="Adresse complète du client"></textarea>
                            </div>
                        </div>

                        <!-- Section Informations du Compte -->
                        <div class="form-section">
                            <h5 class="mb-4"><i class="fas fa-credit-card me-2"></i>Informations du Compte</h5>
                            
                            <!-- Ligne 1: Numéro de compte et Type de compte -->
                            <div class="form-row-horizontal">
                                <div class="form-group">
                                    <label class="form-label">Numéro de Compte *</label>
                                    <input type="text" class="form-control" name="num_compte" required placeholder="Ex: COMPTE001">
                                    <small class="form-text">Numéro unique identifiant le compte</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Type de Compte *</label>
                                    <select class="form-select" name="id_type_compte" required>
                                        <option value="">Sélectionnez le type...</option>
                                        <?php foreach ($typesCompte as $type): ?>
                                        <option value="<?php echo $type['id_type_compte']; ?>">
                                            <?php echo htmlspecialchars($type['libelle'] . ' (' . $type['code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Ligne 2: Agence et Solde initial -->
                            <div class="form-row-horizontal">
                                <div class="form-group">
                                    <label class="form-label">Agence d'Origine *</label>
                                    <select class="form-select" name="id_agence_origine" required>
                                        <option value="">Sélectionnez l'agence...</option>
                                        <?php foreach ($agences as $agence): ?>
                                        <option value="<?php echo $agence['id_agence']; ?>" 
                                                <?php echo ($agence['id_agence'] == $caissierInfo['id_agence']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agence['nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (count($agences) === 1): ?>
                                    <small class="form-text text-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Vous ne pouvez créer des comptes que pour votre agence
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Solde Initial (BIF)</label>
                                    <input type="number" class="form-control" name="solde_initial" min="0" step="100" placeholder="0">
                                    <small class="form-text">Dépôt initial optionnel</small>
                                </div>
                            </div>
                        </div>

                        <!-- Récapitulatif -->
                        <div class="summary-card">
                            <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Récapitulatif</h5>
                            <div class="form-row-horizontal">
                                <div class="form-group">
                                    <div class="detail-item">
                                        <div class="detail-label">Client</div>
                                        <div class="detail-value" id="recapNomClient">-</div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="detail-item">
                                        <div class="detail-label">Téléphone</div>
                                        <div class="detail-value" id="recapTelephone">-</div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="detail-item">
                                        <div class="detail-label">Numéro Compte</div>
                                        <div class="detail-value" id="recapNumCompte">-</div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="detail-item">
                                        <div class="detail-label">Type Compte</div>
                                        <div class="detail-value" id="recapTypeCompte">-</div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="detail-item">
                                        <div class="detail-label">Agence</div>
                                        <div class="detail-value" id="recapAgence">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-grid gap-3 mt-4">
                            <button type="submit" class="btn btn-info btn-operation" id="createAccountBtn">
                                <i class="fas fa-save me-2"></i>Créer le Compte
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetCreateAccountForm()">
                                <i class="fas fa-times me-2"></i>Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- NOUVELLE SECTION: Situation de Compte -->
        <div class="tab-content d-none" id="situation-compte">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Situation de Compte
                    </h3>
                </div>
            </div>

            <div class="operation-guide">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Consultation de Situation</h5>
                <p class="mb-0">Recherchez un compte pour consulter sa situation actuelle, le solde et les informations détaillées.</p>
            </div>

            <div class="operation-card">
                <!-- Recherche du compte -->
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="form-control search-input" 
                           id="searchAccountSituation" 
                           placeholder="Saisir le numéro de compte..."
                           autocomplete="off">
                </div>

                <!-- Affichage des informations du compte -->
                <div class="client-card" id="clientCardSituation">
                    <div class="client-card-header">
                        <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoSituation">
                        <div class="client-info">
                            <h4 id="clientNameSituation">Nom du Client</h4>
                            <p id="clientContactSituation">Contact information</p>
                        </div>
                    </div>
                    
                    <div class="account-details">
                        <div class="detail-item">
                            <div class="detail-label">N° Compte</div>
                            <div class="detail-value" id="accountNumberSituation">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type de Compte</div>
                            <div class="detail-value" id="accountTypeSituation">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Agence</div>
                            <div class="detail-value" id="accountAgenceSituation">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Statut</div>
                            <div class="detail-value" id="accountStatusSituation">-</div>
                        </div>
                    </div>
                    
                    <div class="balance-display">
                        <div class="detail-label text-white-50">Solde Actuel</div>
                        <div class="balance-amount" id="accountBalanceSituation">0 BIF</div>
                        <div class="detail-label text-white-50 mt-2">Solde Disponible</div>
                        <div class="balance-amount" id="accountAvailableSituation">0 BIF</div>
                    </div>
                    
                    <!-- Dernières transactions -->
                    <div class="mt-4">
                        <h6 class="mb-3"><i class="fas fa-list me-2"></i>Dernières Transactions</h6>
                        <div class="transaction-history" id="recentTransactions">
                            <!-- Les transactions récentes seront chargées ici -->
                        </div>
                    </div>
                </div>

                <!-- Placeholder initial -->
                <div class="form-placeholder" id="situationPlaceholder">
                    <i class="fas fa-search"></i>
                    <h5>Recherche de Compte</h5>
                    <p class="mb-0">Veuillez saisir un numéro de compte dans la barre de recherche ci-dessus pour consulter sa situation.</p>
                </div>
            </div>
        </div>

        <!-- NOUVELLE SECTION: Génération d'Historique -->
        <div class="tab-content d-none" id="generation-historique">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-file-pdf me-2 text-danger"></i>Génération d'Historique de Compte
                    </h3>
                </div>
            </div>

            <div class="operation-guide">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Génération d'Extrait de Compte</h5>
                <p class="mb-0">Recherchez un compte et sélectionnez une période pour générer un historique détaillé des transactions. <strong class="text-danger">Frais applicable: 1,000 BIF</strong></p>
            </div>

            <div class="operation-card">
                <!-- Étape 1: Recherche du compte -->
                <div class="search-container mb-4">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="form-control search-input" 
                           id="searchAccountHistory" 
                           placeholder="Saisir le numéro de compte..."
                           autocomplete="off">
                </div>

                <!-- Informations du compte -->
                <div class="client-card mb-4" id="clientCardHistory">
                    <div class="client-card-header">
                        <img src="assets/images/default-avatar.png" alt="Photo" class="profile-avatar" id="clientPhotoHistory">
                        <div class="client-info">
                            <h4 id="clientNameHistory">Nom du Client</h4>
                            <p id="clientContactHistory">Contact information</p>
                        </div>
                    </div>
                    
                    <div class="account-details">
                        <div class="detail-item">
                            <div class="detail-label">N° Compte</div>
                            <div class="detail-value" id="accountNumberHistory">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type de Compte</div>
                            <div class="detail-value" id="accountTypeHistory">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Solde Actuel</div>
                            <div class="detail-value" id="accountBalanceHistory">0 BIF</div>
                        </div>
                    </div>
                </div>

                <!-- Étape 2: Sélection de la période -->
                <div class="history-period-selector d-none" id="periodSelector">
                    <h5 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Sélection de la Période</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="period-option" data-period="7">
                                <i class="fas fa-calendar-week fa-2x mb-2"></i>
                                <div class="fw-bold">7 Derniers Jours</div>
                                <small>Historique récent</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="period-option" data-period="30">
                                <i class="fas fa-calendar fa-2x mb-2"></i>
                                <div class="fw-bold">30 Derniers Jours</div>
                                <small>1 mois complet</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="period-option" data-period="90">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <div class="fw-bold">3 Derniers Mois</div>
                                <small>Trimestre complet</small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="period-option" data-period="custom">
                                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                <div class="fw-bold">Période Personnalisée</div>
                                <small>Dates spécifiques</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="period-option" data-period="all">
                                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                <div class="fw-bold">Historique Complet</div>
                                <small>Toutes les transactions</small>
                            </div>
                        </div>
                    </div>

                    <!-- Période personnalisée -->
                    <div class="custom-period-selector mt-4 d-none" id="customPeriodSelector">
                        <h6 class="mb-3">Période Personnalisée</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                        </div>
                    </div>

                    <!-- Récapitulatif des frais -->
                    <div class="summary-card mt-4">
                        <h6 class="mb-3"><i class="fas fa-receipt me-2"></i>Récapitulatif</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="detail-label">Frais de génération</div>
                                <div class="detail-value text-danger">1,000 BIF</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Période sélectionnée</div>
                                <div class="detail-value" id="selectedPeriod">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton de génération -->
                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-danger btn-operation" id="generateHistoryBtn" onclick="generateAccountHistory()">
                            <i class="fas fa-file-pdf me-2"></i>Générer l'Historique (1,000 BIF)
                        </button>
                        <button class="btn btn-secondary" onclick="resetHistoryForm()">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                    </div>
                </div>

                <!-- Placeholder initial -->
                <div class="form-placeholder" id="historyPlaceholder">
                    <i class="fas fa-search"></i>
                    <h5>Recherche de Compte</h5>
                    <p class="mb-0">Veuillez saisir un numéro de compte dans la barre de recherche ci-dessus pour générer son historique.</p>
                </div>
            </div>
        </div>

        <!-- Section Paiement Factures -->
        <div class="tab-content d-none" id="paiement">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-credit-card me-2 text-warning"></i>Paiement de Factures
                    </h3>
                </div>
            </div>
            
            <div class="operation-card">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Module de paiement de factures - En cours de développement
                </div>
            </div>
        </div>

        <!-- Section Historique -->
        <div class="tab-content d-none" id="historique">
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">
                        <i class="fas fa-history me-2 text-secondary"></i>Historique des Transactions
                    </h3>
                </div>
            </div>
            
            <div class="operation-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Heure</th>
                                <th>Type</th>
                                <th>Compte</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mesTransactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        <i class="fas fa-inbox me-2"></i>Aucune transaction aujourd'hui
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mesTransactions as $trans): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($trans['date_heure'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $trans['categorie'] == 'DEPOT' ? 'success' : 
                                                ($trans['categorie'] == 'RETRAIT' ? 'danger' : 'primary'); 
                                        ?>">
                                            <?php echo htmlspecialchars($trans['type_libelle']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['num_compte']); ?></td>
                                    <td class="fw-bold text-<?php echo $trans['sens'] == 'CREDIT' ? 'success' : 'danger'; ?>">
                                        <?php echo $trans['sens'] == 'CREDIT' ? '+' : '-'; ?>
                                        <?php echo number_format($trans['montant'], 0, ',', ' '); ?> BIF
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($trans['statut']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Horloge en temps réel
        function updateClock() {
            const now = new Date();
            $('#liveClock').text(now.toLocaleTimeString('fr-FR'));
        }
        setInterval(updateClock, 1000);

        // Gestion des onglets
        $('.nav-link').on('click', function(e) {
            e.preventDefault();
            
            if ($(window).width() <= 768) {
                $('#sidebar').removeClass('show');
            }
            
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            
            const tabId = $(this).data('tab');
            $('.tab-content').addClass('d-none');
            $('#' + tabId).removeClass('d-none');
            
            // Réinitialiser les formulaires quand on change d'onglet
            resetAllForms();
        });


         // Détecter le type de virement quand les deux comptes sont saisis
    $('#virementNumCompteSource, #virementNumCompteDest').on('change', function() {
        const source = $('#virementNumCompteSource').val();
        const dest = $('#virementNumCompteDest').val();
        if (source && dest) {
            setTimeout(detectVirementType, 500);
        }
    });

        // Initialisation de l'interface
        function initializeInterface() {
            // Cacher tous les formulaires et montrer les placeholders
            $('.transaction-form').addClass('d-none');
            $('.client-card').removeClass('active');
            $('.form-placeholder').show();
            
            // Réinitialiser les étapes
            resetAllSteps();
            
            // INITIALISATION SPÉCIFIQUE POUR LE BILLETAGE
            initializeBilletage();
        }

        // INITIALISATION DU BILLETAGE - FONCTION AMÉLIORÉE
        function initializeBilletage() {
            console.log('Initialisation du billetage...');
            
            // S'assurer que la section billetage est visible
            $('#billetageSection').show().css({
                'display': 'block',
                'opacity': '1',
                'visibility': 'visible'
            });
            
            // Initialiser les écouteurs d'événements pour le billetage
            $('.billet-input').off('input').on('input', function() {
                calculateBilletageTotal();
            });
            
            // Calcul initial
            calculateBilletageTotal();
            
            console.log('Billetage initialisé avec succès');
        }

        // CALCUL DU TOTAL DU BILLETAGE - FONCTION AMÉLIORÉE
        function calculateBilletageTotal() {
            console.log('Calcul du billetage...');
            
            const billets = {
                10000: parseInt($('#billet10000').val()) || 0,
                5000: parseInt($('#billet5000').val()) || 0,
                2000: parseInt($('#billet2000').val()) || 0,
                1000: parseInt($('#billet1000').val()) || 0,
                500: parseInt($('#billet500').val()) || 0,
                100: parseInt($('#billet100').val()) || 0
            };
            
            console.log('Billets saisis:', billets);
            
            const total = (billets[10000] * 10000) + 
                         (billets[5000] * 5000) + 
                         (billets[2000] * 2000) + 
                         (billets[1000] * 1000) + 
                         (billets[500] * 500) + 
                         (billets[100] * 100);
            
            console.log('Total calculé:', total);
            
            $('#billetTotalValue').text(formatMontant(total));
            
            // Animation du total
            $('#billetTotalDepot').addClass('highlight');
            setTimeout(() => {
                $('#billetTotalDepot').removeClass('highlight');
            }, 500);
            
            return total;
        }

        // APPLIQUER LE BILLETAGE AU MONTANT
        window.appliquerBilletage = function() {
            const totalBilletage = calculateBilletageTotal();
            $('#depotMontant').val(totalBilletage);
            updateDepotSummary();
            
            showAlert('success', 'Montant du billetage appliqué : ' + formatMontant(totalBilletage) + ' BIF');
        };

        // RÉINITIALISER LE BILLETAGE
        window.reinitialiserBilletage = function() {
            $('.billet-input').val(0);
            calculateBilletageTotal();
            $('#depotMontant').val(0);
            updateDepotSummary();
            
            showAlert('info', 'Billetage réinitialisé');
        };

        // Gestion de l'upload de photo
        function initializePhotoUpload() {
            const photoInput = $('#photoInput');
            const photoPreview = $('#photoPreview');
            const uploadContainer = $('#photoUploadContainer');
            const uploadPlaceholder = $('#uploadPlaceholder');
            const removePhotoBtn = $('#removePhotoBtn');
            
            // Click sur le container
            uploadContainer.on('click', function(e) {
                if (!$(e.target).is('input')) {
                    photoInput.click();
                }
            });
            
            // Drag and drop
            uploadContainer.on('dragover', function(e) {
                e.preventDefault();
                uploadContainer.addClass('dragover');
            });
            
            uploadContainer.on('dragleave', function(e) {
                e.preventDefault();
                uploadContainer.removeClass('dragover');
            });
            
            uploadContainer.on('drop', function(e) {
                e.preventDefault();
                uploadContainer.removeClass('dragover');
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });
            
            // Changement de fichier via input
            photoInput.on('change', function(e) {
                if (this.files && this.files[0]) {
                    handleFileSelect(this.files[0]);
                }
            });
            
            // Suppression de photo
            removePhotoBtn.on('click', function() {
                photoInput.val('');
                photoPreview.hide();
                uploadPlaceholder.show();
                removePhotoBtn.hide();
            });
            
            function handleFileSelect(file) {
                // Vérification du type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    showAlert('danger', 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF');
                    return;
                }
                
                // Vérification de la taille (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showAlert('danger', 'Fichier trop volumineux. Taille maximale: 2MB');
                    return;
                }
                
                // Prévisualisation
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.attr('src', e.target.result);
                    photoPreview.show();
                    uploadPlaceholder.hide();
                    removePhotoBtn.show();
                };
                reader.readAsDataURL(file);
            }
        }

        // Réinitialiser toutes les étapes
        function resetAllSteps() {
            $('.step').removeClass('active');
            $('.step').each(function() {
                const stepId = $(this).attr('id');
                if (stepId && stepId.includes('step1-')) {
                    $(this).addClass('active');
                }
            });
        }

        // Réinitialiser tous les formulaires
        function resetAllForms() {
            resetDepotForm();
            resetRetraitForm();
            resetVirementForm();
            resetSituationForm();
            resetHistoryForm();
            initializeInterface();
        }

        // Mise à jour du récapitulatif création de compte en temps réel
        $('#createAccountForm input, #createAccountForm select').on('input change', function() {
            updateAccountCreationSummary();
        });

        function updateAccountCreationSummary() {
            $('#recapNomClient').text(($('#createAccountForm input[name="nom"]').val() || '-') + ' ' + ($('#createAccountForm input[name="prenom"]').val() || ''));
            $('#recapTelephone').text($('#createAccountForm input[name="telephone"]').val() || '-');
            $('#recapNumCompte').text($('#createAccountForm input[name="num_compte"]').val() || '-');
            
            const typeCompteSelect = $('#createAccountForm select[name="id_type_compte"]');
            const selectedTypeOption = typeCompteSelect.find('option:selected');
            $('#recapTypeCompte').text(selectedTypeOption.text() || '-');
            
            const agenceSelect = $('#createAccountForm select[name="id_agence_origine"]');
            const selectedAgenceOption = agenceSelect.find('option:selected');
            $('#recapAgence').text(selectedAgenceOption.text() || '-');
        }

        // Gestion du formulaire de création de compte avec upload
        $('#createAccountForm').on('submit', function(e) {
            e.preventDefault();
            createAccount();
        });

        function createAccount() {
            const form = $('#createAccountForm')[0];
            const formData = new FormData(form);
            const submitBtn = $('#createAccountBtn');
            
            // Désactiver le bouton
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Création en cours...');
            
            $.ajax({
                url: 'ajax_caissier.php',
                method: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message);
                        // Réinitialiser le formulaire après succès
                        setTimeout(() => {
                            resetCreateAccountForm();
                            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Créer le Compte');
                        }, 2000);
                    } else {
                        showAlert('danger', response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Créer le Compte');
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la création du compte');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Créer le Compte');
                }
            });
        }

        // Recherche de compte pour toutes les opérations
        function setupAccountSearch(inputId, cardType) {
            $(inputId).on('input', function() {
                const query = $(this).val().trim();
                if (query.length >= 3) {
                    searchAccount(query, cardType);
                } else {
                    hideAccountCard(cardType);
                }
            });
            
            // Recherche avec Entrée
            $(inputId).on('keypress', function(e) {
                if (e.which === 13) {
                    const query = $(this).val().trim();
                    if (query.length >= 3) {
                        searchAccount(query, cardType);
                    }
                }
            });
        }

        // Configuration des recherches
        setupAccountSearch('#searchAccountDepot', 'depot');
        setupAccountSearch('#searchAccountRetrait', 'retrait');
        setupAccountSearch('#searchAccountVirementSource', 'virement_source');
        setupAccountSearch('#searchAccountVirementDest', 'virement_dest');
        setupAccountSearch('#searchAccountSituation', 'situation');
        setupAccountSearch('#searchAccountHistory', 'history');

        function searchAccount(numCompte, type) {
            $.ajax({
                url: 'ajax_caissier.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'search_account',
                    num_compte: numCompte,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                success: function(response) {
                    if (response.success && response.account) {
                        displayAccountInfo(response.account, type);
                    } else {
                        hideAccountCard(type);
                        showAlert('warning', response.message || 'Compte non trouvé');
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    hideAccountCard(type);
                    showAlert('danger', 'Erreur lors de la recherche du compte');
                }
            });
        }

        function displayAccountInfo(account, type) {
            const selectors = getSelectorsForType(type);
            
            // Mettre à jour les informations du compte
            $(selectors.photo).attr('src', account.photo_profil_url || 'assets/images/default-avatar.png');
            $(selectors.name).text(account.client_nom + ' ' + account.client_prenom);
            $(selectors.contact).text((account.client_telephone || '') + (account.client_email ? ' • ' + account.client_email : ''));
            $(selectors.number).text(account.num_compte);
            
            if (selectors.type) {
                $(selectors.type).text(account.type_compte_libelle);
            }
            
            if (selectors.agence) {
                $(selectors.agence).text(account.agence_origine_nom || '-');
            }
            
            if (selectors.status) {
                $(selectors.status).text(account.statut);
            }
            
            if (selectors.balance) {
                $(selectors.balance).text(formatMontant(account.solde_disponible) + ' BIF');
            }
            
            if (selectors.available) {
                $(selectors.available).text(formatMontant(account.solde_disponible) + ' BIF');
            }
            
            $(selectors.mainBalance).text(formatMontant(account.solde_disponible) + ' BIF');
            
            // Afficher la carte client et cacher le placeholder
            $(selectors.card).addClass('active');
            $(selectors.placeholder).hide();
            
            // Actions spécifiques selon le type
            if (type === 'history') {
                $('#periodSelector').removeClass('d-none');
            }
            
            if (type === 'situation') {
                loadRecentTransactions(account.num_compte);
            }
            
            if (type !== 'general') {
                // Pré-remplir les champs cachés
                if (type === 'depot') {
                    $('#depotNumCompte').val(account.num_compte);
                } else if (type === 'retrait') {
                    $('#retraitNumCompte').val(account.num_compte);
                    $('#availableBalanceRetrait').text(formatMontant(account.solde_disponible) + ' BIF');
                } else if (type === 'virement_source') {
                    $('#virementNumCompteSource').val(account.num_compte);
                    $('#availableBalanceVirement').text(formatMontant(account.solde_disponible) + ' BIF');
                    updateSteps('virement', 2);
                    checkVirementFormReady();
                } else if (type === 'virement_dest') {
                    $('#virementNumCompteDest').val(account.num_compte);
                    updateSteps('virement', 2);
                    checkVirementFormReady();
                }
            }
        }

        function updateSteps(operation, stepNumber) {
            // Désactiver toutes les étapes
            $(`#${operation} .step`).removeClass('active');
            
            // Activer les étapes jusqu'au stepNumber
            for (let i = 1; i <= stepNumber; i++) {
                $(`#step${i}-${operation}`).addClass('active');
            }
        }

     // MODIFICATION: Mettre à jour la fonction checkVirementFormReady
function checkVirementFormReady() {
    const source = $('#virementNumCompteSource').val();
    const dest = $('#virementNumCompteDest').val();
    
    if (source && dest) {
        $('#virementFormSection').removeClass('d-none');
        $('#virementPlaceholder').hide();
        updateSteps('virement', 3);
        
        // Détecter automatiquement le type de virement
        detectVirementType();
    }
}

        function hideAccountCard(type) {
            const selectors = getSelectorsForType(type);
            $(selectors.card).removeClass('active');
            $(selectors.placeholder).show();
            
            if (type === 'history') {
                $('#periodSelector').addClass('d-none');
            }
            
            if (type === 'situation') {
                $('#recentTransactions').empty();
            }
        }

        function getSelectorsForType(type) {
            const base = {
                'depot': {
                    card: '#clientCardDepot',
                    photo: '#clientPhotoDepot',
                    name: '#clientNameDepot',
                    contact: '#clientContactDepot',
                    number: '#accountNumberDepot',
                    type: '#accountTypeDepot',
                    agence: '#accountAgenceDepot',
                    balance: '#accountBalanceDepot',
                    mainBalance: '#accountBalanceDepot',
                    formSection: '#depotFormSection',
                    placeholder: '#depotPlaceholder'
                },
                'retrait': {
                    card: '#clientCardRetrait',
                    photo: '#clientPhotoRetrait',
                    name: '#clientNameRetrait',
                    contact: '#clientContactRetrait',
                    number: '#accountNumberRetrait',
                    type: '#accountTypeRetrait',
                    balance: '#accountBalanceRetrait',
                    mainBalance: '#accountBalanceMainRetrait',
                    formSection: '#retraitFormSection',
                    placeholder: '#retraitPlaceholder'
                },
                'virement_source': {
                    card: '#clientCardVirementSource',
                    photo: '#clientPhotoVirementSource',
                    name: '#clientNameVirementSource',
                    contact: '#clientContactVirementSource',
                    number: '#accountNumberVirementSource',
                    type: '#accountTypeVirementSource',
                    mainBalance: '#accountBalanceVirementSource',
                    formSection: '#virementFormSection',
                    placeholder: '#virementPlaceholder'
                },
                'virement_dest': {
                    card: '#clientCardVirementDest',
                    photo: '#clientPhotoVirementDest',
                    name: '#clientNameVirementDest',
                    contact: '#clientContactVirementDest',
                    number: '#accountNumberVirementDest',
                    type: '#accountTypeVirementDest',
                    mainBalance: '#accountBalanceVirementDest',
                    formSection: '#virementFormSection',
                    placeholder: '#virementPlaceholder'
                },
                'situation': {
                    card: '#clientCardSituation',
                    photo: '#clientPhotoSituation',
                    name: '#clientNameSituation',
                    contact: '#clientContactSituation',
                    number: '#accountNumberSituation',
                    type: '#accountTypeSituation',
                    agence: '#accountAgenceSituation',
                    status: '#accountStatusSituation',
                    balance: '#accountBalanceSituation',
                    available: '#accountAvailableSituation',
                    mainBalance: '#accountBalanceSituation',
                    placeholder: '#situationPlaceholder'
                },
                'history': {
                    card: '#clientCardHistory',
                    photo: '#clientPhotoHistory',
                    name: '#clientNameHistory',
                    contact: '#clientContactHistory',
                    number: '#accountNumberHistory',
                    type: '#accountTypeHistory',
                    balance: '#accountBalanceHistory',
                    mainBalance: '#accountBalanceHistory',
                    periodSelector: '#periodSelector',
                    placeholder: '#historyPlaceholder'
                }
            };
            
            return base[type] || base['depot'];
        }

        function formatMontant(montant) {
            return Math.round(montant).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        // Mise à jour des résumés en temps réel
        function updateDepotSummary() {
            const montant = parseFloat($('#depotMontant').val()) || 0;
            const selectedOption = $('#depotType option:selected');
            const fraisFixe = parseFloat(selectedOption.data('frais-fixe')) || 0;
            const fraisPourcent = parseFloat(selectedOption.data('frais-pourcent')) || 0;
            
            const frais = fraisFixe + (montant * fraisPourcent / 100);
            const currentBalance = parseFloat($('#accountBalanceDepot').text().replace(/[^\d]/g, '')) || 0;
            const newBalance = currentBalance + montant;
            
            $('#summaryDepotMontant').text(formatMontant(montant) + ' BIF');
            $('#summaryDepotFrais').text(formatMontant(frais) + ' BIF');
            $('#summaryDepotNewBalance').text(formatMontant(newBalance) + ' BIF');
            $('#depotSummary').removeClass('d-none');
            
            if (montant > 0) {
                updateSteps('depot', 3);
            }
        }

        function updateRetraitSummary() {
            const montant = parseFloat($('#retraitMontant').val()) || 0;
            const selectedOption = $('#retraitType option:selected');
            const fraisFixe = parseFloat(selectedOption.data('frais-fixe')) || 0;
            const fraisPourcent = parseFloat(selectedOption.data('frais-pourcent')) || 0;
            
            const frais = fraisFixe + (montant * fraisPourcent / 100);
            const currentBalance = parseFloat($('#accountBalanceMainRetrait').text().replace(/[^\d]/g, '')) || 0;
            const newBalance = currentBalance - montant - frais;
            
            $('#summaryRetraitMontant').text(formatMontant(montant) + ' BIF');
            $('#summaryRetraitFrais').text(formatMontant(frais) + ' BIF');
            $('#summaryRetraitNewBalance').text(formatMontant(newBalance) + ' BIF');
            $('#retraitSummary').removeClass('d-none');
            
            if (montant > 0) {
                updateSteps('retrait', 3);
            }
        }


        // NOUVELLE FONCTION: Détection automatique du type de virement
function detectVirementType() {
    const numCompteSource = $('#virementNumCompteSource').val();
    const numCompteDest = $('#virementNumCompteDest').val();
    
    if (!numCompteSource || !numCompteDest) {
        return;
    }
    
    $.ajax({
        url: 'ajax_caissier.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'detect_virement_type',
            num_compte_source: numCompteSource,
            num_compte_dest: numCompteDest,
            csrf_token: '<?php echo $csrf_token; ?>'
        },
        success: function(response) {
            if (response.success) {
                const selectVirement = $('#virementType');
                selectVirement.empty();
                selectVirement.append('<option value="">Sélectionnez...</option>');
                
                // Ajouter les types disponibles
                response.types_disponibles.forEach(type => {
                    selectVirement.append(
                        $('<option>', {
                            value: type.id_type_transaction,
                            text: type.libelle,
                            'data-frais-fixe': type.frais_fixe,
                            'data-frais-pourcent': type.frais_pourcentage
                        })
                    );
                });
                
                // Sélectionner automatiquement le type recommandé
                if (response.type_recommande) {
                    selectVirement.val(response.type_recommande.id_type_transaction);
                    
                    // Afficher une info sur le type de virement
                    const typeInfo = response.meme_agence ? 
                        `Virement interne (même agence: ${response.agence_source})` :
                        `Virement externe (${response.agence_source} → ${response.agence_dest})`;
                    
                    // Ajouter une info sous le select
                    let infoElement = $('#virementTypeInfo');
                    if (infoElement.length === 0) {
                        infoElement = $('<div class="form-text text-info mt-1" id="virementTypeInfo"></div>');
                        selectVirement.after(infoElement);
                    }
                    infoElement.html(`<i class="fas fa-info-circle me-1"></i>${typeInfo}`);
                    
                    // Mettre à jour le résumé
                    updateVirementSummary();
                }
            }
        },
        error: function(xhr) {
            console.error('Erreur détection type virement:', xhr.responseText);
        }
    });
}

        function updateVirementSummary() {
            const montant = parseFloat($('#virementMontant').val()) || 0;
            const selectedOption = $('#virementType option:selected');
            const fraisFixe = parseFloat(selectedOption.data('frais-fixe')) || 0;
            const fraisPourcent = parseFloat(selectedOption.data('frais-pourcent')) || 0;
            
            const frais = fraisFixe + (montant * fraisPourcent / 100);
            const currentBalance = parseFloat($('#accountBalanceVirementSource').text().replace(/[^\d]/g, '')) || 0;
            const newBalance = currentBalance - montant - frais;
            
            $('#summaryVirementMontant').text(formatMontant(montant) + ' BIF');
            $('#summaryVirementFrais').text(formatMontant(frais) + ' BIF');
            $('#summaryVirementNewBalance').text(formatMontant(newBalance) + ' BIF');
            $('#virementSummary').removeClass('d-none');
            
            if (montant > 0) {
                updateSteps('virement', 4);
            }
        }

        // Événements pour les mises à jour en temps réel
        $('#depotMontant, #depotType').on('input change', function() {
            updateDepotSummary();
        });

        $('#retraitMontant, #retraitType').on('input change', function() {
            updateRetraitSummary();
        });

        $('#virementMontant, #virementType').on('input change', function() {
            updateVirementSummary();
        });

        // Gestion des formulaires
        $('#depotForm').on('submit', function(e) {
            e.preventDefault();
            processTransaction('depot');
        });

        $('#retraitForm').on('submit', function(e) {
            e.preventDefault();
            processTransaction('retrait');
        });

        $('#virementForm').on('submit', function(e) {
            e.preventDefault();
            processTransaction('virement');
        });

        function processTransaction(type) {
            const form = $('#' + type + 'Form');
            const submitBtn = $('#' + type + 'SubmitBtn');
            
            // Désactiver le bouton
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Traitement...');
            
            // Récupérer l'action correcte
            let action = '';
            switch(type) {
                case 'depot':
                    action = 'process_depot';
                    break;
                case 'retrait':
                    action = 'process_retrait';
                    break;
                case 'virement':
                    action = 'process_virement';
                    break;
            }
            
            // Préparer les données
            const formData = form.serializeArray();
            formData.push({name: 'action', value: action});
            
            $.ajax({
                url: 'ajax_caissier.php',
                method: 'POST',
                dataType: 'json',
                data: $.param(formData),
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message);
                        // Recharger la page après succès
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert('danger', response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Confirmer l\'opération');
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du traitement');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Confirmer l\'opération');
                }
            });
        }

        // NOUVEAU: Gestion de la sélection de période pour l'historique
        $('.period-option').on('click', function() {
            $('.period-option').removeClass('active');
            $(this).addClass('active');
            
            const period = $(this).data('period');
            $('#selectedPeriod').text($(this).find('.fw-bold').text());
            
            if (period === 'custom') {
                $('#customPeriodSelector').removeClass('d-none');
            } else {
                $('#customPeriodSelector').addClass('d-none');
            }
        });

        window.generateAccountHistory = function() {
    const numCompte = $('#accountNumberHistory').text();
    const selectedPeriod = $('.period-option.active').data('period');
    let startDate = '';
    let endDate = '';

    if (selectedPeriod === 'custom') {
        startDate = $('#startDate').val();
        endDate = $('#endDate').val();
        
        if (!startDate || !endDate) {
            showAlert('warning', 'Veuillez sélectionner les dates de début et de fin');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            showAlert('warning', 'La date de début doit être antérieure à la date de fin');
            return;
        }
    }

    if (!numCompte || numCompte === '-') {
        showAlert('warning', 'Veuillez d\'abord rechercher un compte valide');
        return;
    }

    showLoading('Génération de l\'historique en cours...');

    // Préparer les données
    const formData = {
        action: 'generate_account_history',
        num_compte: numCompte,
        period: selectedPeriod,
        csrf_token: '<?php echo $csrf_token; ?>'
    };

    // Ajouter les dates si période personnalisée
    if (selectedPeriod === 'custom') {
        formData.start_date = startDate;
        formData.end_date = endDate;
    }

    $.ajax({
        url: 'ajax_caissier.php',
        method: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                showAlert('success', response.message);
                
                // Afficher le lien de téléchargement si disponible
                if (response.download_url) {
                    setTimeout(() => {
                        // Supprimer les anciens liens de téléchargement
                        $('.download-link-alert').remove();
                        
                        const downloadHtml = `
                            <div class="alert alert-success mt-3 download-link-alert">
                                <i class="fas fa-download me-2"></i>
                                Votre historique est prêt : 
                                <a href="${response.download_url}" class="alert-link" target="_blank">
                                    Télécharger le PDF
                                </a>
                                <br><small class="text-muted">Nouveau solde: ${formatMontant(response.nouveau_solde)} BIF</small>
                            </div>
                        `;
                        $('#generateHistoryBtn').after(downloadHtml);
                    }, 1000);
                }
                
                // Réinitialiser après succès
                setTimeout(() => {
                    resetHistoryForm();
                }, 5000);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            hideLoading();
            console.error('Erreur génération historique:', xhr.responseText);
            
            // Message d'erreur plus détaillé
            let errorMessage = 'Erreur lors de la génération de l\'historique';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // Utiliser le message par défaut
            }
            
            showAlert('danger', errorMessage);
        }
    });
};


        // NOUVEAU: Charger les transactions récentes pour la situation de compte
        function loadRecentTransactions(numCompte) {
            $.ajax({
                url: 'ajax_caissier.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_recent_transactions',
                    num_compte: numCompte,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayRecentTransactions(response.transactions);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                }
            });
        }

        // NOUVEAU: Afficher les transactions récentes
        function displayRecentTransactions(transactions) {
            const container = $('#recentTransactions');
            container.empty();
            
            if (transactions.length === 0) {
                container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>Aucune transaction récente</p>
                    </div>
                `);
                return;
            }
            
            transactions.forEach(transaction => {
                const isCredit = transaction.sens === 'CREDIT';
                const transactionHtml = `
                    <div class="transaction-item ${isCredit ? 'credit' : 'debit'}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${transaction.type_libelle}</strong>
                                <div class="text-muted small">${transaction.date_heure}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold ${isCredit ? 'text-success' : 'text-danger'}">
                                    ${isCredit ? '+' : '-'} ${formatMontant(transaction.montant)} BIF
                                </div>
                                <div class="text-muted small">Solde: ${formatMontant(transaction.solde_apres)} BIF</div>
                            </div>
                        </div>
                        ${transaction.description ? `<div class="mt-2 small">${transaction.description}</div>` : ''}
                    </div>
                `;
                container.append(transactionHtml);
            });
        }

        // UTILITAIRES
        function showAlert(type, message) {
            // Supprimer les alertes existantes
            $('.custom-alert').remove();
            
            const alertClass = {
                'success': 'alert-success',
                'danger': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';
            
            const icon = {
                'success': '✓',
                'danger': '✗',
                'warning': '⚠',
                'info': 'ℹ'
            }[type] || 'ℹ';
            
            const alertHtml = `
                <div class="custom-alert alert ${alertClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     style="z-index: 9999; min-width: 400px;" role="alert">
                    <strong>${icon}</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            // Auto-dismiss après 5 secondes pour les succès
            if (type === 'success') {
                setTimeout(() => {
                    $('.custom-alert').fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }

        function showLoading(message = 'Traitement en cours...') {
            // Supprimer les loaders existants
            hideLoading();
            
            const loadingHtml = `
                <div class="loading-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div class="loading-content text-center text-white">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <div class="loading-message mt-2">${message}</div>
                    </div>
                </div>
            `;
            
            $('body').append(loadingHtml);
            
            // Empêcher l'interaction avec le contenu pendant le chargement
            $('body').addClass('loading-active');
        }

        function hideLoading() {
            $('.loading-overlay').remove();
            $('body').removeClass('loading-active');
        }

        // FONCTIONS GLOBALES AMÉLIORÉES

        window.proceedToDepotForm = function() {
            $('#clientCardDepot').hide();
            $('#depotFormSection').removeClass('d-none');
            $('#depotPlaceholder').hide();
            updateSteps('depot', 3);
            
            // INITIALISATION EXPLICITE DU BILLETAGE
            initializeBilletage();
        };

        window.proceedToRetraitForm = function() {
            $('#clientCardRetrait').hide();
            $('#retraitFormSection').removeClass('d-none');
            $('#retraitPlaceholder').hide();
            updateSteps('retrait', 3);
        };

        window.proceedToVirementForm = function() {
            $('.client-card').hide();
            $('#virementFormSection').removeClass('d-none');
            $('#virementPlaceholder').hide();
            updateSteps('virement', 3);
        };

        window.focusDestInput = function() {
            $('#searchAccountVirementDest').focus();
        };

        window.resetDepotSearch = function() {
            $('#searchAccountDepot').val('');
            $('#clientCardDepot').removeClass('active');
            $('#depotPlaceholder').show();
            updateSteps('depot', 1);
        };

        window.resetRetraitSearch = function() {
            $('#searchAccountRetrait').val('');
            $('#clientCardRetrait').removeClass('active');
            $('#retraitPlaceholder').show();
            updateSteps('retrait', 1);
        };

        window.resetSituationForm = function() {
            $('#searchAccountSituation').val('');
            $('#clientCardSituation').removeClass('active');
            $('#situationPlaceholder').show();
            $('#recentTransactions').empty();
        };

        window.resetHistoryForm = function() {
            $('#searchAccountHistory').val('');
            $('#clientCardHistory').removeClass('active');
            $('#periodSelector').addClass('d-none');
            $('#historyPlaceholder').show();
            $('.period-option').removeClass('active');
            $('#customPeriodSelector').addClass('d-none');
            $('#startDate').val('');
            $('#endDate').val('');
        };

        window.switchTab = function(tabId) {
            $('.nav-link').removeClass('active');
            $(`.nav-link[data-tab="${tabId}"]`).addClass('active');
            $('.tab-content').addClass('d-none');
            $('#' + tabId).removeClass('d-none');
            initializeInterface();
        };

        window.resetDepotForm = function() {
            $('#depotForm')[0].reset();
            $('#depotFormSection').addClass('d-none');
            $('#clientCardDepot').removeClass('active');
            $('#searchAccountDepot').val('');
            $('#depotSummary').addClass('d-none');
            $('#depotPlaceholder').show();
            resetAllSteps();
            updateSteps('depot', 1);
        };

        window.resetRetraitForm = function() {
            $('#retraitForm')[0].reset();
            $('#retraitFormSection').addClass('d-none');
            $('#clientCardRetrait').removeClass('active');
            $('#searchAccountRetrait').val('');
            $('#retraitSummary').addClass('d-none');
            $('#retraitPlaceholder').show();
            resetAllSteps();
            updateSteps('retrait', 1);
        };

        window.resetVirementForm = function() {
            $('#virementForm')[0].reset();
            $('#virementFormSection').addClass('d-none');
            $('.client-card').removeClass('active');
            $('#searchAccountVirementSource').val('');
            $('#searchAccountVirementDest').val('');
            $('#virementSummary').addClass('d-none');
            $('#virementPlaceholder').show();
            resetAllSteps();
            updateSteps('virement', 1);
        };

        window.resetCreateAccountForm = function() {
            $('#createAccountForm')[0].reset();
            $('#photoPreview').hide();
            $('#uploadPlaceholder').show();
            $('#removePhotoBtn').hide();
            updateAccountCreationSummary();
        };

        // Menu mobile
        $('#mobileMenuBtn').on('click', function() {
            $('#sidebar').toggleClass('show');
        });

        // Fermer la sidebar en cliquant à l'extérieur sur mobile
        $(document).on('click', function(e) {
            if ($(window).width() <= 768 && 
                !$(e.target).closest('#sidebar').length && 
                !$(e.target).closest('#mobileMenuBtn').length) {
                $('#sidebar').removeClass('show');
            }
        });

        // Initialiser l'interface
        initializeInterface();
        initializePhotoUpload();
        updateAccountCreationSummary();
    });
</script>
</body>
</html>