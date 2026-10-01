<?php /** @var array $settings  @var array $errors  @var PDO $pdo */
$p = 'settings-client';
$volumeUnits = settings_unit_options($pdo, 'volume', true);
$massUnits = settings_unit_options($pdo, 'mass');
$zones = array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers());
?>
<form id="settings-client" method="post" action="/settings/client/save" hx-post="/settings/client/save" hx-target="#page-content" hx-swap="innerHTML">
    <?= csrf_field() ?>
    <div class="row"><div class="col-lg-12">
        <div class="card stretch stretch-full" id="settings-client-card">
            <div class="card-body">
                <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Client</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Quantities are stored in metric base units and shown in the display units chosen here.</span></h5></div>
                <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'settings-client-errors']) ?>
                <?= form_input($p, 'client_name', 'Client name', $settings['client_name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-briefcase', 'autofocus' => true]) ?>
                <?= form_input($p, 'subdomain', 'Subdomain', $settings['subdomain'] ?? '', $errors, ['readonly' => true, 'icon' => 'feather-globe', 'help' => 'Set when the account was provisioned.']) ?>
                <?= form_select($p, 'timezone', 'Time zone', $zones, $settings['timezone'] ?? '', $errors, ['required' => true]) ?>
                <?= form_select($p, 'volume_display_unit', 'Volume display unit', $volumeUnits, $settings['volume_display_unit'] ?? '', $errors, ['required' => true]) ?>
                <?= form_select($p, 'mass_display_unit', 'Mass display unit', $massUnits, $settings['mass_display_unit'] ?? '', $errors, ['required' => true]) ?>
                <?= form_select($p, 'fruit_display_unit', 'Fruit weight display unit', $massUnits, $settings['fruit_display_unit'] ?? '', $errors, ['required' => true, 'last' => true]) ?>
            </div>
        </div>
    </div></div>
</form>
