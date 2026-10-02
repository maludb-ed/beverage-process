<?php /** @var array $input  @var array $errors  @var array $customers  @var array $results  @var bool $hasLocation */
$p = 'keg-return-form';
?>
<?= view('shared/page-header.php', ['title' => 'Return kegs', 'screen' => 'keg-return', 'crumbs' => ['Packaging' => null, 'Kegs' => '/kegs/', 'Return' => null], 'actionsHtml' => form_actions('keg-return-form', '/kegs/', 'Return Kegs')]) ?>
<div class="main-content" id="keg-return-content">
    <form id="keg-return-form" method="post" action="/kegs/return" hx-post="/kegs/return" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="keg-return-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Returned kegs</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">One serial per line. Each returned keg becomes dirty and goes back to the packaged goods location.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'keg-return-form-errors']) ?>
                    <?php if (!$hasLocation): ?><div class="alert alert-warning" id="keg-return-form-no-location">No packaged goods location exists, so kegs cannot be returned yet. Add one under Locations.</div><?php endif; ?>
                    <?= form_textarea($p, 'serials', 'Serials', $input['serials'] ?? '', $errors, ['rows' => 8, 'placeholder' => "KEG-0001\nKEG-0002"]) ?>
                    <?= form_select($p, 'customer_id', 'Returned by', $customers, $input['customer_id'] ?? '', $errors, ['blank' => 'No customer', 'last' => true]) ?>
                </div>
            </div>
            <?php if ($results !== []): ?>
            <div class="card" id="keg-return-results-card">
                <div class="card-header"><h5 class="card-title">Result</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="keg-return-results-table">
                        <thead class="thead-light"><tr><th>Serial</th><th>Result</th><th>Detail</th></tr></thead>
                        <tbody>
                        <?php foreach ($results as $n => $row): ?>
                            <tr id="keg-return-result-row-<?= e($n + 1) ?>">
                                <td><?php if ($row['keg_id']): ?><a <?= nav_attrs('/kegs/' . (int) $row['keg_id']) ?>><?= e($row['serial']) ?></a><?php else: ?><?= e($row['serial']) ?><?php endif; ?></td>
                                <td><?= badge($row['result'] === 'returned' ? 'Returned' : humanize($row['result']), $row['result'] === 'returned' ? 'success' : 'danger') ?></td>
                                <td><?= e($row['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div></div>
    </form>
</div>
