<div class="container" style="margin-top: 20px; margin-bottom: 40px;">

    <h1><?= htmlspecialchars($pageTitle) ?></h1>

    <?php if (!empty($pageDescription)): ?>
        <p class="text-muted"><?= htmlspecialchars($pageDescription) ?></p>
        <hr>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash'])): ?>
        <?php
        $type = $_SESSION['flash']['type'];
        // Bootstrap 3 : success, info, warning, danger
        if ($type === 'error') {
            $type = 'danger';
        }
        ?>
        <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
