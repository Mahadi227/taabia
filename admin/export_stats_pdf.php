<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include('../includes/db.php');

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Préparer les stats
$start = $month . '-01';
$end = date("Y-m-t", strtotime($start));

// Chiffre d'affaires global
$revenueStmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE created_at BETWEEN ? AND ?");
$revenueStmt->execute([$start, $end]);
$totalRevenue = $revenueStmt->fetch()['total'] ?? 0;

// Total payé
$payoutStmt = $pdo->prepare("SELECT SUM(amount) AS total FROM payouts WHERE created_at BETWEEN ? AND ?");
$payoutStmt->execute([$start, $end]);
$totalPaid = $payoutStmt->fetch()['total'] ?? 0;

// Solde en attente
$balance = $totalRevenue - $totalPaid;

// Top instructeurs
$topStmt = $pdo->prepare("SELECT u.full_name, SUM(t.amount) as total 
                          FROM transactions t 
                          JOIN users u ON u.id = t.user_id 
                          WHERE t.created_at BETWEEN ? AND ? 
                          GROUP BY t.user_id 
                          ORDER BY total DESC LIMIT 5");
$topStmt->execute([$start, $end]);
$topInstructors = $topStmt->fetchAll();

ob_start();
?>

<h2>Statistiques de Paiements - <?= date('F Y', strtotime($month)) ?></h2>
<p><strong>Chiffre d’affaires :</strong> <?= number_format($totalRevenue, 2) ?> GHS</p>
<p><strong>Total payé :</strong> <?= number_format($totalPaid, 2) ?> GHS</p>
<p><strong>Solde en attente :</strong> <?= number_format($balance, 2) ?> GHS</p>

<h3>Top instructeurs</h3>
<table border="1" cellspacing="0" cellpadding="5" width="100%">
    <thead>
        <tr>
            <th>Nom</th>
            <th>Montant</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($topInstructors as $instructor): ?>
            <tr>
                <td><?= htmlspecialchars($instructor['full_name']) ?></td>
                <td><?= number_format($instructor['total'], 2) ?> GHS</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("statistiques_paiements_{$month}.pdf", ["Attachment" => false]);
?>