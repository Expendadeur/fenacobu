<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Conseiller Bancaire - FenacoBu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-red: #dc2626;
            --primary-green: #16a34a;
            --primary-white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-gray: #374151;
            --border-color: #e5e7eb;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }

        .header {
            background: linear-gradient(135deg, var(--primary-green), #22c55e);
            color: var(--primary-white);
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .header .advisor-info {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-red), #ef4444);
            color: var(--primary-white);
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 8px;
            border-left: 4px solid var(--primary-green);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-green);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-gray);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-green);
            color: var(--primary-white);
        }

        .btn-primary:hover {
            background: #15803d;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--primary-red);
            color: var(--primary-white);
        }

        .btn-secondary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }

        .btn-outline:hover {
            background: var(--primary-green);
            color: var(--primary-white);
        }

        .client-search {
            margin-bottom: 2rem;
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--primary-white);
        }

        .client-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .client-item:hover {
            background-color: var(--light-gray);
        }

        .client-item:last-child {
            border-bottom: none;
        }

        .client-name {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .client-details {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .operations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(22, 163, 74, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-green);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background-color: rgba(22, 163, 74, 0.1);
            color: var(--primary-green);
        }

        .status-pending {
            background-color: rgba(251, 191, 36, 0.1);
            color: #f59e0b;
        }

        .status-rejected {
            background-color: rgba(220, 38, 38, 0.1);
            color: var(--primary-red);
        }

        .transaction-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-amount {
            font-weight: 600;
        }

        .transaction-amount.credit {
            color: var(--primary-green);
        }

        .transaction-amount.debit {
            color: var(--primary-red);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--primary-white);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-red);
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Interface Conseiller Bancaire - FenacoBu</h1>
                <div class="advisor-info">
                    Conseiller: <span id="advisor-name">Jean Dupont</span> | 
                    Agence: Bujumbura Centre | 
                    <span id="current-time"></span>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <button class="btn btn-outline" style="background: transparent; border-color: white; color: white;" onclick="showNotifications()" id="notifications">
                    🔔 Notifications <span id="notif-count" style="background: var(--primary-red); border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.8rem; margin-left: 0.5rem;">3</span>
                </button>
                <button class="btn btn-outline" style="background: transparent; border-color: white; color: white;" onclick="showQuickHelp()">
                    ❓ Aide
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Tableau de Bord -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    Statistiques du Portefeuille
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number" id="total-clients">0</div>
                            <div class="stat-label">Clients Actifs</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="pending-requests">0</div>
                            <div class="stat-label">Demandes en Attente</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="monthly-revenue">0 BIF</div>
                            <div class="stat-label">Revenus du Mois</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="active-loans">0</div>
                            <div class="stat-label">Crédits Actifs</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Recherche Client
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Rechercher un client</label>
                        <input type="text" class="form-input" id="client-search" placeholder="Nom, prénom ou numéro de compte...">
                    </div>
                    <div id="search-results" class="search-results" style="display: none;"></div>
                </div>
            </div>
        </div>

        <!-- Opérations Principales -->
        <div class="operations-grid">
            <div class="card">
                <div class="card-header">
                    Nouvelle Demande de Crédit
                </div>
                <div class="card-body">
                    <form id="credit-form">
                        <div class="form-group">
                            <label class="form-label">Client</label>
                            <select class="form-select" id="client-select">
                                <option value="">Sélectionner un client</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type de Crédit</label>
                            <select class="form-select" id="credit-type">
                                <option value="">Sélectionner un type</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Montant (BIF)</label>
                            <input type="number" class="form-input" id="credit-amount" placeholder="Montant demandé">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Durée (mois)</label>
                            <input type="number" class="form-input" id="credit-duration" placeholder="Durée en mois">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <span class="btn-text">Créer la Demande</span>
                            <span class="loading" style="display: none;"></span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Transactions Récentes
                </div>
                <div class="card-body">
                    <div class="transaction-list" id="recent-transactions">
                        <!-- Les transactions seront chargées dynamiquement -->
                    </div>
                    <button class="btn btn-outline" style="width: 100%; margin-top: 1rem;" onclick="loadMoreTransactions()">
                        Voir Plus de Transactions
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Demandes en Attente
                </div>
                <div class="card-body">
                    <div id="pending-requests-list">
                        <!-- Les demandes seront chargées dynamiquement -->
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <button class="btn btn-primary" style="flex: 1;" onclick="openNewAccountModal()">
                            Nouveau Compte
                        </button>
                        <button class="btn btn-secondary" style="flex: 1;" onclick="generateReport()">
                            Rapport
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour nouveau compte -->
    <div id="newAccountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('newAccountModal')">&times;</span>
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Nouveau Compte Client</h2>
            <form id="new-account-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-input" id="client-nom" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <input type="text" class="form-input" id="client-prenom" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" class="form-input" id="client-adresse">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-input" id="client-telephone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="client-email">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Type de Compte</label>
                    <select class="form-select" id="account-type" required>
                        <option value="">Sélectionner un type</option>
                        <option value="Courant">Compte Courant</option>
                        <option value="Epargne">Compte Épargne</option>
                        <option value="Entreprise">Compte Entreprise</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Dépôt Initial (BIF)</label>
                    <input type="number" class="form-input" id="initial-deposit" min="0">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Créer le Compte
                </button>
            </form>
        </div>
    </div>

    <!-- Modal pour génération de rapport -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('reportModal')">&times;</span>
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Générer un Rapport</h2>
            <form id="report-form">
                <div class="form-group">
                    <label class="form-label">Type de Rapport</label>
                    <select class="form-select" id="report-type" required>
                        <option value="">Sélectionner un type</option>
                        <option value="portfolio">Rapport de Portefeuille</option>
                        <option value="transactions">Rapport des Transactions</option>
                        <option value="credits">Rapport des Crédits</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Date de Début</label>
                        <input type="date" class="form-input" id="date-from" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date de Fin</label>
                        <input type="date" class="form-input" id="date-to" required>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeModal('reportModal')">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="generateSelectedReport()">
                        Générer le Rapport
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Données de simulation
        let clientsData = [];
        let transactionsData = [];
        let creditTypesData = [];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadInitialData();
            setupEventListeners();
            updateDashboard();
            updateCurrentTime();
        });

        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
            
            // Mettre à jour toutes les minutes
            setTimeout(updateCurrentTime, 60000);
        }

        function setupEventListeners() {
            // Recherche client en temps réel
            document.getElementById('client-search').addEventListener('input', function(e) {
                searchClients(e.target.value);
            });

            // Formulaire de crédit
            document.getElementById('credit-form').addEventListener('submit', function(e) {
                e.preventDefault();
                submitCreditRequest();
            });

            // Formulaire nouveau compte
            document.getElementById('new-account-form').addEventListener('submit', function(e) {
                e.preventDefault();
                createNewAccount();
            });

            // Auto-actualisation des données toutes les 30 secondes
            setInterval(function() {
                updateDashboard();
                loadRecentTransactions();
            }, 30000);

            // Initialiser les dates par défaut pour les rapports
            const today = new Date();
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            document.getElementById('date-from').value = firstDayOfMonth.toISOString().split('T')[0];
            document.getElementById('date-to').value = today.toISOString().split('T')[0];
        }

        function loadInitialData() {
            // Simulation de données clients
            clientsData = [
                { 
                    id: 1, 
                    nom: 'Ntahompagaze', 
                    prenom: 'Jean', 
                    numero_compte: 'BDI001234', 
                    telephone: '+257 79 123 456', 
                    email: 'jean.n@email.com', 
                    solde: 500000,
                    adresse: 'Quartier Rohero, Bujumbura'
                },
                { 
                    id: 2, 
                    nom: 'Nizigiyimana', 
                    prenom: 'Marie', 
                    numero_compte: 'BDI001235', 
                    telephone: '+257 79 234 567', 
                    email: 'marie.n@email.com', 
                    solde: 750000,
                    adresse: 'Avenue de la Révolution, Bujumbura'
                },
                { 
                    id: 3, 
                    nom: 'Bigirimana', 
                    prenom: 'Pierre', 
                    numero_compte: 'BDI001236', 
                    telephone: '+257 79 345 678', 
                    email: 'pierre.b@email.com', 
                    solde: 1200000,
                    adresse: 'Quartier Ngagara, Bujumbura'
                }
            ];

            // Types de crédit
            creditTypesData = [
                { 
                    id: 1, 
                    nom: 'Crédit Personnel', 
                    taux_interet: 12.5, 
                    duree_max_mois: 60, 
                    montant_min: 100000, 
                    montant_max: 5000000 
                },
                { 
                    id: 2, 
                    nom: 'Crédit Immobilier', 
                    taux_interet: 8.5, 
                    duree_max_mois: 240, 
                    montant_min: 1000000, 
                    montant_max: 50000000 
                },
                { 
                    id: 3, 
                    nom: 'Crédit Auto', 
                    taux_interet: 10.0, 
                    duree_max_mois: 84, 
                    montant_min: 500000, 
                    montant_max: 20000000 
                }
            ];

            // Transactions récentes
            transactionsData = [
                { 
                    id: 1, 
                    client: 'Jean Ntahompagaze', 
                    type: 'Dépôt', 
                    montant: 250000, 
                    date: '2025-09-25', 
                    type_class: 'credit' 
                },
                { 
                    id: 2, 
                    client: 'Marie Nizigiyimana', 
                    type: 'Retrait', 
                    montant: -100000, 
                    date: '2025-09-25', 
                    type_class: 'debit' 
                },
                { 
                    id: 3, 
                    client: 'Pierre Bigirimana', 
                    type: 'Virement', 
                    montant: 500000, 
                    date: '2025-09-24', 
                    type_class: 'credit' 
                },
                { 
                    id: 4, 
                    client: 'Jean Ntahompagaze', 
                    type: 'Paiement Facture', 
                    montant: -75000, 
                    date: '2025-09-24', 
                    type_class: 'debit' 
                }
            ];

            populateSelects();
            loadRecentTransactions();
            loadPendingRequests();
        }

        function populateSelects() {
            const clientSelect = document.getElementById('client-select');
            const creditTypeSelect = document.getElementById('credit-type');

            clientSelect.innerHTML = '<option value="">Sélectionner un client</option>';
            clientsData.forEach(client => {
                clientSelect.innerHTML += `<option value="${client.id}">${client.prenom} ${client.nom} - ${client.numero_compte}</option>`;
            });

            creditTypeSelect.innerHTML = '<option value="">Sélectionner un type</option>';
            creditTypesData.forEach(type => {
                creditTypeSelect.innerHTML += `<option value="${type.id}">${type.nom} (${type.taux_interet}%)</option>`;
            });
        }

        function searchClients(query) {
            const resultsDiv = document.getElementById('search-results');
            
            if (query.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            const filteredClients = clientsData.filter(client => 
                client.nom.toLowerCase().includes(query.toLowerCase()) ||
                client.prenom.toLowerCase().includes(query.toLowerCase()) ||
                client.numero_compte.toLowerCase().includes(query.toLowerCase())
            );

            let resultsHTML = '';
            filteredClients.forEach(client => {
                resultsHTML += `
                    <div class="client-item" onclick="selectClient(${client.id})">
                        <div class="client-name">${client.prenom} ${client.nom}</div>
                        <div class="client-details">Compte: ${client.numero_compte} | Solde: ${formatCurrency(client.solde)}</div>
                    </div>
                `;
            });

            resultsDiv.innerHTML = resultsHTML;
            resultsDiv.style.display = filteredClients.length > 0 ? 'block' : 'none';
        }

        function selectClient(clientId) {
            const client = clientsData.find(c => c.id === clientId);
            document.getElementById('client-search').value = `${client.prenom} ${client.nom} - ${client.numero_compte}`;
            document.getElementById('search-results').style.display = 'none';
            
            // Pré-sélectionner le client dans le formulaire de crédit
            document.getElementById('client-select').value = clientId;
            
            // Afficher les détails du client
            showClientDetails(client);
        }

        function showClientDetails(client) {
            // Générer l'historique des transactions pour ce client
            const clientTransactions = transactionsData.filter(t => t.client.includes(client.nom));
            
            const detailHtml = `
                <div id="clientDetailModal" class="modal" style="display: block;">
                    <div class="modal-content" style="max-width: 900px;">
                        <span class="close" onclick="closeClientDetailModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                            Détails du Client: ${client.prenom} ${client.nom}
                        </h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Informations Personnelles</div>
                                <div class="card-body">
                                    <div style="display: grid; gap: 1rem;">
                                        <div><strong>Numéro de compte:</strong> ${client.numero_compte}</div>
                                        <div><strong>Adresse:</strong> ${client.adresse || 'Non renseignée'}</div>
                                        <div><strong>Téléphone:</strong> ${client.telephone}</div>
                                        <div><strong>Email:</strong> ${client.email}</div>
                                        <div><strong>Solde actuel:</strong> <span style="color: var(--primary-green); font-weight: bold;">${formatCurrency(client.solde)}</span></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Actions Rapides</div>
                                <div class="card-body">
                                    <div style="display: grid; gap: 0.5rem;">
                                        <button class="btn btn-primary" onclick="openTransactionModal(${client.id}, 'depot')">
                                            Effectuer un Dépôt
                                        </button>
                                        <button class="btn btn-secondary" onclick="openTransactionModal(${client.id}, 'retrait')">
                                            Effectuer un Retrait
                                        </button>
                                        <button class="btn btn-outline" onclick="openTransferModal(${client.id})">
                                            Effectuer un Virement
                                        </button>
                                        <button class="btn btn-outline" onclick="generateClientStatement(${client.id})">
                                            Relevé de Compte
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card" style="margin: 0;">
                            <div class="card-header">Historique des Transactions</div>
                            <div class="card-body">
                                <div class="transaction-list" style="max-height: 300px;">
                                    ${clientTransactions.length > 0 ? 
                                        clientTransactions.map(t => `
                                            <div class="transaction-item">
                                                <div>
                                                    <div style="font-weight: 500;">${t.type}</div>
                                                    <div style="font-size: 0.9rem; color: #6b7280;">${t.date}</div>
                                                </div>
                                                <div class="transaction-amount ${t.type_class}">
                                                    ${t.montant > 0 ? '+' : ''}${formatCurrency(Math.abs(t.montant))}
                                                </div>
                                            </div>
                                        `).join('') :
                                        '<div style="text-align: center; color: #6b7280; padding: 2rem;">Aucune transaction récente</div>'
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', detailHtml);
        }

        function closeClientDetailModal() {
            const modal = document.getElementById('clientDetailModal');
            if (modal) modal.remove();
        }

        function openTransactionModal(clientId, type) {
            const client = clientsData.find(c => c.id === clientId);
            const title = type === 'depot' ? 'Effectuer un Dépôt' : 'Effectuer un Retrait';
            const action = type === 'depot' ? 'Déposer' : 'Retirer';
            
            const modalHtml = `
                <div id="transactionModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <span class="close" onclick="closeTransactionModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">${title}</h2>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                            <strong>Client:</strong> ${client.prenom} ${client.nom}<br>
                            <strong>Compte:</strong> ${client.numero_compte}<br>
                            <strong>Solde actuel:</strong> ${formatCurrency(client.solde)}
                        </div>
                        <form id="transaction-form">
                            <div class="form-group">
                                <label class="form-label">Montant (BIF)</label>
                                <input type="number" class="form-input" id="transaction-amount" min="1" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Commentaire</label>
                                <textarea class="form-input" id="transaction-comment" rows="3" placeholder="Description de la transaction..."></textarea>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeTransactionModal()">
                                    Annuler
                                </button>
                                <button type="submit" class="btn ${type === 'depot' ? 'btn-primary' : 'btn-secondary'}" style="flex: 1;">
                                    ${action}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            document.getElementById('transaction-form').addEventListener('submit', function(e) {
                e.preventDefault();
                processTransaction(clientId, type);
            });
        }

        function closeTransactionModal() {
            const modal = document.getElementById('transactionModal');
            if (modal) modal.remove();
        }

        function processTransaction(clientId, type) {
            const amount = parseInt(document.getElementById('transaction-amount').value);
            const comment = document.getElementById('transaction-comment').value;
            const client = clientsData.find(c => c.id === clientId);
            
            if (type === 'retrait' && amount > client.solde) {
                alert('Solde insuffisant pour effectuer ce retrait.');
                return;
            }
            
            // Mettre à jour le solde
            if (type === 'depot') {
                client.solde += amount;
            } else {
                client.solde -= amount;
            }
            
            // Ajouter la transaction à l'historique
            const newTransaction = {
                id: transactionsData.length + 1,
                client: `${client.prenom} ${client.nom}`,
                type: type === 'depot' ? 'Dépôt' : 'Retrait',
                montant: type === 'depot' ? amount : -amount,
                date: new Date().toLocaleDateString('fr-CA'),
                type_class: type === 'depot' ? 'credit' : 'debit'
            };
            
            transactionsData.unshift(newTransaction);
            
            alert(`${type === 'depot' ? 'Dépôt' : 'Retrait'} de ${formatCurrency(amount)} effectué avec succès!`);
            closeTransactionModal();
            
            // Rafraîchir les données affichées
            loadRecentTransactions();
            updateDashboard();
            
            // Fermer le modal de détail client et le réouvrir avec les nouvelles données
            closeClientDetailModal();
            setTimeout(() => showClientDetails(client), 500);
        }

        function openTransferModal(clientId) {
            const client = clientsData.find(c => c.id === clientId);
            const otherClients = clientsData.filter(c => c.id !== clientId);
            
            const modalHtml = `
                <div id="transferModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <span class="close" onclick="closeTransferModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Effectuer un Virement</h2>
                        <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                            <strong>Compte débiteur:</strong> ${client.prenom} ${client.nom} (${client.numero_compte})<br>
                            <strong>Solde disponible:</strong> ${formatCurrency(client.solde)}
                        </div>
                        <form id="transfer-form">
                            <div class="form-group">
                                <label class="form-label">Compte bénéficiaire</label>
                                <select class="form-select" id="beneficiary-select" required>
                                    <option value="">Sélectionner un compte</option>
                                    ${otherClients.map(c => `<option value="${c.id}">${c.prenom} ${c.nom} - ${c.numero_compte}</option>`).join('')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Montant (BIF)</label>
                                <input type="number" class="form-input" id="transfer-amount" min="1" max="${client.solde}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Motif du virement</label>
                                <textarea class="form-input" id="transfer-reason" rows="3" placeholder="Motif du virement..." required></textarea>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeTransferModal()">
                                    Annuler
                                </button>
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    Effectuer le Virement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            document.getElementById('transfer-form').addEventListener('submit', function(e) {
                e.preventDefault();
                processTransfer(clientId);
            });
        }

        function closeTransferModal() {
            const modal = document.getElementById('transferModal');
            if (modal) modal.remove();
        }

        function processTransfer(senderId) {
            const sender = clientsData.find(c => c.id === senderId);
            const beneficiaryId = parseInt(document.getElementById('beneficiary-select').value);
            const beneficiary = clientsData.find(c => c.id === beneficiaryId);
            const amount = parseInt(document.getElementById('transfer-amount').value);
            const reason = document.getElementById('transfer-reason').value;
            
            if (amount > sender.solde) {
                alert('Solde insuffisant pour effectuer ce virement.');
                return;
            }
            
            // Effectuer le virement
            sender.solde -= amount;
            beneficiary.solde += amount;
            
            // Ajouter les transactions
            const debitTransaction = {
                id: transactionsData.length + 1,
                client: `${sender.prenom} ${sender.nom}`,
                type: 'Virement (Débit)',
                montant: -amount,
                date: new Date().toLocaleDateString('fr-CA'),
                type_class: 'debit'
            };
            
            const creditTransaction = {
                id: transactionsData.length + 2,
                client: `${beneficiary.prenom} ${beneficiary.nom}`,
                type: 'Virement (Crédit)',
                montant: amount,
                date: new Date().toLocaleDateString('fr-CA'),
                type_class: 'credit'
            };
            
            transactionsData.unshift(debitTransaction, creditTransaction);
            
            alert(`Virement de ${formatCurrency(amount)} effectué avec succès de ${sender.prenom} ${sender.nom} vers ${beneficiary.prenom} ${beneficiary.nom}!`);
            closeTransferModal();
            
            // Rafraîchir les données
            loadRecentTransactions();
            updateDashboard();
            closeClientDetailModal();
        }

        function generateClientStatement(clientId) {
            const client = clientsData.find(c => c.id === clientId);
            const clientTransactions = transactionsData.filter(t => t.client.includes(client.nom));
            
            const statementWindow = window.open('', '_blank');
            statementWindow.document.write(`
                <html>
                    <head>
                        <title>Relevé de Compte - ${client.prenom} ${client.nom}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #16a34a; padding-bottom: 20px; }
                            .client-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                            th { background: #16a34a; color: white; }
                            .credit { color: #16a34a; font-weight: bold; }
                            .debit { color: #dc2626; font-weight: bold; }
                            .footer { margin-top: 30px; text-align: center; font-size: 0.9rem; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>FENACOBU - Banque Coopérative</h1>
                            <h2>RELEVÉ DE COMPTE</h2>
                        </div>
                        
                        <div class="client-info">
                            <strong>Client:</strong> ${client.prenom} ${client.nom}<br>
                            <strong>Numéro de compte:</strong> ${client.numero_compte}<br>
                            <strong>Adresse:</strong> ${client.adresse || 'Non renseignée'}<br>
                            <strong>Téléphone:</strong> ${client.telephone}<br>
                            <strong>Email:</strong> ${client.email}<br>
                            <strong>Solde actuel:</strong> <span class="credit">${formatCurrency(client.solde)}</span>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type de Transaction</th>
                                    <th>Montant</th>
                                    <th>Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${clientTransactions.length > 0 ? 
                                    clientTransactions.map((t, index) => {
                                        return `
                                            <tr>
                                                <td>${t.date}</td>
                                                <td>${t.type}</td>
                                                <td class="${t.type_class}">
                                                    ${t.montant > 0 ? '+' : ''}${formatCurrency(Math.abs(t.montant))}
                                                </td>
                                                <td>${formatCurrency(client.solde)}</td>
                                            </tr>
                                        `;
                                    }).join('') :
                                    '<tr><td colspan="4" style="text-align: center; color: #666;">Aucune transaction trouvée</td></tr>'
                                }
                            </tbody>
                        </table>
                        
                        <div class="footer">
                            <p>Relevé généré le: ${new Date().toLocaleDateString('fr-FR')} par ${document.getElementById('advisor-name').textContent}</p>
                            <p>FENACOBU - Votre partenaire financier de confiance</p>
                        </div>
                    </body>
                </html>
            `);
            statementWindow.document.close();
            statementWindow.print();
        }

        function submitCreditRequest() {
            const formData = {
                client_id: parseInt(document.getElementById('client-select').value),
                credit_type: parseInt(document.getElementById('credit-type').value),
                amount: parseInt(document.getElementById('credit-amount').value),
                duration: parseInt(document.getElementById('credit-duration').value)
            };

            if (!formData.client_id || !formData.credit_type || !formData.amount || !formData.duration) {
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            // Analyser le crédit
            const analysis = analyzeCredit(formData.client_id, formData.credit_type, formData.amount, formData.duration);
            
            if (!analysis) {
                alert('Erreur lors de l\'analyse du crédit.');
                return;
            }

            // Afficher les résultats de l'analyse
            showCreditAnalysis(analysis);
        }

        function analyzeCredit(clientId, creditTypeId, amount, duration) {
            const client = clientsData.find(c => c.id === clientId);
            const creditType = creditTypesData.find(ct => ct.id === creditTypeId);
            
            if (!client || !creditType) return null;

            const analysis = {
                client: client,
                creditType: creditType,
                requestedAmount: amount,
                requestedDuration: duration,
                score: 0,
                recommendations: [],
                risks: [],
                approved: false
            };

            // Analyse du ratio dette/revenu (simulation basée sur le solde)
            const estimatedIncome = client.solde * 0.1; // Estimation simplifiée
            const monthlyPayment = calculateMonthlyPayment(amount, creditType.taux_interet, duration);
            const debtRatio = monthlyPayment / estimatedIncome;

            if (debtRatio > 0.4) {
                analysis.risks.push('Ratio d\'endettement élevé (> 40%)');
                analysis.score -= 20;
            } else if (debtRatio > 0.3) {
                analysis.risks.push('Ratio d\'endettement modéré (30-40%)');
                analysis.score -= 10;
            } else {
                analysis.score += 15;
            }

            // Analyse de la capacité de remboursement
            if (client.solde < amount * 0.1) {
                analysis.risks.push('Apport personnel insuffisant');
                analysis.score -= 15;
            } else {
                analysis.score += 10;
            }

            // Analyse de l'historique client
            const clientHistory = transactionsData.filter(t => t.client.includes(client.nom));
            if (clientHistory.length > 2) {
                analysis.score += 10;
                analysis.recommendations.push('Client avec historique bancaire positif');
            }

            // Vérification des limites du produit
            if (amount < creditType.montant_min) {
                analysis.risks.push(`Montant inférieur au minimum (${formatCurrency(creditType.montant_min)})`);
            } else if (amount > creditType.montant_max) {
                analysis.risks.push(`Montant supérieur au maximum (${formatCurrency(creditType.montant_max)})`);
            }

            if (duration > creditType.duree_max_mois) {
                analysis.risks.push(`Durée supérieure au maximum (${creditType.duree_max_mois} mois)`);
            }

            // Décision finale
            analysis.approved = analysis.score >= 0 && analysis.risks.length <= 1;
            
            if (analysis.approved) {
                analysis.recommendations.push('Crédit recommandé pour approbation');
            } else {
                analysis.recommendations.push('Crédit nécessitant une évaluation approfondie');
            }

            return analysis;
        }

        function calculateMonthlyPayment(principal, annualRate, months) {
            const monthlyRate = annualRate / 100 / 12;
            return (principal * monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                   (Math.pow(1 + monthlyRate, months) - 1);
        }

        function showCreditAnalysis(analysis) {
            const monthlyPayment = calculateMonthlyPayment(analysis.requestedAmount, analysis.creditType.taux_interet, analysis.requestedDuration);
            const totalAmount = monthlyPayment * analysis.requestedDuration;
            const totalInterest = totalAmount - analysis.requestedAmount;

            const analysisHtml = `
                <div id="creditAnalysisModal" class="modal" style="display: block;">
                    <div class="modal-content" style="max-width: 900px;">
                        <span class="close" onclick="closeCreditAnalysisModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                            Analyse de la Demande de Crédit
                        </h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Détails de la Demande</div>
                                <div class="card-body">
                                    <div style="display: grid; gap: 0.5rem;">
                                        <div><strong>Client:</strong> ${analysis.client.prenom} ${analysis.client.nom}</div>
                                        <div><strong>Type de crédit:</strong> ${analysis.creditType.nom}</div>
                                        <div><strong>Montant demandé:</strong> ${formatCurrency(analysis.requestedAmount)}</div>
                                        <div><strong>Durée:</strong> ${analysis.requestedDuration} mois</div>
                                        <div><strong>Taux d'intérêt:</strong> ${analysis.creditType.taux_interet}%</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Calcul du Crédit</div>
                                <div class="card-body">
                                    <div style="display: grid; gap: 0.5rem;">
                                        <div><strong>Mensualité:</strong> <span style="color: var(--primary-green);">${formatCurrency(monthlyPayment)}</span></div>
                                        <div><strong>Montant total:</strong> ${formatCurrency(totalAmount)}</div>
                                        <div><strong>Intérêts totaux:</strong> ${formatCurrency(totalInterest)}</div>
                                        <div><strong>Score d'évaluation:</strong> 
                                            <span style="color: ${analysis.score >= 0 ? 'var(--primary-green)' : 'var(--primary-red)'};">
                                                ${analysis.score}/100
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${analysis.recommendations.length > 0 ? `
                            <div class="card" style="margin: 0 0 1rem 0;">
                                <div class="card-header" style="background: var(--primary-green);">Recommandations</div>
                                <div class="card-body">
                                    ${analysis.recommendations.map(rec => `<div style="margin-bottom: 0.5rem;">✓ ${rec}</div>`).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${analysis.risks.length > 0 ? `
                            <div class="card" style="margin: 0 0 1rem 0;">
                                <div class="card-header" style="background: var(--primary-red);">Risques Identifiés</div>
                                <div class="card-body">
                                    ${analysis.risks.map(risk => `<div style="margin-bottom: 0.5rem;">⚠ ${risk}</div>`).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button class="btn btn-outline" onclick="closeCreditAnalysisModal()">
                                Retour
                            </button>
                            ${analysis.approved ? `
                                <button class="btn btn-primary" onclick="approveCreditRequest()">
                                    Approuver le Crédit
                                </button>
                            ` : `
                                <button class="btn btn-secondary" onclick="requestMoreInfo()">
                                    Demander Plus d'Informations
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', analysisHtml);
        }

        function closeCreditAnalysisModal() {
            const modal = document.getElementById('creditAnalysisModal');
            if (modal) modal.remove();
        }

        function approveCreditRequest() {
            alert('Demande de crédit approuvée ! Le dossier a été transmis pour finalisation.');
            closeCreditAnalysisModal();
            document.getElementById('credit-form').reset();
            updateDashboard();
        }

        function requestMoreInfo() {
            alert('Demande d\'informations complémentaires envoyée au client.');
            closeCreditAnalysisModal();
        }

        function loadRecentTransactions() {
            const container = document.getElementById('recent-transactions');
            let html = '';

            transactionsData.slice(0, 5).forEach(transaction => {
                html += `
                    <div class="transaction-item">
                        <div>
                            <div style="font-weight: 500;">${transaction.client}</div>
                            <div style="font-size: 0.9rem; color: #6b7280;">${transaction.type} - ${transaction.date}</div>
                        </div>
                        <div class="transaction-amount ${transaction.type_class}">
                            ${transaction.montant > 0 ? '+' : ''}${formatCurrency(Math.abs(transaction.montant))}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function loadPendingRequests() {
            const container = document.getElementById('pending-requests-list');
            const pendingRequests = [
                { client: 'Alice Nshimirimana', type: 'Crédit Personnel', montant: 2000000, status: 'En attente' },
                { client: 'Bob Hakizimana', type: 'Nouveau Compte', montant: 0, status: 'Documents manquants' }
            ];

            let html = '';
            pendingRequests.forEach(request => {
                html += `
                    <div class="transaction-item">
                        <div>
                            <div style="font-weight: 500;">${request.client}</div>
                            <div style="font-size: 0.9rem; color: #6b7280;">${request.type}</div>
                        </div>
                        <div>
                            <span class="status-badge status-pending">${request.status}</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function updateDashboard() {
            // Simulation de mise à jour des statistiques
            setTimeout(() => {
                document.getElementById('total-clients').textContent = clientsData.length;
                document.getElementById('pending-requests').textContent = '2';
                document.getElementById('monthly-revenue').textContent = formatCurrency(15750000);
                document.getElementById('active-loans').textContent = '12';
            }, 500);
        }

        function openNewAccountModal() {
            document.getElementById('newAccountModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function createNewAccount() {
            const formData = {
                nom: document.getElementById('client-nom').value,
                prenom: document.getElementById('client-prenom').value,
                adresse: document.getElementById('client-adresse').value,
                telephone: document.getElementById('client-telephone').value,
                email: document.getElementById('client-email').value,
                type: document.getElementById('account-type').value,
                deposit: document.getElementById('initial-deposit').value
            };

            // Simulation de création de compte
            setTimeout(() => {
                alert(`Compte créé avec succès pour ${formData.prenom} ${formData.nom} !`);
                closeModal('newAccountModal');
                document.getElementById('new-account-form').reset();
                
                // Ajouter le nouveau client aux données
                const newClient = {
                    id: clientsData.length + 1,
                    nom: formData.nom,
                    prenom: formData.prenom,
                    numero_compte: `BDI00${1237 + clientsData.length}`,
                    telephone: formData.telephone,
                    email: formData.email,
                    solde: parseInt(formData.deposit) || 0,
                    adresse: formData.adresse
                };
                clientsData.push(newClient);
                populateSelects();
                updateDashboard();
            }, 1000);
        }

        function loadMoreTransactions() {
            alert('Chargement de plus de transactions...');
        }

        function generateReport() {
            document.getElementById('reportModal').style.display = 'block';
        }

        function generateSelectedReport() {
            const reportType = document.getElementById('report-type').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            
            if (!reportType || !dateFrom || !dateTo) {
                alert('Veuillez remplir tous les champs pour générer le rapport.');
                return;
            }

            // Simulation de génération de rapport
            const reportData = generateReportData(reportType, dateFrom, dateTo);
            displayReport(reportData, reportType);
            closeModal('reportModal');
        }

        function generateReportData(type, dateFrom, dateTo) {
            const reports = {
                'portfolio': {
                    title: 'Rapport de Portefeuille Client',
                    data: [
                        { label: 'Nombre de clients actifs', value: clientsData.length },
                        { label: 'Solde total des comptes', value: clientsData.reduce((sum, client) => sum + client.solde, 0) },
                        { label: 'Nouveaux clients (période)', value: 3 },
                        { label: 'Taux de satisfaction', value: '94%' }
                    ]
                },
                'transactions': {
                    title: 'Rapport des Transactions',
                    data: [
                        { label: 'Total des dépôts', value: 2450000 },
                        { label: 'Total des retraits', value: 1230000 },
                        { label: 'Nombre de virements', value: 45 },
                        { label: 'Frais générés', value: 125000 }
                    ]
                },
                'credits': {
                    title: 'Rapport des Crédits',
                    data: [
                        { label: 'Demandes en cours', value: 8 },
                        { label: 'Crédits approuvés', value: 12 },
                        { label: 'Montant total accordé', value: 45000000 },
                        { label: 'Taux de défaut', value: '2.1%' }
                    ]
                }
            };
            return reports[type];
        }

        function displayReport(reportData, type) {
            const reportHtml = `
                <div id="reportResultModal" class="modal" style="display: block;">
                    <div class="modal-content" style="max-width: 800px;">
                        <span class="close" onclick="closeReportResultModal()">&times;</span>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <h2 style="color: var(--primary-green); margin: 0;">${reportData.title}</h2>
                            <button class="btn btn-secondary" onclick="printReport()">
                                Imprimer
                            </button>
                        </div>
                        <div id="report-content">
                            <table style="width: 100%; border-collapse: collapse; background: white;">
                                <thead>
                                    <tr style="background: var(--light-gray);">
                                        <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border-color);">Élément</th>
                                        <th style="padding: 1rem; text-align: right; border-bottom: 2px solid var(--border-color);">Valeur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${reportData.data.map(item => `
                                        <tr>
                                            <td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color); font-weight: 500;">${item.label}</td>
                                            <td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: right; color: var(--primary-green); font-weight: 600;">
                                                ${typeof item.value === 'number' && type !== 'portfolio' ? formatCurrency(item.value) : item.value}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', reportHtml);
        }

        function closeReportResultModal() {
            const modal = document.getElementById('reportResultModal');
            if (modal) modal.remove();
        }

        function printReport() {
            const reportContent = document.getElementById('report-content').innerHTML;
            const reportTitle = document.querySelector('#reportResultModal h2').textContent;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Rapport - FenacoBu</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 10px; border: 1px solid #ddd; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .date { text-align: right; font-size: 0.9rem; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>FENACOBU - Banque Coopérative</h1>
                            <h2>${reportTitle}</h2>
                        </div>
                        <div class="date">Généré le: ${new Date().toLocaleDateString('fr-FR')}</div>
                        ${reportContent}
                        <div style="margin-top: 30px; font-size: 0.9rem; color: #666;">
                            Rapport généré par: ${document.getElementById('advisor-name').textContent}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function showNotifications() {
            const notifications = [
                { type: 'info', message: 'Nouvelle demande de crédit de Alice Nshimirimana', time: '10:30', icon: '🔵' },
                { type: 'warning', message: 'Échéance de crédit approchant pour Bob Hakizimana', time: '09:15', icon: '⚠️' },
                { type: 'success', message: 'Paiement d\'échéance reçu de Pierre Bigirimana', time: '08:45', icon: '✅' }
            ];

            let notifHtml = `
                <div id="notificationModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <span class="close" onclick="closeNotificationModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                            🔔 Notifications
                        </h2>
                        <div class="notification-list">
            `;

            notifications.forEach(notif => {
                const colorClass = notif.type === 'info' ? 'var(--primary-green)' : 
                                  notif.type === 'warning' ? '#f59e0b' : 'var(--primary-green)';
                
                notifHtml += `
                    <div class="notification-item" style="padding: 1rem; border-left: 4px solid ${colorClass}; background: var(--light-gray); margin-bottom: 1rem; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="margin-right: 0.5rem;">${notif.icon}</span>
                                ${notif.message}
                            </div>
                            <small style="color: #6b7280;">${notif.time}</small>
                        </div>
                    </div>
                `;
            });

            notifHtml += `
                        </div>
                        <button class="btn btn-outline" style="width: 100%;" onclick="markAllAsRead()">
                            Marquer tout comme lu
                        </button>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', notifHtml);
        }

        function closeNotificationModal() {
            const modal = document.getElementById('notificationModal');
            if (modal) modal.remove();
        }

        function markAllAsRead() {
            document.getElementById('notif-count').textContent = '0';
            document.getElementById('notif-count').style.display = 'none';
            alert('Toutes les notifications ont été marquées comme lues.');
            closeNotificationModal();
        }

        function showQuickHelp() {
            const helpHtml = `
                <div id="helpModal" class="modal" style="display: block;">
                    <div class="modal-content" style="max-width: 800px;">
                        <span class="close" onclick="closeHelpModal()">&times;</span>
                        <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                            Guide d'Utilisation - Interface Conseiller
                        </h2>
                        
                        <div style="display: grid; gap: 1.5rem;">
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Recherche et Gestion Clients</div>
                                <div class="card-body">
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Utilisez la barre de recherche pour trouver rapidement un client</li>
                                        <li>Cliquez sur un client dans les résultats pour voir ses détails complets</li>
                                        <li>Effectuez des dépôts, retraits et virements depuis le profil client</li>
                                        <li>Générez des relevés de compte en un clic</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Demandes de Crédit</div>
                                <div class="card-body">
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Sélectionnez un client et le type de crédit souhaité</li>
                                        <li>L'analyse automatique évalue la faisabilité</li>
                                        <li>Consultez les recommandations et risques identifiés</li>
                                        <li>Approuvez ou demandez des informations complémentaires</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Rapports et Suivi</div>
                                <div class="card-body">
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Générez des rapports de portefeuille, transactions ou crédits</li>
                                        <li>Sélectionnez la période d'analyse souhaitée</li>
                                        <li>Imprimez ou exportez les rapports</li>
                                        <li>Suivez vos objectifs commerciaux en temps réel</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card" style="margin: 0;">
                                <div class="card-header">Bonnes Pratiques</div>
                                <div class="card-body">
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        <li>Vérifiez toujours l'identité du client avant toute opération</li>
                                        <li>Documentez les raisons des opérations importantes</li>
                                        <li>Respectez les limites de crédit et procédures internes</li>
                                        <li>Consultez les notifications régulièrement</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <button class="btn btn-primary" onclick="closeHelpModal()">
                                Compris
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', helpHtml);
        }

        function closeHelpModal() {
            const modal = document.getElementById('helpModal');
            if (modal) modal.remove();
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'BIF',
                minimumFractionDigits: 0
            }).format(amount);
        }

        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    // Pour les modals dynamiques, les supprimer
                    if (['clientDetailModal', 'transactionModal', 'transferModal', 'creditAnalysisModal', 'notificationModal', 'helpModal', 'reportResultModal'].includes(modal.id)) {
                        modal.remove();
                    }
                }
            });
        }
    </script>
</body>
</html>