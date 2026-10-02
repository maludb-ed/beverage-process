<?php /** @var array $rows  activity rows (oldest first) of this user's assistant exchanges */ ?>
<?= view('shared/page-header.php', ['title' => 'Ask me anything', 'screen' => 'ama', 'crumbs' => ['Ask me anything' => null]]) ?>
<div class="main-content" id="ama-content">
    <div class="row"><div class="col-lg-12">
        <div class="card" id="ama-card">
            <div class="card-header"><h5 class="card-title">Conversation</h5></div>
            <div class="card-body" id="ama-thread">
                <p class="text-muted" id="ama-intro">Ask about records ("which lots expire this month?", "what is in every tank?") or about what happened ("who released batch B-26-003?", "what did the assistant do for me yesterday?"). Answers say which memory they came from. You can also ask it to do things, as in the command bar.</p>
                <?php foreach ($rows as $row): ?>
                    <?php $d = $row['details']; ?>
                    <?= view('ama/exchange.php', [
                        'message' => (string) ($d['message'] ?? ''), 'reply' => (string) ($d['reply'] ?? ''),
                        'sources' => is_array($d['sources'] ?? null) ? $d['sources'] : [],
                        'surface' => (string) ($d['surface'] ?? ($row['action'] === 'ama_question' ? 'ama' : 'command_bar')),
                        'activityId' => (int) $row['id'], 'occurredAt' => $row['occurred_at'],
                    ]) ?>
                <?php endforeach; ?>
            </div>
            <div class="card-footer">
                <form id="ama-form" method="post" action="/assistant/message" hx-post="/assistant/message" hx-target="#ama-thread" hx-swap="beforeend"
                      hx-vals='{"screen":"ama","source":"ama"}' hx-indicator="#ama-indicator" hx-disabled-elt="#ama-ask-btn"
                      hx-on::after-request="if(event.detail.successful){this.reset();document.getElementById('ama-field-question').focus();var t=document.getElementById('ama-thread').lastElementChild;if(t){t.scrollIntoView({block:'start',behavior:'smooth'});}}">
                    <?= csrf_field() ?>
                    <input type="hidden" name="source" value="ama">
                    <div class="input-group">
                        <input type="text" class="form-control" id="ama-field-question" name="message" placeholder="Ask a question" required maxlength="2000" autocomplete="off">
                        <button type="submit" class="btn btn-primary" id="ama-ask-btn"><i class="feather-send me-2"></i><span>Ask</span></button>
                    </div>
                    <div class="fs-12 text-muted mt-2 htmx-indicator" id="ama-indicator"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Looking through records and activity…</div>
                </form>
            </div>
        </div>
    </div></div>
</div>
