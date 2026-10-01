<?php /** @var array $settings  @var array $errors  @var PDO $pdo */
$actions = form_actions('settings-client', '/', 'Save Settings');
?>
<?= view('shared/page-header.php', ['title' => 'Client settings', 'screen' => 'settings-client', 'crumbs' => ['Settings' => null, 'Client settings' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="settings-client-content">
    <?= view('settings/partials/client-form.php', ['settings' => $settings, 'errors' => $errors, 'pdo' => $pdo]) ?>
</div>
