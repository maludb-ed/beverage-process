<?php /** @var array $vessels  @var array $query  @var bool $canArrange
 * The floor: a dotted canvas on which every vessel card sits at its grid position (db/022). Dragging a card's title
 * moves it and saves the position (POST /tanks/{id}/position); the canvas scrolls sideways on a phone.
 */
$refresh = '/tanks/' . query_string(['premises_id' => $query['premises_id'] ?? null, 'kind' => $query['kind'] ?? null]);
$grid = TANK_VIEW_GRID;
$maxX = 0; $maxY = 0;
foreach ($vessels as $v) { $maxX = max($maxX, (int) $v['board_x']); $maxY = max($maxY, (int) $v['board_y']); }
$width = ($maxX + TANK_VIEW_CARD_W + 2) * $grid;
$height = ($maxY + TANK_VIEW_CARD_H + 2) * $grid;
?>
<div id="tank-view-results" hx-get="<?= e($refresh) ?>" hx-trigger="batchesChanged from:body, vesselChanged from:body, inventoryChanged from:body" hx-target="#tank-view-results" hx-swap="outerHTML">
    <?php if ($vessels === []): ?>
    <div class="card" id="tank-view-empty"><div class="card-body text-center py-5 text-muted"><i class="feather-inbox fs-1 d-block mb-3"></i>No vessels match.</div></div>
    <?php else: ?>
    <p class="fs-12 text-muted mb-2" id="tank-view-hint"><?= $canArrange ? 'Drag a tank by its name to arrange the floor; positions save as you drop. ' : '' ?>Open a tank for its batch or lot.</p>
    <div class="tank-view-scroll" id="tank-view-scroll">
        <div class="tank-view-canvas" id="tank-view-canvas" data-grid="<?= $grid ?>" data-arrange="<?= $canArrange ? '1' : '0' ?>" data-csrf="<?= e(csrf_token()) ?>"
             style="width: <?= $width ?>px; min-height: <?= $height ?>px;">
            <?php foreach ($vessels as $v): ?><?= view('tanks/partials/card.php', ['v' => $v, 'grid' => $grid, 'canArrange' => $canArrange]) ?><?php endforeach; ?>
        </div>
    </div>
    <script>
    (function () {
        var canvas = document.getElementById('tank-view-canvas');
        if (!canvas || canvas.dataset.arrange !== '1' || canvas.dataset.bound) { return; }
        canvas.dataset.bound = '1';
        var grid = parseInt(canvas.dataset.grid, 10) || 20, drag = null;
        canvas.addEventListener('pointerdown', function (ev) {
            var handle = ev.target.closest('.tank-view-handle'); if (!handle || ev.button) { return; }
            var card = handle.closest('.tank-view-card'); var rect = canvas.getBoundingClientRect();
            drag = { card: card, dx: ev.clientX - rect.left - card.offsetLeft, dy: ev.clientY - rect.top - card.offsetTop, moved: false };
            card.classList.add('tank-view-dragging'); handle.setPointerCapture(ev.pointerId); ev.preventDefault();
        });
        canvas.addEventListener('pointermove', function (ev) {
            if (!drag) { return; }
            var rect = canvas.getBoundingClientRect();
            var x = Math.max(0, ev.clientX - rect.left - drag.dx), y = Math.max(0, ev.clientY - rect.top - drag.dy);
            drag.card.style.left = x + 'px'; drag.card.style.top = y + 'px'; drag.moved = true;
        });
        function drop(ev) {
            if (!drag) { return; }
            var card = drag.card; card.classList.remove('tank-view-dragging');
            var gx = Math.round(card.offsetLeft / grid), gy = Math.round(card.offsetTop / grid);
            card.style.left = (gx * grid) + 'px'; card.style.top = (gy * grid) + 'px';
            // The canvas grows with the furthest card so nothing is dropped off the edge.
            canvas.style.width = Math.max(canvas.offsetWidth, card.offsetLeft + card.offsetWidth + 2 * grid) + 'px';
            canvas.style.minHeight = Math.max(canvas.offsetHeight, card.offsetTop + card.offsetHeight + 2 * grid) + 'px';
            var moved = drag.moved; drag = null;
            if (!moved) { return; }
            var body = new FormData(); body.append('csrf_token', canvas.dataset.csrf); body.append('x', gx); body.append('y', gy);
            fetch('/tanks/' + card.dataset.vesselId + '/position', { method: 'POST', body: body, headers: { 'HX-Request': 'true' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { card.classList.add('tank-view-unsaved'); } })
                .catch(function () { card.classList.add('tank-view-unsaved'); });
        }
        canvas.addEventListener('pointerup', drop);
        canvas.addEventListener('pointercancel', drop);
    })();
    </script>
    <?php endif; ?>
</div>
