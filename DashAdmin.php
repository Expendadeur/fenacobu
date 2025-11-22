<?php
// DashAdmin.php - Dashboard Administrateur adapté à la nouvelle structure
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: login.php');
    exit();
}

// Application des en-têtes de sécurité avec CSP corrigée
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; img-src 'self' data: https:; connect-src 'self' https:");

// Récupération de la connexion
$db = DatabaseConfig::getConnection();

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// ========================================
// RÉCUPÉRATION DES DONNÉES
// ========================================

// Statistiques globales
try {
    $stats = [
        'clients' => $db->query("SELECT COUNT(*) FROM clients WHERE actif = 1")->fetchColumn(),
        'agents' => $db->query("SELECT COUNT(*) FROM agents WHERE is_active = 1")->fetchColumn(),
        'comptes' => $db->query("SELECT COUNT(*) FROM comptes WHERE statut = 'Actif'")->fetchColumn(),
        'transactions_jour' => $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(date_heure) = CURDATE()")->fetchColumn(),
        'demandes_attente' => $db->query("SELECT COUNT(*) FROM demandes_credit WHERE statut IN ('En attente', 'En étude')")->fetchColumn(),
        'total_liquidites' => $db->query("SELECT SUM(solde) FROM comptes WHERE statut = 'Actif'")->fetchColumn() ?? 0
    ];
} catch(PDOException $e) {
    $stats = ['clients' => 0, 'agents' => 0, 'comptes' => 0, 'transactions_jour' => 0, 'demandes_attente' => 0, 'total_liquidites' => 0];
    error_log("Erreur récupération stats: " . $e->getMessage());
}

// Récupération des agents
try {
    $agents = $db->query("
        SELECT a.*, ag.nom as agence_nom 
        FROM agents a 
        LEFT JOIN agences ag ON a.id_agence = ag.id_agence 
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $agents = [];
    error_log("Erreur agents: " . $e->getMessage());
}

// Récupération des clients
try {
    $clients = $db->query("SELECT * FROM clients ORDER BY created_at DESC")->fetchAll();
} catch(PDOException $e) {
    $clients = [];
}

// Récupération des comptes avec types
try {
    $comptes = $db->query("
        SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom, 
               tc.libelle as type_compte_libelle, tc.code as type_compte_code
        FROM comptes c 
        LEFT JOIN clients cl ON c.id_client = cl.id_client 
        LEFT JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
        ORDER BY c.date_creation DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $comptes = [];
    error_log("Erreur comptes: " . $e->getMessage());
}

// Récupération des types de comptes (pour configuration)
try {
    $typesCompte = $db->query("SELECT * FROM types_compte WHERE actif = 1 ORDER BY code")->fetchAll();
} catch(PDOException $e) {
    $typesCompte = [];
}

// Récupération des types de transactions
try {
    $typesTransaction = $db->query("SELECT * FROM types_transaction WHERE actif = 1 ORDER BY categorie, code")->fetchAll();
} catch(PDOException $e) {
    $typesTransaction = [];
}

// Récupération des demandes de crédit
try {
    $demandesCredit = $db->query("
        SELECT dc.*, cl.nom as client_nom, cl.prenom as client_prenom, 
               tc.nom as type_credit_nom, tc.taux_interet
        FROM demandes_credit dc 
        LEFT JOIN clients cl ON dc.id_client = cl.id_client 
        LEFT JOIN types_credit tc ON dc.id_type_credit = tc.id_type_credit 
        WHERE dc.statut IN ('En attente', 'En étude')
        ORDER BY dc.date_demande DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $demandesCredit = [];
}

// Récupération des transactions récentes
try {
    $transactions = $db->query("
        SELECT t.*, tt.libelle as type_libelle, tt.categorie, tt.sens,
               a.first_name as agent_nom, a.last_name as agent_prenom 
        FROM transactions t 
        LEFT JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        LEFT JOIN agents a ON t.id_agent = a.id_agent 
        ORDER BY t.date_heure DESC 
        LIMIT 100
    ")->fetchAll();
} catch(PDOException $e) {
    $transactions = [];
}

// Récupération des agences
try {
    $agences = $db->query("SELECT * FROM agences ORDER BY nom")->fetchAll();
} catch(PDOException $e) {
    $agences = [];
}

// Récupération des guichets
try {
    $guichets = $db->query("
        SELECT g.*, a.nom as agence_nom 
        FROM guichets g 
        LEFT JOIN agences a ON g.id_agence = a.id_agence 
        ORDER BY a.nom, g.numero_guichet
    ")->fetchAll();
} catch(PDOException $e) {
    $guichets = [];
}

// Récupération des logs d'audit
try {
    $auditLogs = $db->query("
        SELECT lo.*, a.username as agent_username 
        FROM log_operations lo
        LEFT JOIN agents a ON lo.id_agent = a.id_agent
        ORDER BY lo.timestamp DESC 
        LIMIT 100
    ")->fetchAll();
} catch(PDOException $e) {
    $auditLogs = [];
}

// Transactions par jour (pour le graphique)
try {
    $transactionsChart = $db->query("
        SELECT DATE(date_heure) as date, COUNT(*) as nombre
        FROM transactions
        WHERE date_heure >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date_heure)
        ORDER BY date
    ")->fetchAll();
} catch(PDOException $e) {
    $transactionsChart = [];
}

// Configuration DataTables en français
$datatables_fr = [
    "processing" => "Traitement en cours...",
    "search" => "Rechercher&nbsp;:",
    "lengthMenu" => "Afficher _MENU_ &eacute;l&eacute;ments",
    "info" => "Affichage de l'&eacute;l&eacute;ment _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
    "infoEmpty" => "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
    "infoFiltered" => "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
    "infoPostFix" => "",
    "loadingRecords" => "Chargement en cours...",
    "zeroRecords" => "Aucun &eacute;l&eacute;ment &agrave; afficher",
    "emptyTable" => "Aucune donnée disponible dans le tableau",
    "paginate" => [
        "first" => "Premier",
        "previous" => "Précédent",
        "next" => "Suivant",
        "last" => "Dernier"
    ],
    "aria" => [
        "sortAscending" => ": activer pour trier la colonne par ordre croissant",
        "sortDescending" => ": activer pour trier la colonne par ordre décroissant"
    ]
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - FENACOBU</title>
    
    <!-- CSS Bootstrap 5.3.0 corrigé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    
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
        
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .password-strength {
            margin-top: 8px;
        }
        
        .strength-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .strength-progress {
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
        
        .password-requirements {
            list-style: none;
            padding: 0;
            font-size: 0.85rem;
        }
        
        .password-requirements li {
            margin: 4px 0;
            color: #dc3545;
            transition: color 0.3s;
        }
        
        .password-requirements li.valid {
            color: #28a745;
        }
        
        .password-requirements li::before {
            content: "✗ ";
            font-weight: bold;
            margin-right: 4px;
        }
        
        .password-requirements li.valid::before {
            content: "✓ ";
        }
        
        .modal-header {
            background: linear-gradient(90deg, var(--primary-red) 0%, var(--primary-green) 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .input-group .form-select {
            max-width: 140px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Styles améliorés pour les loaders */
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
        
        .loading-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        
        .loading-subtext {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
        }
        
        .table-loading {
            position: relative;
        }
        
        .table-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
        
        .table-loading.loading::after {
            display: flex;
        }
        
        .btn-loading {
            position: relative;
        }
        
        .btn-loading .spinner-border {
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }
        
        .action-loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <i class="fas fa-landmark me-2"></i>FENACOBU - Administration
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
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
                    <a class="nav-link" href="#agents" data-bs-toggle="tab">
                        <i class="fas fa-user-tie"></i> Agents
                    </a>
                    <a class="nav-link" href="#clients" data-bs-toggle="tab">
                        <i class="fas fa-users"></i> Clients
                    </a>
                    <a class="nav-link" href="#comptes" data-bs-toggle="tab">
                        <i class="fas fa-wallet"></i> Comptes
                    </a>
                    <a class="nav-link" href="#types-compte" data-bs-toggle="tab">
                        <i class="fas fa-cog"></i> Types de compte
                    </a>
                    <a class="nav-link" href="#transactions" data-bs-toggle="tab">
                        <i class="fas fa-exchange-alt"></i> Transactions
                    </a>
                    <a class="nav-link" href="#types-transaction" data-bs-toggle="tab">
                        <i class="fas fa-list-ul"></i> Types de transaction
                    </a>
                    <a class="nav-link" href="#credits" data-bs-toggle="tab">
                        <i class="fas fa-hand-holding-usd"></i> Crédits
                    </a>
                    <a class="nav-link" href="#agences" data-bs-toggle="tab">
                        <i class="fas fa-building"></i> Agences
                    </a>
                    <a class="nav-link" href="#guichets" data-bs-toggle="tab">
                        <i class="fas fa-desktop"></i> Guichets
                    </a>
                    <a class="nav-link" href="#audit" data-bs-toggle="tab">
                        <i class="fas fa-clipboard-list"></i> Audit
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
                                    <div class="number"><?php echo number_format($stats['clients']); ?></div>
                                    <div class="label">Clients Actifs</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-user-tie text-success"></i>
                                    <div class="number"><?php echo number_format($stats['agents']); ?></div>
                                    <div class="label">Agents</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-wallet text-info"></i>
                                    <div class="number"><?php echo number_format($stats['comptes']); ?></div>
                                    <div class="label">Comptes Actifs</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-exchange-alt text-warning"></i>
                                    <div class="number"><?php echo number_format($stats['transactions_jour']); ?></div>
                                    <div class="label">Transactions Aujourd'hui</div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-hand-holding-usd text-danger"></i>
                                    <div class="number"><?php echo number_format($stats['demandes_attente']); ?></div>
                                    <div class="label">Demandes en attente</div>
                                </div>
                            </div>
                            <div class="col-lg-8 col-md-6 mb-3">
                                <div class="card stat-card">
                                    <i class="fas fa-money-bill-wave text-success"></i>
                                    <div class="number"><?php echo number_format($stats['total_liquidites'], 0, ',', ' '); ?> BIF</div>
                                    <div class="label">Total Liquidités</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Graphiques et Alertes -->
                        <div class="row">
                            <div class="col-lg-8 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-chart-bar me-2"></i> Transactions des 7 derniers jours
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="transactionsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-bell me-2"></i> Alertes
                                    </div>
                                    <div class="card-body">
                                        <?php if ($stats['demandes_attente'] > 0): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong><?php echo $stats['demandes_attente']; ?> demande(s) de crédit</strong> en attente
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Système opérationnel
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Base de données connectée
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agents Tab -->
                    <div class="tab-pane fade" id="agents">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-user-tie me-2"></i>Gestion des Agents</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAgentModal">
                                <i class="fas fa-plus me-2"></i>Nouvel Agent
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Agents
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="agentsLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des agents...</p>
                                </div>
                                <table id="agentsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Agence</th>
                                            <th>Téléphone</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $agent): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($agent['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($agent['first_name']); ?></td>
                                            <td><?php echo htmlspecialchars($agent['username']); ?></td>
                                            <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $agent['role'] == 'Administrateur' ? 'danger' : 
                                                        ($agent['role'] == 'Caissier' ? 'primary' : 'info'); 
                                                ?>">
                                                    <?php echo $agent['role']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($agent['agence_nom'] ?? 'Non assigné'); ?></td>
                                            <td><?php echo htmlspecialchars($agent['telephone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($agent['is_blocked']): ?>
                                                    <span class="badge bg-danger">Bloqué</span>
                                                <?php else: ?>
                                                    <span class="badge bg-<?php echo $agent['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $agent['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-agent" 
                                                        data-id="<?php echo $agent['id_agent']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning change-agent-password" 
                                                        data-id="<?php echo $agent['id_agent']; ?>"
                                                        title="Changer mot de passe">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if (!$agent['is_blocked']): ?>
                                                    <button class="btn btn-sm btn-outline-danger block-agent" 
                                                            data-id="<?php echo $agent['id_agent']; ?>"
                                                            title="Bloquer l'agent">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-success unblock-agent" 
                                                            data-id="<?php echo $agent['id_agent']; ?>"
                                                            title="Débloquer l'agent">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-secondary toggle-agent" 
                                                        data-id="<?php echo $agent['id_agent']; ?>" 
                                                        data-status="<?php echo $agent['is_active']; ?>"
                                                        title="<?php echo $agent['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                    <i class="fas fa-<?php echo $agent['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                <div class="action-loading" id="clientsLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des clients...</p>
                                </div>
                                <table id="clientsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Score Crédit</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><?php echo $client['id_client']; ?></td>
                                            <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($client['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($client['telephone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $client['score_credit'] >= 750 ? 'success' : 
                                                        ($client['score_credit'] >= 600 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $client['score_credit']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $client['actif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $client['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-client" 
                                                        data-id="<?php echo $client['id_client']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary toggle-client" 
                                                        data-id="<?php echo $client['id_client']; ?>" 
                                                        data-status="<?php echo $client['actif']; ?>"
                                                        title="<?php echo $client['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                    <i class="fas fa-<?php echo $client['actif'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
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
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompteModal">
                                <i class="fas fa-plus me-2"></i>Nouveau Compte
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Comptes
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="comptesLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des comptes...</p>
                                </div>
                                <table id="comptesTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>N° Compte</th>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th>Solde</th>
                                            <th>Solde Disponible</th>
                                            <th>Statut</th>
                                            <th>Date Création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comptes as $compte): ?>
                                        <tr>
                                            <td><?php echo $compte['id_compte']; ?></td>
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
                                            <td><?php echo number_format($compte['solde_disponible'], 0, ',', ' '); ?> BIF</td>
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
                                                <button class="btn btn-sm btn-outline-primary edit-compte" 
                                                        data-id="<?php echo $compte['id_compte']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Types de Compte Tab -->
                    <div class="tab-pane fade" id="types-compte">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-cog me-2"></i>Configuration des Types de Compte</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeCompteModal">
                                <i class="fas fa-plus me-2"></i>Nouveau Type
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Types de Compte Disponibles
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="typesCompteLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des types de compte...</p>
                                </div>
                                <table id="typesCompteTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Code</th>
                                            <th>Libellé</th>
                                            <th>Taux Intérêt</th>
                                            <th>Frais Mensuels</th>
                                            <th>Solde Min.</th>
                                            <th>Découvert</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($typesCompte as $type): ?>
                                        <tr>
                                            <td><?php echo $type['id_type_compte']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($type['code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($type['libelle']); ?></td>
                                            <td><?php echo number_format($type['taux_interet'], 2); ?>%</td>
                                            <td><?php echo number_format($type['frais_gestion_mensuel'], 0, ',', ' '); ?> BIF</td>
                                            <td><?php echo number_format($type['solde_minimum'], 0, ',', ' '); ?> BIF</td>
                                            <td>
                                                <?php if ($type['decouvert_autorise']): ?>
                                                    <span class="badge bg-success">
                                                        <?php echo number_format($type['montant_decouvert_max'], 0, ',', ' '); ?> BIF
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non autorisé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $type['actif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $type['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-type-compte" 
                                                        data-id="<?php echo $type['id_type_compte']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div class="tab-pane fade" id="transactions">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-exchange-alt me-2"></i>Historique des Transactions</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="fas fa-plus me-2"></i>Nouvelle Transaction
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Dernières Transactions
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="transactionsLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des transactions...</p>
                                </div>
                                <table id="transactionsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date/Heure</th>
                                            <th>Compte</th>
                                            <th>Type</th>
                                            <th>Catégorie</th>
                                            <th>Sens</th>
                                            <th>Montant</th>
                                            <th>Frais</th>
                                            <th>Agent</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $trans): ?>
                                        <tr>
                                            <td><?php echo $trans['id_transaction']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($trans['date_heure'])); ?></td>
                                            <td><?php echo htmlspecialchars($trans['num_compte']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['type_libelle']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $trans['categorie'] == 'DEPOT' ? 'success' : 
                                                        ($trans['categorie'] == 'RETRAIT' ? 'danger' : 
                                                        ($trans['categorie'] == 'VIREMENT' ? 'info' : 'warning')); 
                                                ?>">
                                                    <?php echo $trans['categorie']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-arrow-<?php echo $trans['sens'] == 'CREDIT' ? 'up text-success' : 'down text-danger'; ?>"></i>
                                                <?php echo $trans['sens']; ?>
                                            </td>
                                            <td><strong><?php echo number_format($trans['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                            <td><?php echo number_format($trans['frais'], 0, ',', ' '); ?> BIF</td>
                                            <td><?php echo htmlspecialchars($trans['agent_nom'] . ' ' . $trans['agent_prenom']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $trans['statut'] == 'Terminée' ? 'success' : 
                                                        ($trans['statut'] == 'En cours' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $trans['statut']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Types de Transaction Tab -->
                    <div class="tab-pane fade" id="types-transaction">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-list-ul me-2"></i>Configuration des Types de Transaction</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeTransactionModal">
                                <i class="fas fa-plus me-2"></i>Nouveau Type
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Types de Transaction Disponibles
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="typesTransactionLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des types de transaction...</p>
                                </div>
                                <table id="typesTransactionTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Code</th>
                                            <th>Libellé</th>
                                            <th>Catégorie</th>
                                            <th>Sens</th>
                                            <th>Frais Fixe</th>
                                            <th>Frais %</th>
                                            <th>Montant Min.</th>
                                            <th>Montant Max.</th>
                                            <th>Guichet</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($typesTransaction as $type): ?>
                                        <tr>
                                            <td><?php echo $type['id_type_transaction']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($type['code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($type['libelle']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $type['categorie'] == 'DEPOT' ? 'success' : 
                                                        ($type['categorie'] == 'RETRAIT' ? 'danger' : 
                                                        ($type['categorie'] == 'VIREMENT' ? 'info' : 'warning')); 
                                                ?>">
                                                    <?php echo $type['categorie']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-arrow-<?php echo $type['sens'] == 'CREDIT' ? 'up text-success' : 'down text-danger'; ?>"></i>
                                                <?php echo $type['sens']; ?>
                                            </td>
                                            <td><?php echo number_format($type['frais_fixe'], 0, ',', ' '); ?> BIF</td>
                                            <td><?php echo number_format($type['frais_pourcentage'], 2); ?>%</td>
                                            <td><?php echo number_format($type['montant_min'], 0, ',', ' '); ?></td>
                                            <td><?php echo $type['montant_max'] ? number_format($type['montant_max'], 0, ',', ' ') : 'Illimité'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $type['necessite_guichet'] ? 'warning' : 'info'; ?>">
                                                    <?php echo $type['necessite_guichet'] ? 'Requis' : 'Optionnel'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $type['actif'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $type['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-type-transaction" 
                                                        data-id="<?php echo $type['id_type_transaction']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Crédits Tab -->
                    <div class="tab-pane fade" id="credits">
                        <h2 class="mb-4"><i class="fas fa-hand-holding-usd me-2"></i>Demandes de Crédit</h2>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-clock me-2"></i> Demandes en Attente
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="creditsLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des demandes de crédit...</p>
                                </div>
                                <?php if (empty($demandesCredit)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Aucune demande de crédit en attente.
                                    </div>
                                <?php else: ?>
                                <table id="creditsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Client</th>
                                            <th>Type de Crédit</th>
                                            <th>Montant</th>
                                            <th>Durée</th>
                                            <th>Taux</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demandesCredit as $demande): ?>
                                        <tr>
                                            <td><?php echo $demande['id_demande']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($demande['date_demande'])); ?></td>
                                            <td><?php echo htmlspecialchars($demande['client_nom'] . ' ' . $demande['client_prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($demande['type_credit_nom']); ?></td>
                                            <td><strong><?php echo number_format($demande['montant'], 0, ',', ' '); ?> BIF</strong></td>
                                            <td><?php echo $demande['duree_mois']; ?> mois</td>
                                            <td><?php echo number_format($demande['taux_interet'], 2); ?>%</td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $demande['statut'] == 'En attente' ? 'warning' : 'info'; 
                                                ?>">
                                                    <?php echo $demande['statut']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success approve-credit" 
                                                        data-id="<?php echo $demande['id_demande']; ?>"
                                                        title="Approuver">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger reject-credit" 
                                                        data-id="<?php echo $demande['id_demande']; ?>"
                                                        title="Rejeter">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" title="Détails">
                                                    <i class="fas fa-eye"></i>
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

                    <!-- Agences Tab -->
                    <div class="tab-pane fade" id="agences">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-building me-2"></i>Gestion des Agences</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAgenceModal">
                                <i class="fas fa-plus me-2"></i>Nouvelle Agence
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Agences
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="agencesLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des agences...</p>
                                </div>
                                <table id="agencesTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Adresse</th>
                                            <th>Téléphone</th>
                                            <th>Horaires</th>
                                            <th>Date Création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agences as $agence): ?>
                                        <tr>
                                            <td><?php echo $agence['id_agence']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($agence['nom']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($agence['adresse'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($agence['telephone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($agence['horaires_ouverture'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($agence['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-agence" 
                                                        data-id="<?php echo $agence['id_agence']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Guichets Tab -->
                    <div class="tab-pane fade" id="guichets">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-desktop me-2"></i>Gestion des Guichets</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuichetModal">
                                <i class="fas fa-plus me-2"></i>Nouveau Guichet
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i> Liste des Guichets
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="guichetsLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des guichets...</p>
                                </div>
                                <table id="guichetsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Numéro</th>
                                            <th>Agence</th>
                                            <th>Type</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($guichets as $guichet): ?>
                                        <tr>
                                            <td><?php echo $guichet['id_guichet']; ?></td>
                                            <td><strong>G-<?php echo str_pad($guichet['numero_guichet'] ?? $guichet['id_guichet'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo htmlspecialchars($guichet['agence_nom']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo in_array($guichet['type_guichet'], ['Standard', 'Caisse']) ? 'primary' : 
                                                        ($guichet['type_guichet'] == 'DAB' ? 'info' : 'warning'); 
                                                ?>">
                                                    <?php echo $guichet['type_guichet']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $guichet['statut'] == 'Actif' ? 'success' : 
                                                        ($guichet['statut'] == 'Maintenance' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $guichet['statut']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-guichet" 
                                                        data-id="<?php echo $guichet['id_guichet']; ?>"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning toggle-guichet-status" 
                                                        data-id="<?php echo $guichet['id_guichet']; ?>"
                                                        title="Changer statut">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Tab -->
                    <div class="tab-pane fade" id="audit">
                        <h2 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Journal d'Audit</h2>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-history me-2"></i> Logs des Opérations
                            </div>
                            <div class="card-body">
                                <div class="action-loading" id="auditLoading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Chargement des logs d'audit...</p>
                                </div>
                                <?php if (empty($auditLogs)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Aucun log d'audit disponible.
                                    </div>
                                <?php else: ?>
                                <table id="auditTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date/Heure</th>
                                            <th>Table</th>
                                            <th>Opération</th>
                                            <th>ID Enregistrement</th>
                                            <th>Agent</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id_log']; ?></td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                            <td><code><?php echo htmlspecialchars($log['table_name']); ?></code></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $log['operation_type'] == 'INSERT' ? 'success' : 
                                                        ($log['operation_type'] == 'UPDATE' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $log['operation_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $log['record_id']; ?></td>
                                            <td><?php echo htmlspecialchars($log['agent_username'] ?? 'Système'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info view-log-details" 
                                                        data-id="<?php echo $log['id_log']; ?>"
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Agent -->
    <div class="modal fade" id="addAgentModal" tabindex="-1" aria-labelledby="addAgentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAgentModalLabel"><i class="fas fa-user-plus me-2"></i>Ajouter un Nouvel Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addAgentForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rôle *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="Agent">Agent</option>
                                        <option value="Caissier">Caissier</option>
                                        <option value="Administrateur">Administrateur</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Agence</label>
                                    <select class="form-select" name="id_agence">
                                        <option value="">Sélectionner une agence</option>
                                        <?php foreach ($agences as $agence): ?>
                                        <option value="<?php echo $agence['id_agence']; ?>"><?php echo htmlspecialchars($agence['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Indicatif pays</label>
                                    <select class="form-select" name="country_code">
                                        <option value="+257">+257 (Burundi)</option>
                                        <option value="+33">+33 (France)</option>
                                        <option value="+1">+1 (USA/Canada)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" name="phone_number" placeholder="Numéro de téléphone">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" name="password" id="passwordField" required>
                            <div class="password-strength mt-2">
                                <div class="strength-bar">
                                    <div class="strength-progress" id="strengthProgress"></div>
                                </div>
                                <div id="strengthText" class="text-muted small">Force: Aucune</div>
                                <ul class="password-requirements mt-2">
                                    <li id="req-length">Au moins 8 caractères</li>
                                    <li id="req-uppercase">Au moins une majuscule</li>
                                    <li id="req-lowercase">Au moins une minuscule</li>
                                    <li id="req-number">Au moins un chiffre</li>
                                    <li id="req-special">Au moins un caractère spécial</li>
                                </ul>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPasswordField" required>
                            <div id="passwordMatch" class="form-text"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="createAgentBtn" disabled>
                            <i class="fas fa-save me-2"></i>Créer l'agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Agent -->
    <div class="modal fade" id="editAgentModal" tabindex="-1" aria-labelledby="editAgentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAgentModalLabel"><i class="fas fa-edit me-2"></i>Modifier l'Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAgentForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editAgentId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="last_name" id="editLastName" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" class="form-control" name="username" id="editUsername" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="editEmail" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rôle *</label>
                                    <select class="form-select" name="role" id="editRole" required>
                                        <option value="Agent">Agent</option>
                                        <option value="Caissier">Caissier</option>
                                        <option value="Administrateur">Administrateur</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Agence</label>
                                    <select class="form-select" name="id_agence" id="editAgence">
                                        <option value="">Sélectionner une agence</option>
                                        <?php foreach ($agences as $agence): ?>
                                        <option value="<?php echo $agence['id_agence']; ?>"><?php echo htmlspecialchars($agence['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Indicatif pays</label>
                                    <select class="form-select" name="country_code" id="editCountryCode">
                                        <option value="+257">+257 (Burundi)</option>
                                        <option value="+33">+33 (France)</option>
                                        <option value="+1">+1 (USA/Canada)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" name="phone_number" id="editPhoneNumber" placeholder="Numéro de téléphone">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Modifier l'agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Changer Mot de Passe Agent -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel"><i class="fas fa-key me-2"></i>Changer le Mot de Passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="changePasswordAgentId">
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe *</label>
                            <input type="password" class="form-control" name="new_password" id="newPasswordField" required>
                            <div class="password-strength mt-2">
                                <div class="strength-bar">
                                    <div class="strength-progress" id="newStrengthProgress"></div>
                                </div>
                                <div id="newStrengthText" class="text-muted small">Force: Aucune</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmNewPasswordField" required>
                            <div id="newPasswordMatch" class="form-text"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="changePasswordBtn" disabled>
                            <i class="fas fa-save me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Client -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addClientModalLabel"><i class="fas fa-user-plus me-2"></i>Ajouter un Nouveau Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addClientForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="prenom" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Téléphone *</label>
                                    <input type="tel" class="form-control" name="telephone" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" rows="3" placeholder="Adresse complète"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Revenu mensuel (BIF)</label>
                                    <input type="number" class="form-control" name="revenu_mensuel" min="0" step="1000" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Score de crédit initial</label>
                                    <input type="number" class="form-control" name="score_credit" min="300" max="850" value="500">
                                    <div class="form-text">Entre 300 (faible) et 850 (excellent)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer le client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Client -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel"><i class="fas fa-edit me-2"></i>Modifier le Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editClientForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editClientId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" class="form-control" name="nom" id="editNom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" name="prenom" id="editPrenom" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="editClientEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Téléphone *</label>
                                    <input type="tel" class="form-control" name="telephone" id="editTelephone" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" id="editAdresse" rows="3" placeholder="Adresse complète"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Revenu mensuel (BIF)</label>
                                    <input type="number" class="form-control" name="revenu_mensuel" id="editRevenu" min="0" step="1000" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Score de crédit</label>
                                    <input type="number" class="form-control" name="score_credit" id="editScoreCredit" min="300" max="850" value="500">
                                    <div class="form-text">Entre 300 (faible) et 850 (excellent)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Modifier le client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Compte -->
    <div class="modal fade" id="addCompteModal" tabindex="-1" aria-labelledby="addCompteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompteModalLabel"><i class="fas fa-wallet me-2"></i>Ouvrir un Nouveau Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client *</label>
                                    <select class="form-select" name="id_client" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id_client']; ?>">
                                            <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom'] . ' (' . $client['email'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de compte *</label>
                                    <select class="form-select" name="id_type_compte" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($typesCompte as $type): ?>
                                        <option value="<?php echo $type['id_type_compte']; ?>">
                                            <?php echo htmlspecialchars($type['libelle'] . ' (' . $type['code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Numéro de compte *</label>
                                    <input type="text" class="form-control" name="num_compte" required 
                                           pattern="[A-Z0-9]{10,20}" title="Numéro de compte (10-20 caractères alphanumériques)">
                                    <div class="form-text">Format: lettres et chiffres uniquement, 10-20 caractères</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Solde initial (BIF)</label>
                                    <input type="number" class="form-control" name="solde_initial" min="0" step="1000" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Compte -->
    <div class="modal fade" id="editCompteModal" tabindex="-1" aria-labelledby="editCompteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCompteModalLabel"><i class="fas fa-edit me-2"></i>Modifier le Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editCompteId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Numéro de compte *</label>
                                    <input type="text" class="form-control" name="num_compte" id="editNumCompte" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de compte *</label>
                                    <select class="form-select" name="id_type_compte" id="editTypeCompte" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($typesCompte as $type): ?>
                                        <option value="<?php echo $type['id_type_compte']; ?>">
                                            <?php echo htmlspecialchars($type['libelle'] . ' (' . $type['code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statut *</label>
                            <select class="form-select" name="statut" id="editStatutCompte" required>
                                <option value="Actif">Actif</option>
                                <option value="Suspendu">Suspendu</option>
                                <option value="Clôturé">Clôturé</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Modifier le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Type de Compte -->
    <div class="modal fade" id="addTypeCompteModal" tabindex="-1" aria-labelledby="addTypeCompteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTypeCompteModalLabel"><i class="fas fa-plus me-2"></i>Nouveau Type de Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addTypeCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Code *</label>
                                    <input type="text" class="form-control" name="code" required 
                                           pattern="[A-Z_]+" title="Lettres majuscules et underscores uniquement">
                                    <div class="form-text">Ex: EPARGNE, COURANT, ENTREPRISE</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Libellé *</label>
                                    <input type="text" class="form-control" name="libelle" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Taux d'intérêt (%)</label>
                                    <input type="number" class="form-control" name="taux_interet" min="0" max="100" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais mensuels (BIF)</label>
                                    <input type="number" class="form-control" name="frais_gestion_mensuel" min="0" step="100" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Solde minimum (BIF)</label>
                                    <input type="number" class="form-control" name="solde_minimum" min="0" step="1000" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Découvert autorisé</label>
                                    <select class="form-select" name="decouvert_autorise">
                                        <option value="0">Non</option>
                                        <option value="1">Oui</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="montantDecouvertContainer" style="display: none;">
                            <label class="form-label">Montant découvert max (BIF)</label>
                            <input type="number" class="form-control" name="montant_decouvert_max" min="0" step="1000" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Type de Compte -->
    <div class="modal fade" id="editTypeCompteModal" tabindex="-1" aria-labelledby="editTypeCompteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTypeCompteModalLabel"><i class="fas fa-edit me-2"></i>Modifier le Type de Compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTypeCompteForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editTypeCompteId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Code *</label>
                                    <input type="text" class="form-control" name="code" id="editTypeCompteCode" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Libellé *</label>
                                    <input type="text" class="form-control" name="libelle" id="editTypeCompteLibelle" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editTypeCompteDescription" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Taux d'intérêt (%)</label>
                                    <input type="number" class="form-control" name="taux_interet" id="editTauxInteret" min="0" max="100" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais mensuels (BIF)</label>
                                    <input type="number" class="form-control" name="frais_gestion_mensuel" id="editFraisMensuels" min="0" step="100" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Solde minimum (BIF)</label>
                                    <input type="number" class="form-control" name="solde_minimum" id="editSoldeMinimum" min="0" step="1000" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Découvert autorisé</label>
                                    <select class="form-select" name="decouvert_autorise" id="editDecouvertAutorise">
                                        <option value="0">Non</option>
                                        <option value="1">Oui</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="editMontantDecouvertContainer">
                            <label class="form-label">Montant découvert max (BIF)</label>
                            <input type="number" class="form-control" name="montant_decouvert_max" id="editMontantDecouvertMax" min="0" step="1000" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="actif" id="editTypeCompteActif">
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Type de Transaction -->
    <div class="modal fade" id="addTypeTransactionModal" tabindex="-1" aria-labelledby="addTypeTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTypeTransactionModalLabel"><i class="fas fa-plus me-2"></i>Nouveau Type de Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addTypeTransactionForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Code *</label>
                                    <input type="text" class="form-control" name="code" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Libellé *</label>
                                    <input type="text" class="form-control" name="libelle" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Catégorie *</label>
                                    <select class="form-select" name="categorie" required>
                                        <option value="DEPOT">Dépôt</option>
                                        <option value="RETRAIT">Retrait</option>
                                        <option value="VIREMENT">Virement</option>
                                        <option value="PAIEMENT">Paiement</option>
                                        <option value="AUTRES">Autres</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sens *</label>
                                    <select class="form-select" name="sens" required>
                                        <option value="DEBIT">Débit</option>
                                        <option value="CREDIT">Crédit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais fixe (BIF)</label>
                                    <input type="number" class="form-control" name="frais_fixe" min="0" step="100" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais %</label>
                                    <input type="number" class="form-control" name="frais_pourcentage" min="0" max="100" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Montant min (BIF)</label>
                                    <input type="number" class="form-control" name="montant_min" min="0" step="100" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Montant max (BIF)</label>
                                    <input type="number" class="form-control" name="montant_max" min="0" step="1000" placeholder="Illimité">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nécessite guichet</label>
                            <select class="form-select" name="necessite_guichet">
                                <option value="1">Oui</option>
                                <option value="0">Non</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Type de Transaction -->
    <div class="modal fade" id="editTypeTransactionModal" tabindex="-1" aria-labelledby="editTypeTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTypeTransactionModalLabel"><i class="fas fa-edit me-2"></i>Modifier le Type de Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTypeTransactionForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editTypeTransactionId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Code *</label>
                                    <input type="text" class="form-control" name="code" id="editTypeTransactionCode" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Libellé *</label>
                                    <input type="text" class="form-control" name="libelle" id="editTypeTransactionLibelle" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Catégorie *</label>
                                    <select class="form-select" name="categorie" id="editCategorie" required>
                                        <option value="DEPOT">Dépôt</option>
                                        <option value="RETRAIT">Retrait</option>
                                        <option value="VIREMENT">Virement</option>
                                        <option value="PAIEMENT">Paiement</option>
                                        <option value="AUTRES">Autres</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sens *</label>
                                    <select class="form-select" name="sens" id="editSens" required>
                                        <option value="DEBIT">Débit</option>
                                        <option value="CREDIT">Crédit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais fixe (BIF)</label>
                                    <input type="number" class="form-control" name="frais_fixe" id="editFraisFixe" min="0" step="100" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Frais %</label>
                                    <input type="number" class="form-control" name="frais_pourcentage" id="editFraisPourcentage" min="0" max="100" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Montant min (BIF)</label>
                                    <input type="number" class="form-control" name="montant_min" id="editMontantMin" min="0" step="100" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Montant max (BIF)</label>
                                    <input type="number" class="form-control" name="montant_max" id="editMontantMax" min="0" step="1000" placeholder="Illimité">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nécessite guichet</label>
                            <select class="form-select" name="necessite_guichet" id="editNecessiteGuichet">
                                <option value="1">Oui</option>
                                <option value="0">Non</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="actif" id="editTypeTransactionActif">
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Agence -->
    <div class="modal fade" id="addAgenceModal" tabindex="-1" aria-labelledby="addAgenceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAgenceModalLabel"><i class="fas fa-plus me-2"></i>Nouvelle Agence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addAgenceForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse *</label>
                            <textarea class="form-control" name="adresse" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horaires d'ouverture</label>
                            <input type="text" class="form-control" name="horaires_ouverture" placeholder="Ex: Lun-Ven 8h-17h">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Services proposés</label>
                            <textarea class="form-control" name="services_proposes" rows="2" placeholder="Services disponibles dans cette agence"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Agence -->
    <div class="modal fade" id="editAgenceModal" tabindex="-1" aria-labelledby="editAgenceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAgenceModalLabel"><i class="fas fa-edit me-2"></i>Modifier l'Agence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAgenceForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editAgenceId">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" id="editAgenceNom" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse *</label>
                            <textarea class="form-control" name="adresse" id="editAgenceAdresse" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" id="editAgenceTelephone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horaires d'ouverture</label>
                            <input type="text" class="form-control" name="horaires_ouverture" id="editHorairesOuverture" placeholder="Ex: Lun-Ven 8h-17h">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Services proposés</label>
                            <textarea class="form-control" name="services_proposes" id="editServicesProposes" rows="2" placeholder="Services disponibles dans cette agence"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajouter Guichet -->
    <div class="modal fade" id="addGuichetModal" tabindex="-1" aria-labelledby="addGuichetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addGuichetModalLabel"><i class="fas fa-plus me-2"></i>Nouveau Guichet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addGuichetForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label class="form-label">Agence *</label>
                            <select class="form-select" name="id_agence" required>
                                <option value="">Sélectionner une agence</option>
                                <?php foreach ($agences as $agence): ?>
                                <option value="<?php echo $agence['id_agence']; ?>"><?php echo htmlspecialchars($agence['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Numéro de guichet</label>
                                    <input type="number" class="form-control" name="numero_guichet" min="1" max="999">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de guichet *</label>
                                    <select class="form-select" name="type_guichet" required>
                                        <option value="Standard">Standard</option>
                                        <option value="Prioritaire">Prioritaire</option>
                                        <option value="Entreprise">Entreprise</option>
                                        <option value="DAB">DAB</option>
                                        <option value="Caisse">Caisse</option>
                                        <option value="Conseil">Conseil</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifier Guichet -->
    <div class="modal fade" id="editGuichetModal" tabindex="-1" aria-labelledby="editGuichetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editGuichetModalLabel"><i class="fas fa-edit me-2"></i>Modifier le Guichet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editGuichetForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="id" id="editGuichetId">
                        <div class="mb-3">
                            <label class="form-label">Agence *</label>
                            <select class="form-select" name="id_agence" id="editGuichetAgence" required>
                                <option value="">Sélectionner une agence</option>
                                <?php foreach ($agences as $agence): ?>
                                <option value="<?php echo $agence['id_agence']; ?>"><?php echo htmlspecialchars($agence['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Numéro de guichet</label>
                                    <input type="number" class="form-control" name="numero_guichet" id="editNumeroGuichet" min="1" max="999">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de guichet *</label>
                                    <select class="form-select" name="type_guichet" id="editTypeGuichet" required>
                                        <option value="Standard">Standard</option>
                                        <option value="Prioritaire">Prioritaire</option>
                                        <option value="Entreprise">Entreprise</option>
                                        <option value="DAB">DAB</option>
                                        <option value="Caisse">Caisse</option>
                                        <option value="Conseil">Conseil</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statut *</label>
                            <select class="form-select" name="statut" id="editGuichetStatut" required>
                                <option value="Actif">Actif</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Hors service">Hors service</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nouvelle Transaction -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTransactionModalLabel"><i class="fas fa-exchange-alt me-2"></i>Nouvelle Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addTransactionForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Compte *</label>
                                    <select class="form-select" name="num_compte" required>
                                        <option value="">Sélectionner un compte</option>
                                        <?php foreach ($comptes as $compte): ?>
                                        <option value="<?php echo $compte['num_compte']; ?>">
                                            <?php echo htmlspecialchars($compte['num_compte'] . ' - ' . $compte['client_nom'] . ' ' . $compte['client_prenom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de transaction *</label>
                                    <select class="form-select" name="id_type_transaction" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($typesTransaction as $type): ?>
                                        <option value="<?php echo $type['id_type_transaction']; ?>" 
                                                data-sens="<?php echo $type['sens']; ?>"
                                                data-frais-fixe="<?php echo $type['frais_fixe']; ?>"
                                                data-frais-pourcentage="<?php echo $type['frais_pourcentage']; ?>">
                                            <?php echo htmlspecialchars($type['libelle'] . ' (' . $type['categorie'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Montant (BIF) *</label>
                                    <input type="number" class="form-control" name="montant" min="0" step="100" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Guichet</label>
                                    <select class="form-select" name="id_guichet">
                                        <option value="">Sélectionner un guichet</option>
                                        <?php foreach ($guichets as $guichet): ?>
                                        <option value="<?php echo $guichet['id_guichet']; ?>">
                                            <?php echo htmlspecialchars('G-' . ($guichet['numero_guichet'] ?? $guichet['id_guichet']) . ' - ' . $guichet['agence_nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Description de la transaction"></textarea>
                        </div>
                        <div class="alert alert-info" id="transactionInfo">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="transactionDetails">Sélectionnez un type de transaction pour voir les détails</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Exécuter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Détails Log -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logDetailsModalLabel"><i class="fas fa-info-circle me-2"></i>Détails du Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-danger">Anciennes Valeurs:</h6>
                            <pre id="oldValues" class="bg-light p-3 border rounded" style="max-height: 400px; overflow-y: auto;"></pre>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">Nouvelles Valeurs:</h6>
                            <pre id="newValues" class="bg-light p-3 border rounded" style="max-height: 400px; overflow-y: auto;"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    $(document).ready(function() {
        // ========================================
        // CORRECTION DES MODALS BOOTSTRAP
        // ========================================
        
        // Initialisation manuelle des modals pour éviter l'erreur backdrop
        const modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(modalEl => {
            // Vérifier si le modal n'est pas déjà initialisé
            if (!modalEl._bsModal) {
                try {
                    const modal = new bootstrap.Modal(modalEl, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                    
                    // Stocker la référence
                    modalEl._bsModal = modal;
                } catch (error) {
                    console.error('Erreur initialisation modal:', error);
                }
            }
        });

        // Gestion manuelle des boutons d'ouverture de modal
        $('[data-bs-toggle="modal"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this).data('bs-target');
            const modalElement = document.querySelector(target);
            
            if (modalElement && modalElement._bsModal) {
                modalElement._bsModal.show();
            } else {
                // Fallback si l'initialisation a échoué
                $(target).modal('show');
            }
        });

        // ========================================
        // FONCTIONS DE LOADING AMÉLIORÉES
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
        
        function showTableLoading(tableId) {
            $(`#${tableId}Loading`).show();
        }
        
        function hideTableLoading(tableId) {
            $(`#${tableId}Loading`).hide();
        }
        
        // ========================================
        // INITIALISATION DES DATATABLES AVEC CORRECTION CSP
        // ========================================
        const datatablesConfigFr = <?php echo json_encode($datatables_fr); ?>;
        
        const tableConfigs = {
            '#agentsTable': { order: [[0, 'asc']], language: datatablesConfigFr },
            '#clientsTable': { order: [[0, 'desc']], language: datatablesConfigFr },
            '#comptesTable': { order: [[6, 'desc']], language: datatablesConfigFr },
            '#typesCompteTable': { order: [[0, 'asc']], language: datatablesConfigFr },
            '#transactionsTable': { order: [[1, 'desc']], language: datatablesConfigFr },
            '#typesTransactionTable': { order: [[0, 'asc']], language: datatablesConfigFr },
            '#creditsTable': { order: [[1, 'desc']], language: datatablesConfigFr },
            '#agencesTable': { order: [[0, 'desc']], language: datatablesConfigFr },
            '#guichetsTable': { order: [[0, 'asc']], language: datatablesConfigFr },
            '#auditTable': { order: [[1, 'desc']], language: datatablesConfigFr }
        };
        
        Object.keys(tableConfigs).forEach(selector => {
            if ($(selector).length) {
                const tableId = selector.replace('#', '');
                showTableLoading(tableId);
                
                setTimeout(() => {
                    $(selector).DataTable({
                        language: datatablesConfigFr,
                        responsive: true,
                        pageLength: 25,
                        ...tableConfigs[selector]
                    });
                    hideTableLoading(tableId);
                }, 500);
            }
        });
        
        // ========================================
        // GRAPHIQUE DES TRANSACTIONS
        // ========================================
        const transData = <?php echo json_encode($transactionsChart); ?>;
        const labels = transData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' });
        });
        const data = transData.map(item => item.nombre);
        
        const ctx = document.getElementById('transactionsChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de transactions',
                        data: data,
                        borderColor: '#d32f2f',
                        backgroundColor: 'rgba(211, 47, 47, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
        
        // ========================================
        // VALIDATION MOT DE PASSE
        // ========================================
        let passwordValid = false;
        let passwordsMatch = false;
        let newPasswordValid = false;
        let newPasswordsMatch = false;
        
        $('#passwordField').on('input', function() {
            validatePassword($(this).val(), 'strengthProgress', 'strengthText');
        });
        
        $('#confirmPasswordField').on('input', function() {
            validatePasswordMatch($('#passwordField').val(), $(this).val(), 'passwordMatch');
        });
        
        $('#newPasswordField').on('input', function() {
            validatePassword($(this).val(), 'newStrengthProgress', 'newStrengthText');
        });
        
        $('#confirmNewPasswordField').on('input', function() {
            validatePasswordMatch($('#newPasswordField').val(), $(this).val(), 'newPasswordMatch');
        });
        
        function validatePassword(password, progressId, textId) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[@#$%^&*!()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            
            const validCount = Object.values(requirements).filter(Boolean).length;
            const strengthProgress = $(`#${progressId}`);
            const strengthText = $(`#${textId}`);
            
            let strengthClass = '', strengthLabel = '', progressWidth = 0;
            
            if (validCount <= 2) {
                strengthClass = 'strength-weak';
                strengthLabel = 'Faible';
                progressWidth = 20;
            } else if (validCount <= 4) {
                strengthClass = 'strength-medium';
                strengthLabel = 'Moyen';
                progressWidth = 60;
            } else {
                strengthClass = 'strength-strong';
                strengthLabel = 'Fort';
                progressWidth = 100;
            }
            
            strengthProgress.removeClass('strength-weak strength-medium strength-strong').addClass(strengthClass);
            strengthProgress.css('width', progressWidth + '%');
            strengthText.text('Force: ' + strengthLabel);
            
            if (progressId === 'strengthProgress') {
                passwordValid = validCount === 5;
                updateSubmitButton();
            } else {
                newPasswordValid = validCount === 5;
                updateChangePasswordButton();
            }
        }
        
        function validatePasswordMatch(password, confirmPassword, matchId) {
            const matchDiv = $(`#${matchId}`);
            
            if (confirmPassword === '') {
                matchDiv.text('').removeClass('text-danger text-success');
                if (matchId === 'passwordMatch') {
                    passwordsMatch = false;
                } else {
                    newPasswordsMatch = false;
                }
            } else if (password === confirmPassword) {
                matchDiv.text('✓ Les mots de passe correspondent').addClass('text-success').removeClass('text-danger');
                if (matchId === 'passwordMatch') {
                    passwordsMatch = true;
                } else {
                    newPasswordsMatch = true;
                }
            } else {
                matchDiv.text('✗ Les mots de passe ne correspondent pas').addClass('text-danger').removeClass('text-success');
                if (matchId === 'passwordMatch') {
                    passwordsMatch = false;
                } else {
                    newPasswordsMatch = false;
                }
            }
            
            if (matchId === 'passwordMatch') {
                updateSubmitButton();
            } else {
                updateChangePasswordButton();
            }
        }
        
        function updateSubmitButton() {
            $('#createAgentBtn').prop('disabled', !(passwordValid && passwordsMatch));
        }
        
        function updateChangePasswordButton() {
            $('#changePasswordBtn').prop('disabled', !(newPasswordValid && newPasswordsMatch));
        }
        
        // ========================================
        // GESTION DU FORMULAIRE TYPE DE COMPTE
        // ========================================
        $('select[name="decouvert_autorise"]').on('change', function() {
            if ($(this).val() == '1') {
                $('#montantDecouvertContainer').show();
            } else {
                $('#montantDecouvertContainer').hide();
            }
        });
        
        $('#editDecouvertAutorise').on('change', function() {
            if ($(this).val() == '1') {
                $('#editMontantDecouvertContainer').show();
            } else {
                $('#editMontantDecouvertContainer').hide();
            }
        });
        
        // ========================================
        // GESTION DU FORMULAIRE TRANSACTION
        // ========================================
        $('select[name="id_type_transaction"]').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const sens = selectedOption.data('sens');
            const fraisFixe = selectedOption.data('frais-fixe') || 0;
            const fraisPourcentage = selectedOption.data('frais-pourcentage') || 0;
            
            let details = `Sens: ${sens}`;
            if (fraisFixe > 0) {
                details += ` | Frais fixe: ${fraisFixe.toLocaleString()} BIF`;
            }
            if (fraisPourcentage > 0) {
                details += ` | Frais %: ${fraisPourcentage}%`;
            }
            
            $('#transactionDetails').text(details);
        });
        
        // ========================================
        // FONCTIONS D'ÉDITION
        // ========================================
        
        // Édition Agent
        $(document).on('click', '.edit-agent', function() {
            const agentId = $(this).data('id');
            loadAgentData(agentId);
        });
        
        function loadAgentData(agentId) {
            showGlobalLoading('Chargement des données agent...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_agent',
                    id: agentId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const agent = response.data;
                        $('#editAgentId').val(agent.id_agent);
                        $('#editFirstName').val(agent.first_name);
                        $('#editLastName').val(agent.last_name);
                        $('#editUsername').val(agent.username);
                        $('#editEmail').val(agent.email);
                        $('#editRole').val(agent.role);
                        $('#editAgence').val(agent.id_agence);
                        
                        if (agent.country_code) {
                            $('#editCountryCode').val(agent.country_code);
                        }
                        if (agent.phone_number) {
                            $('#editPhoneNumber').val(agent.phone_number);
                        }
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editAgentModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editAgentModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Changer mot de passe agent
        $(document).on('click', '.change-agent-password', function() {
            const agentId = $(this).data('id');
            $('#changePasswordAgentId').val(agentId);
            
            // Réinitialiser les champs
            $('#newPasswordField').val('');
            $('#confirmNewPasswordField').val('');
            $('#newStrengthProgress').css('width', '0%').removeClass('strength-weak strength-medium strength-strong');
            $('#newStrengthText').text('Force: Aucune');
            $('#newPasswordMatch').text('').removeClass('text-danger text-success');
            $('#changePasswordBtn').prop('disabled', true);
            
            // Ouvrir le modal
            const modalElement = document.querySelector('#changePasswordModal');
            if (modalElement && modalElement._bsModal) {
                modalElement._bsModal.show();
            } else {
                $('#changePasswordModal').modal('show');
            }
        });
        
        // Blocage/Déblocage Agent
        $(document).on('click', '.block-agent', function() {
            const agentId = $(this).data('id');
            if (confirm('Êtes-vous sûr de vouloir bloquer cet agent ?')) {
                blockUnblockAgent(agentId, 'block_agent', 'Agent bloqué avec succès');
            }
        });
        
        $(document).on('click', '.unblock-agent', function() {
            const agentId = $(this).data('id');
            if (confirm('Êtes-vous sûr de vouloir débloquer cet agent ?')) {
                blockUnblockAgent(agentId, 'unblock_agent', 'Agent débloqué avec succès');
            }
        });
        
        function blockUnblockAgent(agentId, action, successMessage) {
            showGlobalLoading('Traitement en cours...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: action,
                    id: agentId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', successMessage);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de l\'opération');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Client
        $(document).on('click', '.edit-client', function() {
            const clientId = $(this).data('id');
            loadClientData(clientId);
        });
        
        function loadClientData(clientId) {
            showGlobalLoading('Chargement des données client...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_client',
                    id: clientId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const client = response.data;
                        $('#editClientId').val(client.id_client);
                        $('#editNom').val(client.nom);
                        $('#editPrenom').val(client.prenom);
                        $('#editClientEmail').val(client.email);
                        $('#editTelephone').val(client.telephone);
                        $('#editAdresse').val(client.adresse || '');
                        $('#editRevenu').val(client.revenu_mensuel || 0);
                        $('#editScoreCredit').val(client.score_credit || 500);
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editClientModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editClientModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Compte
        $(document).on('click', '.edit-compte', function() {
            const compteId = $(this).data('id');
            loadCompteData(compteId);
        });
        
        function loadCompteData(compteId) {
            showGlobalLoading('Chargement des données compte...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_compte',
                    id: compteId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const compte = response.data;
                        $('#editCompteId').val(compte.id_compte);
                        $('#editNumCompte').val(compte.num_compte);
                        $('#editTypeCompte').val(compte.id_type_compte);
                        $('#editStatutCompte').val(compte.statut);
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editCompteModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editCompteModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Type de Compte
        $(document).on('click', '.edit-type-compte', function() {
            const typeId = $(this).data('id');
            loadTypeCompteData(typeId);
        });
        
        function loadTypeCompteData(typeId) {
            showGlobalLoading('Chargement des données type de compte...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_type_compte',
                    id: typeId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const type = response.data;
                        $('#editTypeCompteId').val(type.id_type_compte);
                        $('#editTypeCompteCode').val(type.code);
                        $('#editTypeCompteLibelle').val(type.libelle);
                        $('#editTypeCompteDescription').val(type.description || '');
                        $('#editTauxInteret').val(type.taux_interet);
                        $('#editFraisMensuels').val(type.frais_gestion_mensuel);
                        $('#editSoldeMinimum').val(type.solde_minimum);
                        $('#editDecouvertAutorise').val(type.decouvert_autorise);
                        $('#editMontantDecouvertMax').val(type.montant_decouvert_max);
                        $('#editTypeCompteActif').val(type.actif);
                        
                        // Gérer l'affichage du champ découvert
                        if (type.decouvert_autorise == '1') {
                            $('#editMontantDecouvertContainer').show();
                        } else {
                            $('#editMontantDecouvertContainer').hide();
                        }
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editTypeCompteModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editTypeCompteModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Type de Transaction
        $(document).on('click', '.edit-type-transaction', function() {
            const typeId = $(this).data('id');
            loadTypeTransactionData(typeId);
        });
        
        function loadTypeTransactionData(typeId) {
            showGlobalLoading('Chargement des données type de transaction...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_type_transaction',
                    id: typeId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const type = response.data;
                        $('#editTypeTransactionId').val(type.id_type_transaction);
                        $('#editTypeTransactionCode').val(type.code);
                        $('#editTypeTransactionLibelle').val(type.libelle);
                        $('#editCategorie').val(type.categorie);
                        $('#editSens').val(type.sens);
                        $('#editFraisFixe').val(type.frais_fixe);
                        $('#editFraisPourcentage').val(type.frais_pourcentage);
                        $('#editMontantMin').val(type.montant_min);
                        $('#editMontantMax').val(type.montant_max || '');
                        $('#editNecessiteGuichet').val(type.necessite_guichet);
                        $('#editTypeTransactionActif').val(type.actif);
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editTypeTransactionModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editTypeTransactionModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Agence
        $(document).on('click', '.edit-agence', function() {
            const agenceId = $(this).data('id');
            loadAgenceData(agenceId);
        });
        
        function loadAgenceData(agenceId) {
            showGlobalLoading('Chargement des données agence...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_agence',
                    id: agenceId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const agence = response.data;
                        $('#editAgenceId').val(agence.id_agence);
                        $('#editAgenceNom').val(agence.nom);
                        $('#editAgenceAdresse').val(agence.adresse);
                        $('#editAgenceTelephone').val(agence.telephone || '');
                        $('#editHorairesOuverture').val(agence.horaires_ouverture || '');
                        $('#editServicesProposes').val(agence.services_proposes || '');
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editAgenceModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editAgenceModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // Édition Guichet
        $(document).on('click', '.edit-guichet', function() {
            const guichetId = $(this).data('id');
            loadGuichetData(guichetId);
        });
        
        function loadGuichetData(guichetId) {
            showGlobalLoading('Chargement des données guichet...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_guichet',
                    id: guichetId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const guichet = response.data;
                        $('#editGuichetId').val(guichet.id_guichet);
                        $('#editGuichetAgence').val(guichet.id_agence);
                        $('#editNumeroGuichet').val(guichet.numero_guichet || '');
                        $('#editTypeGuichet').val(guichet.type_guichet);
                        $('#editGuichetStatut').val(guichet.statut);
                        
                        // Ouvrir le modal d'édition
                        const modalElement = document.querySelector('#editGuichetModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#editGuichetModal').modal('show');
                        }
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors du chargement des données');
                },
                complete: function() {
                    hideGlobalLoading();
                }
            });
        }
        
        // ========================================
        // SOUMISSION FORMULAIRES
        // ========================================
        
        // Formulaire Ajouter Agent
        $('#addAgentForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_agent', $(this), 'Agent créé avec succès');
        });
        
        // Formulaire Modifier Agent
        $('#editAgentForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_agent', $(this), 'Agent modifié avec succès');
        });
        
        // Formulaire Changer Mot de Passe
        $('#changePasswordForm').submit(function(e) {
            e.preventDefault();
            submitForm('change_agent_password', $(this), 'Mot de passe modifié avec succès');
        });
        
        // Formulaire Ajouter Client
        $('#addClientForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_client', $(this), 'Client créé avec succès');
        });
        
        // Formulaire Modifier Client
        $('#editClientForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_client', $(this), 'Client modifié avec succès');
        });
        
        // Formulaire Ajouter Compte
        $('#addCompteForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_compte', $(this), 'Compte créé avec succès');
        });
        
        // Formulaire Modifier Compte
        $('#editCompteForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_compte', $(this), 'Compte modifié avec succès');
        });
        
        // Formulaire Ajouter Type de Compte
        $('#addTypeCompteForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_type_compte', $(this), 'Type de compte créé avec succès');
        });
        
        // Formulaire Modifier Type de Compte
        $('#editTypeCompteForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_type_compte', $(this), 'Type de compte modifié avec succès');
        });
        
        // Formulaire Ajouter Type de Transaction
        $('#addTypeTransactionForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_type_transaction', $(this), 'Type de transaction créé avec succès');
        });
        
        // Formulaire Modifier Type de Transaction
        $('#editTypeTransactionForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_type_transaction', $(this), 'Type de transaction modifié avec succès');
        });
        
        // Formulaire Ajouter Agence
        $('#addAgenceForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_agence', $(this), 'Agence créée avec succès');
        });
        
        // Formulaire Modifier Agence
        $('#editAgenceForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_agence', $(this), 'Agence modifiée avec succès');
        });
        
        // Formulaire Ajouter Guichet
        $('#addGuichetForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_guichet', $(this), 'Guichet créé avec succès');
        });
        
        // Formulaire Modifier Guichet
        $('#editGuichetForm').submit(function(e) {
            e.preventDefault();
            submitForm('edit_guichet', $(this), 'Guichet modifié avec succès');
        });
        
        // Formulaire Ajouter Transaction
        $('#addTransactionForm').submit(function(e) {
            e.preventDefault();
            submitForm('add_transaction', $(this), 'Transaction effectuée avec succès');
        });
        
        // Fonction générique de soumission
        function submitForm(action, form, successMessage) {
            const submitBtn = form.find('button[type="submit"]');
            showButtonLoading(submitBtn, 'Traitement...');
            showGlobalLoading('Traitement en cours', 'Veuillez patienter...');
            
            const formData = new FormData(form[0]);
            formData.append('action', action);
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        showAlert('success', successMessage);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + (response.message || 'Erreur inconnue'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', xhr.responseText);
                    try {
                        const response = JSON.parse(xhr.responseText);
                        showAlert('danger', 'Erreur: ' + (response.message || error));
                    } catch (e) {
                        showAlert('danger', 'Erreur lors de l\'opération: ' + error);
                    }
                },
                complete: function() {
                    hideButtonLoading(submitBtn);
                    hideGlobalLoading();
                }
            });
        }
        
        // ========================================
        // TOGGLE STATUT AGENT
        // ========================================
        $(document).on('click', '.toggle-agent', function() {
            const agentId = $(this).data('id');
            const currentStatus = $(this).data('status');
            const newStatus = currentStatus ? 0 : 1;
            
            if (confirm('Voulez-vous vraiment ' + (currentStatus ? 'désactiver' : 'activer') + ' cet agent ?')) {
                const button = $(this);
                showButtonLoading(button, 'Modification...');
                
                $.ajax({
                    url: 'ajax_actions.php',
                    method: 'POST',
                    data: {
                        action: 'toggle_agent',
                        id: agentId,
                        status: newStatus,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Statut de l\'agent modifié avec succès');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert('danger', 'Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erreur:', xhr.responseText);
                        showAlert('danger', 'Erreur lors de la modification du statut');
                    },
                    complete: function() {
                        hideButtonLoading(button);
                    }
                });
            }
        });
        
        // ========================================
        // TOGGLE STATUT CLIENT
        // ========================================
        $(document).on('click', '.toggle-client', function() {
            const clientId = $(this).data('id');
            const currentStatus = $(this).data('status');
            const newStatus = currentStatus ? 0 : 1;
            
            if (confirm('Voulez-vous vraiment ' + (currentStatus ? 'désactiver' : 'activer') + ' ce client ?')) {
                const button = $(this);
                showButtonLoading(button, 'Modification...');
                
                $.ajax({
                    url: 'ajax_actions.php',
                    method: 'POST',
                    data: {
                        action: 'toggle_client',
                        id: clientId,
                        status: newStatus,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Statut du client modifié avec succès');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert('danger', 'Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erreur:', xhr.responseText);
                        showAlert('danger', 'Erreur lors de la modification du statut');
                    },
                    complete: function() {
                        hideButtonLoading(button);
                    }
                });
            }
        });
        
        // ========================================
        // GESTION DES CRÉDITS
        // ========================================
        $(document).on('click', '.approve-credit', function() {
            const demandeId = $(this).data('id');
            
            if (confirm('Êtes-vous sûr de vouloir approuver cette demande de crédit ?')) {
                const button = $(this);
                showButtonLoading(button, 'Validation...');
                showGlobalLoading('Validation de la demande de crédit', 'Traitement en cours...');
                
                $.ajax({
                    url: 'ajax_actions.php',
                    method: 'POST',
                    data: {
                        action: 'approve_credit',
                        id: demandeId,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Demande approuvée avec succès !');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert('danger', 'Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erreur:', xhr.responseText);
                        showAlert('danger', 'Erreur lors de l\'approbation');
                    },
                    complete: function() {
                        hideButtonLoading(button);
                        hideGlobalLoading();
                    }
                });
            }
        });
        
        $(document).on('click', '.reject-credit', function() {
            const demandeId = $(this).data('id');
            const motif = prompt('Motif du rejet (optionnel):');
            
            if (motif !== null) {
                const button = $(this);
                showButtonLoading(button, 'Rejet...');
                showGlobalLoading('Rejet de la demande de crédit', 'Traitement en cours...');
                
                $.ajax({
                    url: 'ajax_actions.php',
                    method: 'POST',
                    data: {
                        action: 'reject_credit',
                        id: demandeId,
                        commentaires: motif,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Demande rejetée avec succès !');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert('danger', 'Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error('Erreur:', xhr.responseText);
                        showAlert('danger', 'Erreur lors du rejet');
                    },
                    complete: function() {
                        hideButtonLoading(button);
                        hideGlobalLoading();
                    }
                });
            }
        });
        
        // ========================================
        // DÉTAILS DU LOG
        // ========================================
        $(document).on('click', '.view-log-details', function() {
            const logId = $(this).data('id');
            const button = $(this);
            showButtonLoading(button, 'Chargement...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'get_log_details',
                    id: logId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#oldValues').text(JSON.stringify(response.old_values, null, 2) || 'Aucune valeur');
                        $('#newValues').text(JSON.stringify(response.new_values, null, 2) || 'Aucune valeur');
                        
                        // Ouvrir le modal avec notre gestion personnalisée
                        const modalElement = document.querySelector('#logDetailsModal');
                        if (modalElement && modalElement._bsModal) {
                            modalElement._bsModal.show();
                        } else {
                            $('#logDetailsModal').modal('show');
                        }
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
        // GESTION DES GUICHETS
        // ========================================
        $(document).on('click', '.toggle-guichet-status', function() {
            const guichetId = $(this).data('id');
            const button = $(this);
            showButtonLoading(button, 'Changement statut...');
            
            $.ajax({
                url: 'ajax_actions.php',
                method: 'POST',
                data: {
                    action: 'toggle_guichet_status',
                    id: guichetId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Statut du guichet modifié avec succès');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', 'Erreur: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur:', xhr.responseText);
                    showAlert('danger', 'Erreur lors de la modification du statut');
                },
                complete: function() {
                    hideButtonLoading(button);
                }
            });
        });
        
        // ========================================
        // FONCTION D'ALERTE
        // ========================================
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     style="z-index: 9999; min-width: 400px;" role="alert">
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
        // RÉINITIALISATION FORMULAIRES
        // ========================================
        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('form')[0]?.reset();
            // Réinitialisation spécifique pour le formulaire agent
            if ($(this).attr('id') === 'addAgentModal') {
                $('.password-requirements li').removeClass('valid');
                $('#strengthProgress').css('width', '0%').removeClass('strength-weak strength-medium strength-strong');
                $('#strengthText').text('Force: Aucune');
                $('#passwordMatch').text('').removeClass('text-danger text-success');
                $('#createAgentBtn').prop('disabled', true);
                passwordValid = false;
                passwordsMatch = false;
            }
            // Réinitialisation pour le type de compte
            if ($(this).attr('id') === 'addTypeCompteModal') {
                $('#montantDecouvertContainer').hide();
            }
            // Réinitialisation pour les transactions
            if ($(this).attr('id') === 'addTransactionModal') {
                $('#transactionDetails').text('Sélectionnez un type de transaction pour voir les détails');
            }
        });
        
        // ========================================
        // GESTION DES ONGLETS
        // ========================================
        // Activation des onglets au clic
        $('.sidebar .nav-link').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            
            // Mettre à jour la classe active
            $('.sidebar .nav-link').removeClass('active');
            $(this).addClass('active');
            
            // Afficher l'onglet correspondant
            $('.tab-pane').removeClass('show active');
            $(target).addClass('show active');
        });
    });
    </script>
</body>
</html>