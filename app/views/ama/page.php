<?= view('shared/page-header.php', ['title' => 'Ask me anything', 'screen' => 'ama', 'crumbs' => ['Ask me anything' => null]]) ?>
<div class="main-content" id="ama-content">
    <div class="row"><div class="col-lg-12">
        <div class="card stretch stretch-full" id="ama-card">
            <div class="card-header"><h5 class="card-title">Conversation</h5></div>
            <div class="card-body" id="ama-thread">
                <p class="text-muted" id="ama-intro">Ask about records ("which lots expire this month?") or about what happened ("who released batch B-26-003?"). The assistant arrives with build phase 4; until then, questions typed here are kept in the activity log and answered with a placeholder.</p>
            </div>
            <div class="card-footer">
                <form id="ama-form" method="post" action="/assistant/message" hx-post="/assistant/message" hx-target="#ama-thread" hx-swap="beforeend" hx-vals='{"screen":"ama","source":"ama"}' hx-on::after-request="if(event.detail.successful){this.reset();}">
                    <?= csrf_field() ?>
                    <div class="input-group">
                        <input type="text" class="form-control" id="ama-field-question" name="message" placeholder="Ask a question" required maxlength="2000" autocomplete="off">
                        <button type="submit" class="btn btn-primary" id="ama-ask-btn"><i class="feather-send me-2"></i><span>Ask</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div></div>
</div>
