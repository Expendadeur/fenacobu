<?php
// DashConseiller.php - Dashboard Conseiller avec fonctionnalités complètes
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Conseiller') {
    header('Location: login.php');
    exit();
}

// Application des en-têtes de sécurité
SecurityManager::setSecurityHeaders();

// Récupération de la connexion
$db = DatabaseConfig::getConnection();

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityManager::generateSecureToken();
}
$csrf_token = $_SESSION['csrf_token'];

// Récupération des informations du conseiller
$conseiller_id = $_SESSION['user_id'];
$conseiller = $db->query("SELECT * FROM agents WHERE id_agent = $conseiller_id")->fetch();

// ========================================
// RÉCUPÉRATION DES DONNÉES
// ========================================

// Statistiques pour le conseiller
try {
    $stats = [
        'clients_total' => $db->query("SELECT COUNT(*) FROM clients WHERE actif = 1")->fetchColumn(),
        'comptes_actifs' => $db->query("SELECT COUNT(*) FROM comptes WHERE statut = 'Actif'")->fetchColumn(),
        'demandes_attente' => $db->query("SELECT COUNT(*) FROM demandes_credit WHERE statut IN ('En attente', 'En étude')")->fetchColumn(),
        'clients_nouveaux_mois' => $db->query("SELECT COUNT(*) FROM clients WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn(),
        'comptes_ouverts_mois' => $db->query("SELECT COUNT(*) FROM comptes WHERE MONTH(date_creation) = MONTH(CURDATE()) AND YEAR(date_creation) = YEAR(CURDATE())")->fetchColumn()
    ];
} catch(PDOException $e) {
    $stats = ['clients_total' => 0, 'comptes_actifs' => 0, 'demandes_attente' => 0, 'clients_nouveaux_mois' => 0, 'comptes_ouverts_mois' => 0];
    error_log("Erreur récupération stats: " . $e->getMessage());
}

// Récupération des clients
try {
    $clients = $db->query("
        SELECT c.*, 
               COUNT(DISTINCT co.id_compte) as nb_comptes,
               SUM(co.solde) as total_solde
        FROM clients c
        LEFT JOIN comptes co ON c.id_client = co.id_client AND co.statut = 'Actif'
        GROUP BY c.id_client
        ORDER BY c.created_at DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $clients = [];
    error_log("Erreur clients: " . $e->getMessage());
}

// Récupération des comptes
try {
    $comptes = $db->query("
        SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom, cl.photo_profil,
               tc.libelle as type_compte_libelle, tc.code as type_compte_code
        FROM comptes c 
        LEFT JOIN clients cl ON c.id_client = cl.id_client 
        LEFT JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
        ORDER BY c.date_creation DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $comptes = [];
}

// Récupération des types de comptes
try {
    $typesCompte = $db->query("SELECT * FROM types_compte WHERE actif = 1 ORDER BY code")->fetchAll();
} catch(PDOException $e) {
    $typesCompte = [];
}

// Récupération des types de crédit
try {
    $typesCredit = $db->query("SELECT * FROM types_credit ORDER BY nom")->fetchAll();
} catch(PDOException $e) {
    $typesCredit = [];
}

// Récupération des demandes de crédit
try {
    $demandesCredit = $db->query("
        SELECT dc.*, 
               cl.nom as client_nom, cl.prenom as client_prenom, cl.photo_profil,
               cl.score_credit, cl.revenu_mensuel,
               tc.nom as type_credit_nom, tc.taux_interet, tc.duree_max_mois
        FROM demandes_credit dc 
        LEFT JOIN clients cl ON dc.id_client = cl.id_client 
        LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit 
        ORDER BY dc.date_demande DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $demandesCredit = [];
}

// Récupération des dossiers de crédit actifs
try {
    $creditsActifs = $db->query("
        SELECT dcr.*, dc.id_client, dc.montant, dc.duree_mois,
               cl.nom as client_nom, cl.prenom as client_prenom,
               tc.nom as type_credit_nom, tc.taux_interet,
               COUNT(ec.id_echeance) as total_echeances,
               SUM(CASE WHEN ec.statut = 'Payé' THEN 1 ELSE 0 END) as echeances_payees
        FROM dossiers_credit dcr
        LEFT JOIN demandes_credit dc ON dcr.id_demande = dc.id_demande
        LEFT JOIN clients cl ON dc.id_client = cl.id_client
        LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit
        LEFT JOIN echeances_credit ec ON dcr.id_dossier = ec.id_dossier
        WHERE dcr.statut = 'Actif'
        GROUP BY dcr.id_dossier
        ORDER BY dcr.date_approbation DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $creditsActifs = [];
}

// Activités récentes
try {
    $activitesRecentes = $db->query("
        SELECT 'client' as type, CONCAT(nom, ' ', prenom) as description, created_at as date_activite
        FROM clients 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'compte' as type, CONCAT('Compte ', num_compte) as description, date_creation as date_activite
        FROM comptes 
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'credit' as type, CONCAT('Demande crédit #', id_demande) as description, date_demande as date_activite
        FROM demandes_credit 
        WHERE date_demande >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY date_activite DESC
        LIMIT 10
    ")->fetchAll();
} catch(PDOException $e) {
    $activitesRecentes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Conseiller - FENACOBU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        :root {
            --primary-red: #d32f2f;
            --primary-green: #2e7d32;
            --primary-white: #ffffff;
            --light-gray: #f5f5f5;
            --dark-gray: #333333;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(90deg, var(--primary-red) 0%, var(--primary-green) 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background-color: var(--primary-white);
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 56px;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: var(--dark-gray);
            border-left: 3px solid transparent;
            padding: 12px 20px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: var(--light-gray);
            border-left: 3px solid var(--primary-red);
            color: var(--primary-red);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(90deg, var(--primary-red) 0%, var(--primary-green) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            padding: 16px 20px;
        }
        
        .btn-primary {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #b71c1c;
            border-color: #b71c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(211, 47, 47, 0.3);
        }
        
        .btn-success {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-success:hover {
            background-color: #1b5e20;
            border-color: #1b5e20;
        }
        
        .stat-card {
            text-align: center;
            padding: 24px;
            background: white;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 8px 0;
        }
        
        .stat-card .label {
            font-size: 0.95rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .client-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-green);
        }
        
        .client-photo-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-green);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .photo-upload-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            margin: 0 auto;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .photo-upload-preview:hover {
            border-color: var(--primary-green);
            background-color: #e8f5e9;
        }
        
        .photo-upload-preview img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .table {
            font-size: 0.95rem;
        }
        
        .table th {
            background-color: var(--light-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
        }
        
        .modal-header {
            background: linear-gradient(90deg, var(--primary-red) 0%, var(--primary-green) 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .activity-item {
            padding: 12px;
            border-left: 3px solid var(--primary-green);
            background-color: #f8f9fa;
            margin-bottom: 8px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .credit-score {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .score-excellent { background: #4caf50; color: white; }
        .score-good { background: #8bc34a; color: white; }
        .score-fair { background: #ffc107; color: #333; }
        .score-poor { background: #ff9800; color: white; }
        .score-bad { background: #f44336; color: white; }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            min-width: 300px;
        }
        
        .loading-spinner-large {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 24px;
            background: var(--primary-green);
            color: white;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            background: #1b5e20;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .info-section h6 {
            color: var(--primary-red);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                top: 0;
            }
            
            .stat-card .number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay de chargement global -->
    <div class="loading-overlay" id="globalLoading">
        <div class="loading-content">
            <div class="loading-spinner-large"></div>
            <div class="loading-text" id="loadingMessage">Traitement en cours...</div>
            <div class="loading-subtext" id="loadingSubtext">Veuillez patienter</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-tie me-2"></i>FENACOBU - Espace Conseiller
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($conseiller['first_name'] . ' ' . $conseiller['last_name']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 p-0 sidebar">
                <nav class="nav flex-column py-3">
                    <a class="nav-link active" href="#dashboard" data-bs-toggle="tab">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                    <a class="nav-link" href="#clients" data-bs-toggle="tab">
                        <i class="fas fa-users"></i> Gestion Clients
                    </a>
                    <a class="nav-link" href="#comptes" data-bs-toggle="tab">
                        <i class="fas fa-wallet"></i> Gestion Comptes
                    </a>
                    <a class="nav-link" href="#demandes-credit" data-bs-toggle="tab">
                        <i class="fas fa-hand-holding-usd"></i> Demandes de Crédit
                    </a>
                    <a class="nav-link" href="#credits-actifs" data-bs-toggle="tab">
                        <i class="fas fa-file-invoice-dollar"></i> Crédits Actifs
                    </a>
                    <a class="nav-link" href="#historique" data-bs-toggle="tab">
                        <i class="fas fa-history"></i> Historique
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 py-4">
                <div class="tab-content">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard">
                        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>Tableau de Bord</h2>
                        
                        <!-- Statistiques -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-users text-primary"></i>
                                    <div class="number"><?php echo number_format($stats['clients_total']); ?></div>
                                    <div class="label">Clients Total</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-wallet text-success"></i>
                                    <div class="number"><?php echo number_format($stats['comptes_actifs']); ?></div>
                                    <div class="label">Comptes Actifs</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-clock text-warning"></i>
                                    <div class="number"><?php echo number_format($stats['demandes_attente']); ?></div>
                                    <div class="label">Demandes en Attente</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-user-plus text-info"></i>
                                    <div class="number"><?php echo number_format($stats['clients_nouveaux_mois']); ?></div>
                                    <div class="label">Nouveaux ce mois</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Activités récentes et Alertes -->
                        <div class="row">
                            <div class="col-lg-8 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-history me-2"></i> Activités Récentes (7 derniers jours)
                                    </div>
                                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                        <?php if (empty($activitesRecentes)): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Aucune activité récente.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($activitesRecentes as $activite): ?>
                                                <div class="activity-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-<?php 
                                                                echo $activite['type'] == 'client' ? 'user-plus' : 
                                                                    ($activite['type'] == 'compte' ? 'wallet' : 'hand-holding-usd'); 
                                                            ?> me-2"></i>
                                                            <strong><?php echo htmlspecialchars($activite['description']); ?></strong>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($activite['date_activite'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-bell me-2"></i> Alertes & Notifications
                                    </div>
                                    <div class="card-body">
                                        <?php if ($stats['demandes_attente'] > 0): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong><?php echo $stats['demandes_attente']; ?> demande(s)</strong> de crédit à traiter
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($stats['clients_nouveaux_mois'] > 0): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong><?php echo $stats['clients_nouveaux_mois']; ?> nouveau(x) client(s)</strong> ce mois
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Vous êtes connecté en tant que Conseiller
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <i class="fas fa-chart-pie me-2"></i> Actions Rapides
                                    </div>
                                    <div class="card-body">
                                        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                            <i class="fas fa-user-plus me-2"></i>Nouveau Client
                                        </button>
                                        <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#openCompteModal">
                                            <i class="fas fa-wallet me-2"></i>Ouvrir un Compte
                                        </button>
                                        <button class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#addDemandeCreditModal">
                                            <i class="fas fa-hand-holding-usd me-2"></i>Demande de Crédit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clients Tab -->
                    <div class="tab-pane fade" id="clients">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-users me-2"></i>Gestion des Clients</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                <i class="fas fa-plus me-2"></i>Nouveau Client
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Clients
                            </div>
                            <div class="card-body">
                                <table id="clientsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Nom Complet</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Score Crédit</th>
                                            <th>Nb Comptes</th>
                                            <th>Total Solde</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td>
                                                <?php if ($client['photo_profil']): ?>
                                                    <img src="<?php echo htmlspecialchars($client['photo_profil']); ?>" class="client-photo" alt="Photo">
                                                <?php else: ?>
                                                    <div class="client-photo bg-secondary d-flex align-items-center justify-content-center text-white">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($client['telephone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="credit-score score-<?php 
                                                    echo $client['score_credit'] >= 750 ? 'excellent' : 
                                                        ($client['score_credit'] >= 650 ? 'good' : 
                                                        ($client['score_credit'] >= 550 ? 'fair' : 
                                                        ($client['score_credit'] >= 450 ? 'poor' : 'bad'))); 
                                                ?>">
                                                    <?php echo $client['score_credit']; ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $client['nb_comptes'] ?? 0; ?></span></td>
                                            <td><strong><?php echo number_format($client['total_solde'] ?? 0, 0, ',', ' '); ?> BIF</strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $client['actif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $client['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-client" 
                                                        data-id="<?php echo $client['id_client']; ?>"
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary edit-client" 
                                                        data-id="<?php echo $client['id_client']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success open-compte-for-client" 
                                                        data-id="<?php echo $client['id_client']; ?>"
                                                        data-nom="<?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>"
                                                        title="Ouvrir un compte">
                                                    <i class="fas fa-wallet"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Comptes Tab -->
                    <div class="tab-pane fade" id="comptes">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-wallet me-2"></i>Gestion des Comptes</h2>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#openCompteModal">
                                <i class="fas fa-plus me-2"></i>Ouvrir un Compte
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Comptes
                            </div>
                            <div class="card-body">
                                <table id="comptesTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>N° Compte</th>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th>Solde</th>
                                            <th>Statut</th>
                                            <th>Date Création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comptes as $compte): ?>
                                        <tr>
                                            <td>
                                                <?php if ($compte['photo_profil']): ?>
                                                    <img src="<?php echo htmlspecialchars($compte['photo_profil']); ?>" class="client-photo" alt="Photo">
                                                <?php else: ?>
                                                    <div class="client-photo bg-secondary d-flex align-items-center justify-content-center text-white">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($compte['num_compte']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($compte['client_nom'] . ' ' . $compte['client_prenom']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $compte['type_compte_code'] == 'EPARGNE' ? 'success' : 
                                                        ($compte['type_compte_code'] == 'COURANT' ? 'primary' : 
                                                        ($compte['type_compte_code'] == 'ENTREPRISE' ? 'warning' : 'info')); 
                                                ?>">
                                                    <?php echo htmlspecialchars($compte['type_compte_libelle']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo number_format($compte['solde'], 0, ',', ' '); ?> BIF</strong></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $compte['statut'] == 'Actif' ? 'success' : 
                                                        ($compte['statut'] == 'Suspendu' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $compte['statut']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($compte['date_creation'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-compte" 
                                                        data-id="<?php echo $compte['id_compte']; ?>"
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($compte['statut'] == 'Actif'): ?>
                                                <button class="btn btn-sm btn-outline-warning suspend-compte" 
                                                        data-id="<?php echo $compte['id_compte']; ?>"
                                                        data-num="<?php echo htmlspecialchars($compte['num_compte']); ?>"
                                                        title="Suspendre">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger close-compte" 
                                                        data-id="<?php echo $compte['id_compte']; ?>"
                                                        data-num="<?php echo htmlspecialchars($compte['num_compte']); ?>"
                                                        title="Fermer">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                                <?php elseif ($compte['statut'] == 'Suspendu'): ?>
                                                <button class="btn btn-sm btn-outline-success activate-compte" 
                                                        data-id="<?php echo $compte['id_compte']; ?>"
                                                        data-num="<?php echo htmlspecialchars($compte['num_compte']); ?>"
                                                        title="Réactiver">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Demandes de Crédit Tab -->
                    <div class="tab-pane fade" id="demandes-credit">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-hand-holding-usd me-2"></i>Demandes de Crédit</h2>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addDemandeCreditModal">
                                <i class="fas fa-plus me-2"></i>Nouvelle Demande
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-clock me-2"></i> Toutes les Demandes de Crédit
                            </div>
                            <div class="card-body">
                                <table id="demandesCreditTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Photo</th>
                                            <th>Client</th>
                                            <th>Type Crédit</th>
                                            <th>Montant</th>
                                            <th>Durée</th>
                                            <th>Score</th>
                                            <th>Statut</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demandesCredit as $demande): ?>
                                        <tr>
                                            <td><?php echo $demande['id_demande']; ?></td>
                                            <td>
                                                <?php if ($demande['photo_profil']): ?>
                                                    <img src="<?php echo htmlspecialchars($demande['photo_profil']); ?>" class="client-photo" alt="Photo">
                                                <?php else: ?>
                                                    <div class="client-photo bg-secondary d-flex align-items-center justify-content-center text-white">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($demande['client_nom'] . ' ' . $demande['client_prenom']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($demande['type_credit_nom']); ?></td>
                                            <td><strong><?php echo number_format($demande['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                            <td><?php echo $demande['duree_mois']; ?> mois</td>
                                            <td>
                                                <span class="credit-score score-<?php 
                                                    echo $demande['score_credit'] >= 750 ? 'excellent' : 
                                                        ($demande['score_credit'] >= 650 ? 'good' : 
                                                        ($demande['score_credit'] >= 550 ? 'fair' : 
                                                        ($demande['score_credit'] >= 450 ? 'poor' : 'bad'))); 
                                                ?>">
                                                    <?php echo $demande['score_credit']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $demande['statut'] == 'Approuvé' ? 'success' : 
                                                        ($demande['statut'] == 'Rejeté' ? 'danger' : 
                                                        ($demande['statut'] == 'En étude' ? 'info' : 'warning')); 
                                                ?>">
                                                    <?php echo $demande['statut']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($demande['date_demande'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-demande-credit" 
                                                        data-id="<?php echo $demande['id_demande']; ?>"
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (in_array($demande['statut'], ['En attente', 'En étude'])): ?>
                                                <button class="btn btn-sm btn-outline-primary edit-demande-credit" 
                                                        data-id="<?php echo $demande['id_demande']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Crédits Actifs Tab -->
                    <div class="tab-pane fade" id="credits-actifs">
                        <h2 class="mb-4"><i class="fas fa-file-invoice-dollar me-2"></i>Crédits Actifs</h2>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-check-circle me-2"></i> Dossiers de Crédit en Cours
                            </div>
                            <div class="card-body">
                                <?php if (empty($creditsActifs)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Aucun crédit actif pour le moment.
                                    </div>
                                <?php else: ?>
                                <table id="creditsActifsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID Dossier</th>
                                            <th>Client</th>
                                            <th>Type Crédit</th>
                                            <th>Montant</th>
                                            <th>Durée</th>
                                            <th>Taux</th>
                                            <th>Progression</th>
                                            <th>Date Approbation</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($creditsActifs as $credit): ?>
                                        <tr>
                                            <td><strong>#<?php echo $credit['id_dossier']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($credit['client_nom'] . ' ' . $credit['client_prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($credit['type_credit_nom']); ?></td>
                                            <td><strong><?php echo number_format($credit['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                            <td><?php echo $credit['duree_mois']; ?> mois</td>
                                            <td><?php echo number_format($credit['taux_interet'], 2); ?>%</td>
                                            <td>
                                                <?php 
                                                $progression = ($credit['total_echeances'] > 0) ? 
                                                    round(($credit['echeances_payees'] / $credit['total_echeances']) * 100) : 0;
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $progression; ?>%" 
                                                         aria-valuenow="<?php echo $progression; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $progression; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $credit['echeances_payees']; ?>/<?php echo $credit['total_echeances']; ?> échéances
                                                </small>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($credit['date_approbation'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-credit-details" 
                                                        data-id="<?php echo $credit['id_dossier']; ?>"
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success view-echeancier" 
                                                        data-id="<?php echo $credit['id_dossier']; ?>"
                                                        title="Échéancier">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Historique Tab -->
                    <div class="tab-pane fade" id="historique">
                        <h2 class="mb-4"><i class="fas fa-history me-2"></i>Historique des Actions</h2>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Activités Récentes (30 derniers jours)
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <select class="form-select" id="filterType">
                                            <option value="">Tous les types</option>
                                            <option value="client">Clients</option>
                                            <option value="compte">Comptes</option>
                                            <option value="credit">Crédits</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="date" class="form-control" id="filterDateDebut" placeholder="Date début">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="date" class="form-control" id="filterDateFin" placeholder="Date fin">
                                    </div>
                                </div>
                                
                                <div id="historiqueContent" style="max-height: 600px; overflow-y: auto;">
                                    <!-- Contenu chargé dynamiquement -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Client -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nouveau Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addClientForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <!-- Photo de profil -->
                        <div class="text-center mb-4">
                            <div class="photo-upload-preview" id="photoPreview">
                                <i class="fas fa-camera fa-3x text-muted"></i>
                            </div>
                            <div class="file-upload-wrapper mt-3">
                                <input type="file" id="photoInput" name="photo_profil" accept="image/*">
                                <label for="photoInput" class="file-upload-label">
                                    <i class="fas fa-upload me-2"></i>Choisir une photo
                                </label>
                            </div>
                            <small class="text-muted">Format: JPG, PNG (Max 2MB)</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" class="form-control" name="nom" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prénom *</label>
                                <input type="text" class="form-control" name="prenom" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control" name="telephone" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Revenu Mensuel (BIF)</label>
                                <input type="number" class="form-control" name="revenu_mensuel" min="0" step="1000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Score Crédit Initial</label>
                                <input type="number" class="form-control" name="score_credit" min="0" max="999" value="600">
                                <small class="text-muted">Entre 0 et 999 (Défaut: 600)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ouvrir Compte -->
    <div class="modal fade" id="openCompteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-wallet me-2"></i>Ouvrir un Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="openCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Client *</label>
                            <select class="form-select" name="id_client" id="selectClient" required>
                                <option value="">-- Sélectionner un client --</option>
                                <?php foreach ($clients as $client): ?>
                                    <?php if ($client['actif']): ?>
                                    <option value="<?php echo $client['id_client']; ?>">
                                        <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type de Compte *</label>
                            <select class="form-select" name="id_type_compte" id="selectTypeCompte" required>
                                <option value="">-- Sélectionner un type --</option>
                                <?php foreach ($typesCompte as $type): ?>
                                    <option value="<?php echo $type['id_type_compte']; ?>" 
                                            data-frais="<?php echo $type['frais_gestion_mensuel']; ?>"
                                            data-solde-min="<?php echo $type['solde_minimum']; ?>"
                                            data-taux="<?php echo $type['taux_interet']; ?>">
                                        <?php echo htmlspecialchars($type['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="typeCompteInfo" class="info-section" style="display: none;">
                            <h6>Informations sur le type de compte</h6>
                            <p class="mb-1"><strong>Frais mensuels:</strong> <span id="infoFrais"></span> BIF</p>
                            <p class="mb-1"><strong>Solde minimum:</strong> <span id="infoSoldeMin"></span> BIF</p>
                            <p class="mb-0"><strong>Taux d'intérêt:</strong> <span id="infoTaux"></span>%</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dépôt Initial (BIF) *</label>
                            <input type="number" class="form-control" name="depot_initial" id="depotInitial" min="0" step="1000" required>
                            <small class="text-muted">Le dépôt initial doit être supérieur ou égal au solde minimum</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Ouvrir le Compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nouvelle Demande de Crédit -->
    <div class="modal fade" id="addDemandeCreditModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-hand-holding-usd me-2"></i>Nouvelle Demande de Crédit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addDemandeCreditForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Client *</label>
                            <select class="form-select" name="id_client" id="selectClientCredit" required>
                                <option value="">-- Sélectionner un client --</option>
                                <?php foreach ($clients as $client): ?>
                                    <?php if ($client['actif']): ?>
                                    <option value="<?php echo $client['id_client']; ?>"
                                            data-score="<?php echo $client['score_credit']; ?>"
                                            data-revenu="<?php echo $client['revenu_mensuel']; ?>">
                                        <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?> 
                                        (Score: <?php echo $client['score_credit']; ?>)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="clientCreditInfo" class="info-section" style="display: none;">
                            <h6>Informations du client</h6>
                            <p class="mb-1"><strong>Score crédit:</strong> <span id="infoCreditScore"></span></p>
                            <p class="mb-0"><strong>Revenu mensuel:</strong> <span id="infoCreditRevenu"></span> BIF</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type de Crédit *</label>
                            <select class="form-select" name="id_type_credit" id="selectTypeCredit" required>
                                <option value="">-- Sélectionner un type --</option>
                                <?php foreach ($typesCredit as $type): ?>
                                    <option value="<?php echo $type['id_type_credit']; ?>"
                                            data-taux="<?php echo $type['taux_interet']; ?>"
                                            data-duree-max="<?php echo $type['duree_max_mois']; ?>"
                                            data-montant-min="<?php echo $type['montant_min']; ?>"
                                            data-montant-max="<?php echo $type['montant_max']; ?>">
                                        <?php echo htmlspecialchars($type['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="typeCreditInfo" class="info-section" style="display: none;">
                            <h6>Conditions du crédit</h6>
                            <p class="mb-1"><strong>Taux d'intérêt:</strong> <span id="infoCreditTaux"></span>%</p>
                            <p class="mb-1"><strong>Durée max:</strong> <span id="infoCreditDureeMax"></span> mois</p>
                            <p class="mb-1"><strong>Montant min:</strong> <span id="infoCreditMontantMin"></span> BIF</p>
                            <p class="mb-0"><strong>Montant max:</strong> <span id="infoCreditMontantMax"></span> BIF</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Montant Demandé (BIF) *</label>
                                <input type="number" class="form-control" name="montant" id="montantCredit" min="0" step="10000" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Durée (mois) *</label>
                                <input type="number" class="form-control" name="duree_mois" id="dureeCredit" min="1" required>
                            </div>
                        </div>
                        
                        <div id="calculCredit" class="info-section" style="display: none; background-color: #e8f5e9;">
                            <h6 class="text-success">Simulation du crédit</h6>
                            <p class="mb-1"><strong>Mensualité estimée:</strong> <span id="mensualiteEstimee"></span> BIF</p>
                            <p class="mb-0"><strong>Montant total à rembourser:</strong> <span id="montantTotalEstime"></span> BIF</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-paper-plane me-2"></i>Soumettre la Demande
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Détails Client -->
    <div class="modal fade" id="clientDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Détails du Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="clientDetailsContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Suspendre Compte -->
    <div class="modal fade" id="suspendCompteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-pause me-2"></i>Suspendre le Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="suspendCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id_compte" id="suspendCompteId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Vous êtes sur le point de suspendre le compte <strong id="suspendCompteNum"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motif de la suspension *</label>
                            <textarea class="form-control" name="motif_suspension" rows="3" required placeholder="Expliquez la raison de la suspension..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-pause me-2"></i>Suspendre
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Fermer Compte -->
    <div class="modal fade" id="closeCompteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Fermer le Compte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="closeCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id_compte" id="closeCompteId">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Attention:</strong> Cette action est définitive. Le compte <strong id="closeCompteNum"></strong> sera fermé et ne pourra plus être utilisé.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motif de la fermeture *</label>
                            <textarea class="form-control" name="motif_fermeture" rows="3" required placeholder="Expliquez la raison de la fermeture..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmClose" required>
                            <label class="form-check-label" for="confirmClose">
                                Je confirme vouloir fermer définitivement ce compte
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle me-2"></i>Fermer Définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // ========================================
        // FONCTIONS DE LOADING
        // ========================================
        function showGlobalLoading(message = 'Traitement en cours...', subtext = 'Veuillez patienter') {
            $('#loadingMessage').text(message);
            $('#loadingSubtext').text(subtext);
            $('#globalLoading').fadeIn(300);
        }
        
        function hideGlobalLoading() {
            $('#globalLoading').fadeOut(300);
        }
        
        function showButtonLoading(button, text = 'Traitement...') {
            const originalText = button.html();
            button.data('original-text', originalText);
            button.prop('disabled', true);
            button.html(`<span class="spinner-border spinner-border-sm me-2" role="status"></span>${text}`);
        }
        
        function hideButtonLoading(button) {
            const originalText = button.data('original-text');
            button.prop('disabled', false);
            button.html(originalText);
        }
        
        // ========================================
        // INITIALISATION DES DATATABLES
        // ========================================
        const dataTableConfig = {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/fr-FR.json'
            },
            responsive: true,
            pageLength: 25
        };
        
        if ($('#clientsTable').length) {
            $('#clientsTable').DataTable({ ...dataTableConfig, order: [[1, 'asc']] });
        }
        
        if ($('#comptesTable').length) {
            $('#comptesTable').DataTable({ ...dataTableConfig, order: [[6, 'desc']] });
        }
        
        if ($('#demandesCreditTable').length) {
            $('#demandesCreditTable').DataTable({ ...dataTableConfig, order: [[8, 'desc']] });
        }
        
        if ($('#creditsActifsTable').length) {
            $('#creditsActifsTable').DataTable({ ...dataTableConfig, order: [[7, 'desc']] });
        }
        
        // ========================================
        // UPLOAD PHOTO
        // ========================================
        $('#photoInput').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Vérification de la taille (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('La photo ne doit pas dépasser 2MB');
                    $(this).val('');
                    return;
                }
                
                // Vérification du type
                if (!file.type.match('image.*')) {
                    alert('Veuillez sélectionner une image valide');
                    $(this).val('');
                    return;
                }
                
                // Prévisualisation
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#photoPreview').html(`<img src="${e.target.result}" alt="Preview">`);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // ========================================
        // AJOUTER CLIENT
        // ========================================
        $('#addClientForm').submit(function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Enregistrement...');
            showGlobalLoading('Création du client', 'Veuillez patienter...');
            
            const formData = new FormData(this);
            formData.append('action', 'add_client');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Client créé avec succès !');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la création du client');
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                    hideGlobalLoading();
                }
            });
        });
        
        // ========================================
        // OUVRIR COMPTE
        // ========================================
        
        // Afficher les infos du type de compte
        $('#selectTypeCompte').change(function() {
            const selected = $(this).find('option:selected');
            if (selected.val()) {
                const frais = selected.data('frais');
                const soldeMin = selected.data('solde-min');
                const taux = selected.data('taux');
                
                $('#infoFrais').text(Number(frais).toLocaleString('fr-FR'));
                $('#infoSoldeMin').text(Number(soldeMin).toLocaleString('fr-FR'));
                $('#infoTaux').text(taux);
                $('#typeCompteInfo').slideDown();
                
                // Mettre à jour le minimum du dépôt initial
                $('#depotInitial').attr('min', soldeMin);
            } else {
                $('#typeCompteInfo').slideUp();
            }
        });
        
        // Bouton pour ouvrir compte depuis la liste des clients
        $(document).on('click', '.open-compte-for-client', function() {
            const clientId = $(this).data('id');
            const clientNom = $(this).data('nom');
            
            $('#selectClient').val(clientId);
            $('#openCompteModal').modal('show');
        });
        
        $('#openCompteForm').submit(function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Ouverture...');
            showGlobalLoading('Ouverture du compte', 'Création en cours...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=open_compte',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Compte ouvert avec succès ! Numéro: ' + response.num_compte);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de l\'ouverture du compte');
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                    hideGlobalLoading();
                }
            });
        });
        
        // ========================================
        // DEMANDE DE CRÉDIT
        // ========================================
        
        // Afficher les infos du client
        $('#selectClientCredit').change(function() {
            const selected = $(this).find('option:selected');
            if (selected.val()) {
                const score = selected.data('score');
                const revenu = selected.data('revenu');
                
                $('#infoCreditScore').html(`<span class="credit-score score-${
                    score >= 750 ? 'excellent' : 
                    (score >= 650 ? 'good' : 
                    (score >= 550 ? 'fair' : 
                    (score >= 450 ? 'poor' : 'bad')))
                }">${score}</span>`);
                $('#infoCreditRevenu').text(Number(revenu).toLocaleString('fr-FR'));
                $('#clientCreditInfo').slideDown();
            } else {
                $('#clientCreditInfo').slideUp();
            }
        });
        
        // Afficher les infos du type de crédit
        $('#selectTypeCredit').change(function() {
            const selected = $(this).find('option:selected');
            if (selected.val()) {
                const taux = selected.data('taux');
                const dureeMax = selected.data('duree-max');
                const montantMin = selected.data('montant-min');
                const montantMax = selected.data('montant-max');
                
                $('#infoCreditTaux').text(taux);
                $('#infoCreditDureeMax').text(dureeMax);
                $('#infoCreditMontantMin').text(Number(montantMin).toLocaleString('fr-FR'));
                $('#infoCreditMontantMax').text(Number(montantMax).toLocaleString('fr-FR'));
                $('#typeCreditInfo').slideDown();
                
                // Mettre à jour les limites
                $('#montantCredit').attr('min', montantMin).attr('max', montantMax);
                $('#dureeCredit').attr('max', dureeMax);
            } else {
                $('#typeCreditInfo').slideUp();
            }
            calculateCredit();
        });
        
        // Calculer la simulation du crédit
        $('#montantCredit, #dureeCredit').on('input', calculateCredit);
        
        function calculateCredit() {
            const montant = parseFloat($('#montantCredit').val()) || 0;
            const duree = parseInt($('#dureeCredit').val()) || 0;
            const tauxAnnuel = parseFloat($('#selectTypeCredit').find('option:selected').data('taux')) || 0;
            
            if (montant > 0 && duree > 0 && tauxAnnuel > 0) {
                const tauxMensuel = tauxAnnuel / 100 / 12;
                const mensualite = montant * (tauxMensuel * Math.pow(1 + tauxMensuel, duree)) / (Math.pow(1 + tauxMensuel, duree) - 1);
                const montantTotal = mensualite * duree;
                
                $('#mensualiteEstimee').text(Math.round(mensualite).toLocaleString('fr-FR'));
                $('#montantTotalEstime').text(Math.round(montantTotal).toLocaleString('fr-FR'));
                $('#calculCredit').slideDown();
            } else {
                $('#calculCredit').slideUp();
            }
        }
        
        $('#addDemandeCreditForm').submit(function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Soumission...');
            showGlobalLoading('Soumission de la demande', 'Traitement en cours...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=add_demande_credit',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Demande de crédit soumise avec succès !');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la soumission de la demande');
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                    hideGlobalLoading();
                }
            });
        });
        
        // ========================================
        // VOIR DÉTAILS CLIENT
        // ========================================
        $(document).on('click', '.view-client', function() {
            const clientId = $(this).data('id');
            const button = $(this);
            showButtonLoading(button, 'Chargement...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: {
                    action: 'get_client_details',
                    id: clientId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayClientDetails(response.data);
                        $('#clientDetailsModal').modal('show');
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des détails');
                },
                complete: function() {
                    hideButtonLoading(button);
                }
            });
        });
        
        function displayClientDetails(data) {
            const client = data.client;
            const comptes = data.comptes || [];
            const credits = data.credits || [];
            
            let html = `
                <div class="row">
                    <div class="col-md-3 text-center">
                        ${client.photo_profil ? 
                            `<img src="${client.photo_profil}" class="client-photo-large mb-3" alt="Photo">` :
                            `<div class="client-photo-large bg-secondary d-flex align-items-center justify-content-center text-white mb-3">
                                <i class="fas fa-user fa-4x"></i>
                            </div>`
                        }
                        <h5>${client.nom} ${client.prenom}</h5>
                        <p class="text-muted">ID: ${client.id_client}</p>
                    </div>
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Informations Personnelles</h6>
                                <p><strong>Email:</strong> ${client.email || 'N/A'}</p>
                                <p><strong>Téléphone:</strong> ${client.telephone || 'N/A'}</p>
                                <p><strong>Adresse:</strong> ${client.adresse || 'N/A'}</p>
                                <p><strong>Date création:</strong> ${new Date(client.created_at).toLocaleDateString('fr-FR')}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Informations Financières</h6>
                                <p><strong>Score crédit:</strong> <span class="credit-score score-${
                                    client.score_credit >= 750 ? 'excellent' : 
                                    (client.score_credit >= 650 ? 'good' : 
                                    (client.score_credit >= 550 ? 'fair' : 
                                    (client.score_credit >= 450 ? 'poor' : 'bad')))
                                }">${client.score_credit}</span></p>
                                <p><strong>Revenu mensuel:</strong> ${Number(client.revenu_mensuel).toLocaleString('fr-FR')} BIF</p>
                                <p><strong>Statut:</strong> <span class="badge bg-${client.actif ? 'success' : 'secondary'}">${client.actif ? 'Actif' : 'Inactif'}</span></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="text-info mb-3">Comptes (${comptes.length})</h6>
                        ${comptes.length > 0 ? `
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>N° Compte</th>
                                            <th>Type</th>
                                            <th>Solde</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${comptes.map(c => `
                                            <tr>
                                                <td><strong>${c.num_compte}</strong></td>
                                                <td><span class="badge bg-primary">${c.type_compte}</span></td>
                                                <td>${Number(c.solde).toLocaleString('fr-FR')} BIF</td>
                                                <td><span class="badge bg-${c.statut == 'Actif' ? 'success' : 'secondary'}">${c.statut}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<p class="text-muted">Aucun compte</p>'}
                        
                        <hr>
                        
                        <h6 class="text-warning mb-3">Crédits (${credits.length})</h6>
                        ${credits.length > 0 ? `
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Montant</th>
                                            <th>Durée</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${credits.map(cr => `
                                            <tr>
                                                <td>${cr.type_credit}</td>
                                                <td>${Number(cr.montant).toLocaleString('fr-FR')} BIF</td>
                                                <td>${cr.duree_mois} mois</td>
                                                <td><span class="badge bg-info">${cr.statut}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<p class="text-muted">Aucun crédit</p>'}
                    </div>
                </div>
            `;
            
            $('#clientDetailsContent').html(html);
        }
        
        // ========================================
        // SUSPENDRE / FERMER COMPTE
        // ========================================
        $(document).on('click', '.suspend-compte', function() {
            const compteId = $(this).data('id');
            const compteNum = $(this).data('num');
            
            $('#suspendCompteId').val(compteId);
            $('#suspendCompteNum').text(compteNum);
            $('#suspendCompteModal').modal('show');
        });
        
        $('#suspendCompteForm').submit(function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Suspension...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=suspend_compte',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Compte suspendu avec succès');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la suspension');
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                }
            });
        });
        
        $(document).on('click', '.close-compte', function() {
            const compteId = $(this).data('id');
            const compteNum = $(this).data('num');
            
            $('#closeCompteId').val(compteId);
            $('#closeCompteNum').text(compteNum);
            $('#closeCompteModal').modal('show');
        });
        
        $('#closeCompteForm').submit(function(e) {
            e.preventDefault();
            
            const submitBtn = $(this).find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Fermeture...');
            showGlobalLoading('Fermeture du compte', 'Opération définitive...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: $(this).serialize() + '&action=close_compte',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Compte fermé avec succès');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la fermeture');
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                    hideGlobalLoading();
                }
            });
        });
        
        $(document).on('click', '.activate-compte', function() {
            const compteId = $(this).data('id');
            const compteNum = $(this).data('num');
            
            if (confirm('Voulez-vous réactiver le compte ' + compteNum + ' ?')) {
                const button = $(this);
                showButtonLoading(button, 'Activation...');
                
                $.ajax({
                    url: 'ajax_conseiller_actions.php',
                    method: 'POST',
                    data: {
                        action: 'activate_compte',
                        id_compte: compteId,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Compte réactivé avec succès');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('danger', 'Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erreur:', xhr.responseText);
                        showAlert('danger', 'Erreur lors de la réactivation');
                    },
                    complete: function() {
                        hideButtonLoading(button);
                    }
                });
            }
        });
        
        // ========================================
        // FONCTION D'ALERTE
        // ========================================
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     style="z-index: 9999; min-width: 400px;" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle')} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            setTimeout(() => {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // ========================================
        // RÉINITIALISATION DES MODALS
        // ========================================
        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('form').trigger('reset');
            $(this).find('.info-section').hide();
            $('#photoPreview').html('<i class="fas fa-camera fa-3x text-muted"></i>');
        });
        
        // ========================================
        // CHARGER L'HISTORIQUE
        // ========================================
        function loadHistorique() {
            const type = $('#filterType').val();
            const dateDebut = $('#filterDateDebut').val();
            const dateFin = $('#filterDateFin').val();
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: {
                    action: 'get_historique',
                    type: type,
                    date_debut: dateDebut,
                    date_fin: dateFin,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayHistorique(response.data);
                    } else {
                        $('#historiqueContent').html(`
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${response.message}
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    $('#historiqueContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            Erreur lors du chargement de l'historique
                        </div>
                    `);
                }
            });
        }
        
        function displayHistorique(data) {
            if (data.length === 0) {
                $('#historiqueContent').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucune activité trouvée pour ces critères
                    </div>
                `);
                return;
            }
            
            let html = '';
            data.forEach(item => {
                const iconMap = {
                    'client': 'user-plus',
                    'compte': 'wallet',
                    'credit': 'hand-holding-usd'
                };
                
                const colorMap = {
                    'client': 'primary',
                    'compte': 'success',
                    'credit': 'warning'
                };
                
                html += `
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-${iconMap[item.type] || 'circle'} text-${colorMap[item.type] || 'secondary'} me-2"></i>
                                <strong>${item.description}</strong>
                                ${item.details ? `<br><small class="text-muted ms-4">${item.details}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${new Date(item.date).toLocaleDateString('fr-FR')}</small><br>
                                <small class="text-muted">${new Date(item.date).toLocaleTimeString('fr-FR')}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#historiqueContent').html(html);
        }
        
        // Charger l'historique au changement de filtre
        $('#filterType, #filterDateDebut, #filterDateFin').on('change', function() {
            loadHistorique();
        });
        
        // Charger l'historique initial quand l'onglet est activé
        $('a[href="#historique"]').on('shown.bs.tab', function() {
            if ($('#historiqueContent').html() === '') {
                loadHistorique();
            }
        });
        
        // ========================================
        // VOIR DÉTAILS DEMANDE DE CRÉDIT
        // ========================================
        $(document).on('click', '.view-demande-credit', function() {
            const demandeId = $(this).data('id');
            const button = $(this);
            showButtonLoading(button, 'Chargement...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: {
                    action: 'get_demande_credit_details',
                    id: demandeId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Fonctionnalité en cours de développement');
                        // TODO: Afficher les détails dans un modal
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des détails');
                },
                complete: function() {
                    hideButtonLoading(button);
                }
            });
        });
        
        // ========================================
        // VOIR ÉCHÉANCIER
        // ========================================
        $(document).on('click', '.view-echeancier', function() {
            const dossierId = $(this).data('id');
            const button = $(this);
            showButtonLoading(button, 'Chargement...');
            
            $.ajax({
                url: 'ajax_conseiller_actions.php',
                method: 'POST',
                data: {
                    action: 'get_echeancier',
                    id_dossier: dossierId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Fonctionnalité en cours de développement');
                        // TODO: Afficher l'échéancier dans un modal
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement de l\'échéancier');
                },
                complete: function() {
                    hideButtonLoading(button);
                }
            });
        });
        
        // ========================================
        // MODIFIER CLIENT
        // ========================================
        $(document).on('click', '.edit-client', function() {
            const clientId = $(this).data('id');
            showAlert('info', 'Fonctionnalité de modification en cours de développement');
            // TODO: Charger les données du client et afficher le formulaire de modification
        });
        
        // ========================================
        // VOIR DÉTAILS COMPTE
        // ========================================
        $(document).on('click', '.view-compte', function() {
            const compteId = $(this).data('id');
            showAlert('info', 'Fonctionnalité d\'affichage des détails en cours de développement');
            // TODO: Afficher les détails du compte (transactions, historique, etc.)
        });
    });
    </script>
</body>
</html>