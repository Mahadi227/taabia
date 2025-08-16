<?php
// Paramètres de connexion
$host = 'localhost';
$db   = 'taabia_skills'; // nom de ta base
$user = 'root';          // utilisateur MySQL (par défaut en local)
$pass = '';              // mot de passe (souvent vide en local)
$charset = 'utf8mb4';

// DSN PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Options PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Gérer les erreurs
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Résultats sous forme de tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES   => false                   // Sécurité renforcée
];

// Connexion
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Arrêter l'exécution si la connexion échoue
    exit('Erreur de connexion à la base de données : ' . $e->getMessage());
}
?>
