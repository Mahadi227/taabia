<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2><?= __('admin_panel') ?></h2>
        <p><?php
            $current_user = null;
            try {
                $stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'] ?? 0]);
                $current_user = $stmt->fetch();
            } catch (PDOException $e) {
                error_log("Error fetching current user: " . $e->getMessage());
            }
            echo htmlspecialchars($current_user['fullname'] ?? __('administrator'));
            ?></p>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span><?= __('dashboard') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="users.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span><?= __('users') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="courses.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                <i class="fas fa-book"></i>
                <span><?= __('courses') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="products.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i>
                <span><?= __('products') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="orders.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span><?= __('orders') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="events.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span><?= __('events') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="event_registrations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'event_registrations.php' ? 'active' : '' ?>">
                <i class="fas fa-user-check"></i>
                <span><?= __('registrations') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="contact_messages.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contact_messages.php' ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i>
                <span><?= __('messages') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="blog_posts.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['blog_posts.php', 'add_blog_post.php', 'edit_blog_post.php']) ? 'active' : '' ?>">
                <i class="fas fa-newspaper"></i>
                <span><?= __('blog_posts') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="transactions.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : '' ?>">
                <i class="fas fa-exchange-alt"></i>
                <span><?= __('transactions') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="payout_requests.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payout_requests.php' ? 'active' : '' ?>">
                <i class="fas fa-hand-holding-usd"></i>
                <span><?= __('payout_requests') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="earnings.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'earnings.php' ? 'active' : '' ?>">
                <i class="fas fa-wallet"></i>
                <span><?= __('earnings') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="payments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span><?= __('payments') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="payment_stats.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payment_stats.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span><?= __('statistics') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="certificates.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['certificates.php', 'certificate_details.php']) ? 'active' : '' ?>">
                <i class="fas fa-certificate"></i>
                <span><?= __('certificates') ?></span>
            </a>
        </div>

        <div class="nav-item">
            <a href="site_settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'site_settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span><?= __('site_settings') ?></span>
            </a>
        </div>

        <div class="nav-item" style="margin-top: 2rem;">
            <a href="../auth/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span><?= __('logout') ?></span>
            </a>
        </div>
    </nav>
</div>