<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

$event_id = $_GET['id'] ?? 0;
$recent_registrations = [];
if ($event_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT r.*, u.full_name, u.email 
            FROM registrations r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.event_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT 10");
        $stmt->execute([$event_id]);
        $recent_registrations = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recent_registrations = [];
    }
}

$event = null;
$success_message = '';
$error_message = '';

// Get event data
if ($event_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in admin/edit_event.php: " . $e->getMessage());
    }
}

if (!$event) {
    redirect('events.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $organizer_id = (int)($_POST['organizer_id'] ?? 0);
        $event_type = trim($_POST['event_type'] ?? 'webinar');
        $duration = (int)($_POST['duration'] ?? 60);
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $meeting_url = trim($_POST['meeting_url'] ?? '');
        $status = trim($_POST['status'] ?? 'upcoming');

        // Validation
        if (empty($title)) {
            $error_message = __("event_title_required");
        } elseif (empty($description)) {
            $error_message = __("description_required");
        } elseif (empty($event_date)) {
            $error_message = __("event_date_required");
        } elseif (empty($organizer_id)) {
            $error_message = __("organizer_required");
        } else {
            // Update event
            $imagePath = $event['image_url'] ?? null;
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/';
                $ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                $filename = 'event_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['event_image']['tmp_name'], $targetFile)) {
                    $imagePath = $filename;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE events 
                SET title = ?, description = ?, event_date = ?, organizer_id = ?, event_type = ?, 
                    duration = ?, max_participants = ?, price = ?, meeting_url = ?, image_url = ?, updated_at = NOW()
                WHERE id = ?
            ");

            if ($stmt->execute([
                $title,
                $description,
                $event_date,
                $organizer_id,
                $event_type,
                $duration,
                $max_participants,
                $price,
                $meeting_url,
                $imagePath,
                $event_id
            ])) {
                $success_message = __("event_updated_successfully");

                // Refresh event data
                $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch();
            } else {
                $error_message = __("error_updating_event");
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in edit_event: " . $e->getMessage());
        $error_message = __("database_error");
    }
}

// Get organizers for dropdown
try {
    $organizers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'instructor') AND is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/edit_event.php: " . $e->getMessage());
    $organizers = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_event') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> <?= __('edit_event') ?></h1>
                        <p><?= __('modify_event_info') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> <?= __('view') ?>
                            </a>
                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                            </a>
                        </div>

                        <div class="user-menu">
                            <?php
                            $current_user = null;
                            try {
                                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                $stmt->execute([current_user_id()]);
                                $current_user = $stmt->fetch();
                            } catch (PDOException $e) {
                                error_log("Error fetching current user: " . $e->getMessage());
                            }
                            ?>
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;">
                                    <?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Event Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> <?= __('event_information') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="eventForm" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title" class="form-label">
                                    <i class="fas fa-heading"></i> <?= __('event_title') ?> *
                                </label>
                                <input type="text" name="title" id="title" class="form-control"
                                    value="<?= htmlspecialchars($event['title']) ?>" required
                                    placeholder="Entrez le titre de l'événement">
                            </div>

                            <div class="form-group">
                                <label for="organizer_id" class="form-label">
                                    <i class="fas fa-user-tie"></i> <?= __('organizer') ?> *
                                </label>
                                <select name="organizer_id" id="organizer_id" class="form-control" required>
                                    <option value="">-- <?= __('choose_organizer') ?> --</option>
                                    <?php foreach ($organizers as $organizer): ?>
                                        <option value="<?= $organizer['id'] ?>"
                                            <?= $event['organizer_id'] == $organizer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($organizer['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar"></i> <?= __('event_date') ?> *
                                </label>
                                <input type="datetime-local" name="event_date" id="event_date" class="form-control"
                                    value="<?= date('Y-m-d\TH:i', strtotime($event['event_date'])) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-tag"></i> <?= __('event_type') ?>
                                </label>
                                <select name="event_type" id="event_type" class="form-control">
                                    <option value="webinar" <?= $event['event_type'] === 'webinar' ? 'selected' : '' ?>>
                                        <?= __('webinar') ?></option>
                                    <option value="workshop"
                                        <?= $event['event_type'] === 'workshop' ? 'selected' : '' ?>><?= __('workshop') ?></option>
                                    <option value="meetup" <?= $event['event_type'] === 'meetup' ? 'selected' : '' ?>>
                                        <?= __('meetup') ?></option>
                                    <option value="conference"
                                        <?= $event['event_type'] === 'conference' ? 'selected' : '' ?>><?= __('conference') ?>
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration" class="form-label">
                                    <i class="fas fa-clock"></i> Durée (minutes)
                                </label>
                                <input type="number" name="duration" id="duration" class="form-control"
                                    value="<?= htmlspecialchars($event['duration']) ?>" min="15" max="480">
                            </div>

                            <div class="form-group">
                                <label for="max_participants" class="form-label">
                                    <i class="fas fa-users"></i> Participants Max
                                </label>
                                <input type="number" name="max_participants" id="max_participants" class="form-control"
                                    value="<?= htmlspecialchars($event['max_participants']) ?>" min="1">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="price" class="form-label">
                                    <i class="fas fa-money-bill-wave"></i> Prix (GHS)
                                </label>
                                <input type="number" name="price" id="price" class="form-control"
                                    value="<?= htmlspecialchars($event['price']) ?>" step="0.01" min="0">
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label">
                                    <i class="fas fa-toggle-on"></i> Statut
                                </label>
                                <select name="status" id="status" class="form-control">
                                    <option value="upcoming" <?= $event['status'] === 'upcoming' ? 'selected' : '' ?>>À
                                        venir</option>
                                    <option value="ongoing" <?= $event['status'] === 'ongoing' ? 'selected' : '' ?>>En
                                        cours</option>
                                    <option value="completed" <?= $event['status'] === 'completed' ? 'selected' : '' ?>>
                                        Terminé</option>
                                    <option value="cancelled" <?= $event['status'] === 'cancelled' ? 'selected' : '' ?>>
                                        Annulé</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="meeting_url" class="form-label">
                                <i class="fas fa-link"></i> URL de Réunion
                            </label>
                            <input type="url" name="meeting_url" id="meeting_url" class="form-control"
                                value="<?= htmlspecialchars($event['meeting_url']) ?>"
                                placeholder="https://meet.google.com/...">
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left"></i> <?= __('description') ?> *
                            </label>
                            <textarea name="description" id="description" class="form-control" rows="6"
                                placeholder="Décrivez l'événement en détail..."><?= htmlspecialchars($event['description']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="event_image" class="form-label">
                                <i class="fas fa-image"></i> Image de l'Événement
                            </label>
                            <input type="file" name="event_image" id="event_image" accept="image/*"
                                class="form-control">
                            <?php if (!empty($event['image_url'])): ?>
                                <div style="margin-top:10px;">
                                    <img src="../uploads/<?= htmlspecialchars($event['image_url']) ?>" alt="Image actuelle"
                                        style="max-width:150px; border-radius:8px;">
                                    <br>
                                    <small>Image actuelle</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= __('save_changes') ?>
                            </button>

                            <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> <?= __('view_event') ?>
                            </a>

                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                            </a>

                            <button type="reset" class="btn btn-warning">
                                <i class="fas fa-undo"></i> <?= __('reset') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Event Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> <?= __('event_information') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>ID de l'événement:</strong>
                                <span>#<?= $event['id'] ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Date de création:</strong>
                                <span><?= date('d/m/Y H:i', strtotime($event['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Dernière modification:</strong>
                                <span><?= isset($event['updated_at']) ? date('d/m/Y H:i', strtotime($event['updated_at'])) : 'Non modifié' ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Statut actuel:</strong>
                                <span class="badge <?php
                                                    switch ($event['status']) {
                                                        case 'upcoming':
                                                            echo 'badge-info';
                                                            break;
                                                        case 'ongoing':
                                                            echo 'badge-success';
                                                            break;
                                                        case 'completed':
                                                            echo 'badge-secondary';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'badge-danger';
                                                            break;
                                                        default:
                                                            echo 'badge-secondary';
                                                            break;
                                                    }
                                                    ?>">
                                    <?php
                                    switch ($event['status']) {
                                        case 'upcoming':
                                            echo 'À venir';
                                            break;
                                        case 'ongoing':
                                            echo 'En cours';
                                            break;
                                        case 'completed':
                                            echo 'Terminé';
                                            break;
                                        case 'cancelled':
                                            echo 'Annulé';
                                            break;
                                        default:
                                            echo htmlspecialchars($event['status']);
                                            break;
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Inscriptions Récentes</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_registrations)): ?>
                        <div class="alert alert-info">
                            Aucune inscription récente pour cet événement.
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Date d'inscription</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $registration): ?>
                                    <tr>
                                        <td><?= $registration['id'] ?></td>
                                        <td><?= htmlspecialchars($registration['full_name']) ?></td>
                                        <td><?= htmlspecialchars($registration['email']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($registration['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('eventForm');
            const priceInput = document.getElementById('price');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');

            // Price validation
            priceInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                const price = parseFloat(priceInput.value);
                const organizer = document.getElementById('organizer_id').value;
                const eventDate = document.getElementById('event_date').value;

                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir le titre de l\'événement.');
                    titleInput.focus();
                    return;
                }

                if (!description) {
                    e.preventDefault();
                    alert('Veuillez saisir la description de l\'événement.');
                    descriptionInput.focus();
                    return;
                }

                if (!organizer) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un organisateur.');
                    document.getElementById('organizer_id').focus();
                    return;
                }

                if (!eventDate) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une date pour l\'événement.');
                    document.getElementById('event_date').focus();
                    return;
                }
            });
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>

    <style>
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item strong {
            color: var(--text-primary);
        }

        .info-item span {
            color: var(--text-secondary);
        }

        /* Admin Language Switcher */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: white;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--light-color);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }
    </style>
</body>

</html>