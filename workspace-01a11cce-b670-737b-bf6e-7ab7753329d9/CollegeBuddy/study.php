<?php
/*
 * study.php - Study Hub: FREE notes, previous year papers and practical files.
 * Browsing is public. Opening or downloading a file needs login (resource.php / download.php).
 * Resources are never sold, so this page has no price or payment fields.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Study Hub';
$activeNav = 'study';
$breadcrumbs = [['Study Hub', '']];
$me = current_user();

$branches = array_column(xml_list('branches'), 'value');
$years = array_map('strval', array_column(xml_list('years'), 'value'));
$semesters = array_map('strval', array_column(xml_list('semesters'), 'value'));
$types = xml_list('resourceTypes');
$subjects = array_values(array_unique(array_column(xml_list('subjects'), 'name')));
sort($subjects);

// Read and check the filters
$q = trim(read_text('q', ''));
$branch = read_text('branch', '');
$year = read_text('year', '');
$semester = read_text('semester', '');
$subject = read_text('subject', '');
$type = read_text('type', '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

if (!in_array($branch, $branches, true)) {
    $branch = '';
}
if (!in_array($year, $years, true)) {
    $year = '';
}
if (!in_array($semester, $semesters, true)) {
    $semester = '';
}
if (!in_array($subject, $subjects, true)) {
    $subject = '';
}
if (!in_array($type, $types, true)) {
    $type = '';
}

// Build the WHERE clause with placeholders
$where = [];
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(r.title LIKE ? OR r.subject LIKE ?)';
    $tp .= 'ss';
    array_push($params, $like, $like);
}
if ($branch !== '') {
    $where[] = 'r.branch = ?';
    $tp .= 's';
    $params[] = $branch;
}
if ($year !== '') {
    $where[] = 'r.year = ?';
    $tp .= 'i';
    $params[] = (int) $year;
}
if ($semester !== '') {
    $where[] = 'r.semester = ?';
    $tp .= 'i';
    $params[] = (int) $semester;
}
if ($subject !== '') {
    $where[] = 'r.subject = ?';
    $tp .= 's';
    $params[] = $subject;
}
if ($type !== '') {
    $where[] = 'r.resource_type = ?';
    $tp .= 's';
    $params[] = $type;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db_count("SELECT COUNT(*) AS total FROM resources r $whereSql", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$resources = db_select(
    "SELECT r.*, u.full_name AS uploader_name
     FROM resources r
     JOIN users u ON u.id = r.user_id
     $whereSql
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, $offset])
);

$query = array_filter([
    'q' => $q, 'branch' => $branch, 'year' => $year, 'semester' => $semester, 'subject' => $subject, 'type' => $type,
], fn($v) => $v !== '');

require __DIR__ . '/includes/header.php';
render_page_head(
    'Free for every student',
    'Study Hub',
    'Notes, previous year papers, question banks and practical files shared by students. Resources are free and can never be sold.',
    '<a class="btn btn-cb-accent" href="' . href($me ? 'upload-resource.php' : 'login.php?next=' . urlencode('upload-resource.php')) . '"><i class="bi bi-cloud-arrow-up"></i> Upload a resource</a>'
);
?>

<section class="container py-4">
    <form method="get" action="" class="card cb-card cb-filter-card mb-4" id="studyFilters" novalidate data-validate data-autosubmit>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center d-lg-none mb-3">
                <strong><i class="bi bi-funnel"></i> Filter resources</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#studyFilterBody" aria-expanded="false" aria-controls="studyFilterBody">Show filters</button>
            </div>
            <div class="collapse d-lg-block" id="studyFilterBody">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold" for="q">Search by title or subject</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="e.g. DBMS, Notes, Unit 3">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="branch">Branch</label>
                        <select class="form-select" id="branch" name="branch">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= e($b) ?>"<?= $branch === $b ? ' selected' : '' ?>><?= e($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="year">Year</label>
                        <select class="form-select" id="year" name="year">
                            <option value="">All years</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= e($y) ?>"<?= $year === $y ? ' selected' : '' ?>>Year <?= e($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="semester">Semester</label>
                        <select class="form-select" id="semester" name="semester">
                            <option value="">All semesters</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= e($s) ?>"<?= $semester === $s ? ' selected' : '' ?>>Semester <?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="type">Resource type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All types</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label small fw-semibold" for="subject">Subject</label>
                        <select class="form-select" id="subject" name="subject">
                            <option value="">All subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= e($s) ?>"<?= $subject === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 d-flex gap-2 align-items-end">
                        <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Apply filters</button>
                        <a class="btn btn-outline-secondary" href="<?= href('study.php') ?>">Clear</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($total > 0): ?>
        <p class="small text-muted mb-3">Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $total) ?> of <?= plural($total, 'resource') ?></p>
    <?php endif; ?>

    <?php if (!$resources): ?>
        <?php render_empty('bi-journal-x', 'No resources match these filters.', 'Try a different branch, semester or subject, or upload the notes you already have to help your juniors.',
            '<a class="btn btn-outline-primary" href="' . href('study.php') . '">Clear filters</a>'
            . '<a class="btn btn-primary" href="' . href($me ? 'upload-resource.php' : 'login.php') . '">Upload a resource</a>'); ?>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($resources as $r): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card cb-card cb-card-hover cb-resource h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="cb-resource-icon"><i class="bi <?= e(resource_icon($r['resource_type'])) ?>"></i></span>
                                <div class="min-w-0">
                                    <span class="badge cb-badge-soft mb-1"><?= e($r['resource_type']) ?></span>
                                    <h2 class="h6 cb-title mb-0"><a href="<?= href('resource.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a></h2>
                                </div>
                            </div>
                            <p class="small mb-2"><i class="bi bi-book text-muted"></i> <?= e($r['subject']) ?></p>
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <span class="badge bg-light text-body border"><?= e($r['branch']) ?></span>
                                <span class="badge bg-light text-body border">Year <?= (int) $r['year'] ?></span>
                                <span class="badge bg-light text-body border">Sem <?= (int) $r['semester'] ?></span>
                                <span class="badge bg-light text-body border text-uppercase"><?= e($r['file_ext']) ?> &middot; <?= e(format_bytes((int) $r['file_size'])) ?></span>
                            </div>
                            <ul class="cb-meta list-unstyled small text-muted mb-3">
                                <li><i class="bi bi-person"></i> Uploaded by <?= e($r['uploader_name']) ?></li>
                                <li><i class="bi bi-calendar3"></i> <?= e(format_date($r['created_at'])) ?> &middot; <?= (int) $r['download_count'] ?> downloads</li>
                            </ul>
                            <a class="btn btn-primary btn-sm mt-auto" href="<?= href('resource.php?id=' . (int) $r['id']) ?>"><i class="bi bi-download"></i> View / Download</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php render_pagination($page, $pages, 'study.php', $query); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
