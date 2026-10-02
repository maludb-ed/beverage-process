<?php /** @var array $removal  @var array $lines  @var array $errors  @var array $lineErrors  @var array $customers  @var array $fromLocations  @var array $toLocations  @var array $options */
$id = $removal['id'] ?? null;
$isEdit = $id !== null;
$direction = $removal['direction'];
$isReturn = $direction === 'in';
$dest = $removal['destination_kind'];
$noun = $isReturn ? 'Return' : 'Removal';
$title = $isEdit ? 'Edit ' . ($removal['number'] ?? $noun) : 'Add ' . $noun;
$cancelUrl = $isEdit ? '/removals/' . $id : '/removals/';
$formUrl = $isEdit ? '/removals/' . $id . '/edit' : '/removals/new';
$p = 'removal-form';
$refresh = static fn(string $changed): string => ' hx-get="' . e($formUrl) . '" hx-trigger="change" hx-target="#page-content" hx-swap="innerHTML" hx-include="#removal-form" hx-vals=\'{"refresh": "1", "changed": "' . e($changed) . '"}\'';
$showCustomer = $isReturn || in_array($dest, REMOVAL_CUSTOMER_REQUIRED, true);
$showTo = $isReturn || $dest === 'taproom_transfer';
$sourceReady = $isReturn ? !empty($removal['customer_id']) : !empty($removal['from_location_id']);
$lineInclude = '#removal-form-field-direction, #removal-form-field-from-location-id, #removal-form-field-customer-id';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'removal-form', 'crumbs' => ['Compliance' => null, 'Removals' => '/removals/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('removal-form', $cancelUrl, 'Save ' . $noun)]) ?>
<div class="main-content" id="removal-form-content">
    <form id="removal-form" method="post" action="/removals/save" hx-post="/removals/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <input type="hidden" name="direction" id="removal-form-field-direction" value="<?= e($direction) ?>" />
        <div class="row"><div class="col-lg-12">
            <div class="card" id="removal-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2"><?= $isReturn ? 'Goods coming back' : 'Goods leaving' ?></span><span class="fs-12 fw-normal text-muted text-truncate-1-line"><?= $isReturn ? 'Save as a draft, then post to return the units to bond.' : 'Save as a draft, then post to move the stock and determine tax.' ?></span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'removal-form-errors']) ?>
                    <?= form_select($p, 'destination_kind', 'Destination', $isReturn ? REMOVAL_IN_DESTINATIONS : REMOVAL_OUT_DESTINATIONS, $dest, $errors, ['required' => true, 'extra' => $isReturn ? '' : $refresh('destination'),
                        'help' => removal_determines_tax($direction, $dest) ? 'Tax is determined when this removal posts.' : ($isReturn ? 'Tax-paid goods returned to bond.' : 'No tax is determined for this destination.')]) ?>
                    <?php if ($showCustomer): ?>
                        <?= form_select($p, 'customer_id', 'Customer', $customers, $removal['customer_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a customer', 'extra' => $refresh('customer'),
                            'help' => $isReturn ? 'Lots ever removed to this customer can come back.' : 'Choosing a customer sets the destination to their default.']) ?>
                    <?php endif; ?>
                    <?php if (!$isReturn): ?>
                        <?php if ($fromLocations === []): ?>
                            <?= form_static($p, 'from_location_id', 'From', '<span class="text-warning" id="removal-form-no-locations">No bonded packaged-goods or taproom location exists. Add one under Locations.</span>') ?>
                        <?php else: ?>
                            <?= form_select($p, 'from_location_id', 'From', $fromLocations, $removal['from_location_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a bonded location', 'extra' => $refresh('from'),
                                'help' => 'Bonded locations only; moving to the taproom is a taproom transfer.']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($showTo): ?>
                        <?= form_select($p, 'to_location_id', $isReturn ? 'Return to' : 'Taproom', $toLocations, $removal['to_location_id'] ?? '', $errors, ['required' => true, 'blank' => $toLocations === [] ? ($isReturn ? 'No bonded packaged-goods location exists' : 'No tax-paid taproom location exists') : 'Choose a location',
                            'help' => $isReturn ? 'A bonded packaged-goods location.' : 'The tax-paid taproom receiving the goods.']) ?>
                    <?php endif; ?>
                    <?= form_input($p, 'removed_at', $isReturn ? 'Returned at' : 'Removed at', $removal['removed_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_input($p, 'reference', 'Reference', $removal['reference'] ?? '', $errors, ['maxlength' => 80, 'icon' => 'feather-file-text', 'placeholder' => 'Invoice or bill of lading']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $removal['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card border-top-0" id="removal-form-lines-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="removal-form-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="removal-form-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#removal-form-pane-lines" role="tab">Lines</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="removal-form-tab-tax" class="nav-link" data-bs-toggle="tab" data-bs-target="#removal-form-pane-tax" role="tab">Tax determination</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="removal-form-pane-lines" role="tabpanel">
                        <div class="card-body">
                            <?php if (!$sourceReady): ?>
                                <p class="text-muted fs-12" id="removal-form-lines-hint"><?= $isReturn ? 'Choose the customer to see the lots they received.' : 'Choose where the goods leave from to see its finished lots.' ?></p>
                            <?php elseif ($options['lots'] === []): ?>
                                <div class="alert alert-info" id="removal-form-lines-empty"><?= $isReturn ? 'Nothing has been removed to this customer yet, so there is nothing to return.' : 'No finished lots with available units at this location. Finished lots appear here once a packaging run posts.' ?></div>
                            <?php endif; ?>
                            <div id="removal-form-lines">
                                <?php foreach ($lines as $n => $line): ?>
                                    <?= view('removals/partials/line-row.php', ['n' => $n, 'line' => $line, 'options' => $options, 'direction' => $direction, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light-brand" id="removal-form-add-line-btn"
                                    hx-get="/removals/line-row" hx-target="#removal-form-lines" hx-swap="beforeend"
                                    hx-include="<?= e($lineInclude) ?>" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="removal-form-pane-tax" role="tabpanel">
                        <div class="card-body" id="removal-form-tax-preview" hx-get="/removals/tax-preview" hx-trigger="load, change from:#removal-form delay:300ms" hx-include="#removal-form" hx-target="this" hx-swap="innerHTML">
                            <p class="text-muted fs-12 mb-0">Calculating…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div></div>
    </form>
</div>
