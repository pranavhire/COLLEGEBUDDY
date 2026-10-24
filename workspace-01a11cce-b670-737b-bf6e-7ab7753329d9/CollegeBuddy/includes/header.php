<?php
/*
 * includes/header.php
 * <head>, navigation bar, flash messages and breadcrumbs.
 * A page sets these variables before including this file:
 *   $pageTitle   (text shown in the browser tab)
 *   $activeNav   (highlights the current menu item)
 *   $breadcrumbs (optional list: [['Label', 'page.php'], ['Current page', '']])
 *   $adminSection (admin pages only, shows the admin sub-menu)
 */
$pageTitle = $pageTitle ?? 'Home';
$activeNav = $activeNav ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$me = current_user();
$flashes = get_flashes();
$unread = $me ? unread_message_count((int) $me['id']) : 0;

$navLinks = ['home' => ['Home', 'index.php']];
if ($me) {
    $navLinks['dashboard'] = ['Dashboard', 'dashboard.php'];
}
$navLinks += [
    'marketplace' => ['Marketplace', 'marketplace.php'],
    'study' => ['Study Hub', 'study.php'],
    'requests' => ['Request Board', 'requests.php'],
    'attendance' => ['Attendance', 'attendance.php'],
    'cgpa' => ['CGPA Calculator', 'cgpa.php'],
];
$flashIcons = [
    'success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-octagon-fill',
    'warning' => 'bi-exclamation-triangle-fill', 'info' => 'bi-info-circle-fill',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="College Buddy - Your College. Your Community. Your Buddy. Used item marketplace, free study resources, request board and academic calculators for students.">
    <title><?= e($pageTitle) ?> | College Buddy</title>
    <link rel="icon" href="<?= href('images/logo.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= href('css/vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= href('css/vendor/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= href('css/style.css') ?>">
</head>
<body class="cb-body d-flex flex-column min-vh-100">
<a class="skip-link" href="#main-content">Skip to main content</a>

<header>
    <nav class="navbar navbar-expand-xl cb-navbar sticky-top" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand cb-brand" href="<?= href('index.php') ?>" aria-label="College Buddy home">
                <img src="<?= href('images/logo.svg') ?>" alt="" width="34" height="34">
                <span class="cb-brand-text">COLLEGE<span class="cb-brand-accent">BUDDY</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cbNavMenu"
                    aria-controls="cbNavMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="cbNavMenu">
                <ul class="navbar-nav me-auto align-items-xl-center">
                    <?php foreach ($navLinks as $key => [$label, $path]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activeNav === $key ? ' active' : '' ?>" href="<?= href($path) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($me && $me['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link cb-admin-link<?= $activeNav === 'admin' ? ' active' : '' ?>" href="<?= href('admin/dashboard.php') ?>" title="Admin Dashboard"><i class="bi bi-shield-lock"></i> Admin</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex flex-column flex-xl-row align-items-stretch align-items-xl-center gap-2 cb-nav-actions">
                    <?php if ($me): ?>
                        <div class="dropdown">
                            <button class="btn cb-user-btn dropdown-toggle w-100 d-flex align-items-center gap-2" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="cb-avatar"><?= e(initials($me['full_name'])) ?></span>
                                <span class="text-start flex-grow-1 cb-user-text">
                                    <span class="d-block fw-semibold lh-1"><?= e(explode(' ', $me['full_name'])[0]) ?></span>
                                    <span class="d-block small text-muted lh-1 mt-1"><?= $me['role'] === 'admin' ? 'Administrator' : 'Student' ?></span>
                                </span>
                                <?php if ($unread > 0): ?><span class="cb-dot" title="<?= $unread ?> unread messages"></span><?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end cb-dropdown shadow">
                                <li class="px-3 py-2">
                                    <div class="fw-semibold"><?= e($me['full_name']) ?></div>
                                    <div class="small text-muted text-truncate"><?= e($me['email']) ?></div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($me['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item cb-admin-link" href="<?= href('admin/dashboard.php') ?>"><i class="bi bi-shield-lock"></i> Admin Dashboard</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?= href('profile.php') ?>"><i class="bi bi-person-circle"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="<?= href('my-listings.php') ?>"><i class="bi bi-bag"></i> My Listings</a></li>
                                <li><a class="dropdown-item" href="<?= href('my-resources.php') ?>"><i class="bi bi-journal-bookmark"></i> My Resources</a></li>
                                <li><a class="dropdown-item" href="<?= href('my-requests.php') ?>"><i class="bi bi-chat-dots"></i> My Requests</a></li>
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center" href="<?= href('messages.php') ?>">
                                        <span><i class="bi bi-envelope"></i> Messages</span>
                                        <?php if ($unread > 0): ?><span class="badge rounded-pill bg-danger"><?= $unread ?></span><?php endif; ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= href('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= href('login.php') ?>"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                        <a class="btn btn-primary" href="<?= href('register.php') ?>">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php if (!empty($adminSection)): ?>
        <div class="container pt-3">
            <ul class="nav nav-pills cb-admin-nav flex-wrap">
                <?php foreach (['dashboard' => ['Overview', 'admin/dashboard.php', 'bi-speedometer2'],
                    'users' => ['Users', 'admin/users.php', 'bi-people'],
                    'listings' => ['Listings', 'admin/listings.php', 'bi-bag'],
                    'resources' => ['Resources', 'admin/resources.php', 'bi-journal-bookmark'],
                    'requests' => ['Requests', 'admin/requests.php', 'bi-chat-dots'],
                    'reports' => ['Reports', 'admin/reports.php', 'bi-flag']] as $key => [$label, $path, $icon]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $adminSection === $key ? ' active' : '' ?>" href="<?= href($path) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</header>

<main id="main-content" class="flex-grow-1">
    <?php if (!empty($flashes)): ?>
        <div class="container pt-3 cb-flash-zone">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show d-flex align-items-start gap-2 mb-2"
                     role="alert" data-autohide="7000">
                    <i class="bi <?= e($flashIcons[$flash['type']] ?? 'bi-info-circle-fill') ?> fs-5 lh-1 mt-1"></i>
                    <div class="flex-grow-1"><?= e($flash['message']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($breadcrumbs)): ?>
        <div class="container pt-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb cb-breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= href('index.php') ?>">Home</a></li>
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i === array_key_last($breadcrumbs) || empty($crumb[1])): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= e($crumb[0]) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= href($crumb[1]) ?>"><?= e($crumb[0]) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
    <?php endif; ?>
