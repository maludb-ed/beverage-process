<?php /** @var string $screen */ ?>
<div id="assistant-bar" class="assistant-bar">
    <div id="assistant-reply" class="assistant-reply" hidden></div>
    <form id="assistant-form" class="assistant-form" method="post" action="/assistant/message"
          hx-post="/assistant/message" hx-target="#assistant-reply" hx-swap="innerHTML"
          hx-vals='js:{screen: (document.getElementById("screen-context")||{dataset:{}}).dataset.screen, entity: (document.getElementById("screen-context")||{dataset:{}}).dataset.entity, record_id: (document.getElementById("screen-context")||{dataset:{}}).dataset.recordId}'
          hx-on::after-request="if(event.detail.successful){this.reset();document.getElementById('assistant-reply').hidden=false;document.getElementById('assistant-input').focus();}">
        <?= csrf_field() ?>
        <div class="input-group">
            <span class="input-group-text"><i class="feather-message-circle"></i></span>
            <input type="text" class="form-control" id="assistant-input" name="message" placeholder="Say what you want to do, or ask a question (Ctrl+K)" autocomplete="off" maxlength="2000" />
            <button type="submit" class="btn btn-primary" id="assistant-send-btn"><i class="feather-send"></i><span class="d-none d-sm-inline ms-2">Send</span></button>
            <button type="button" class="btn btn-light-brand" id="assistant-transcript-btn" data-bs-toggle="offcanvas" data-bs-target="#assistant-transcript" aria-controls="assistant-transcript" title="Conversation"><i class="feather-list"></i></button>
        </div>
    </form>
</div>
<div class="offcanvas offcanvas-end assistant-transcript" tabindex="-1" id="assistant-transcript" aria-labelledby="assistant-transcript-title">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="assistant-transcript-title">Conversation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="assistant-transcript-body" hx-get="/assistant/transcript" hx-trigger="show.bs.offcanvas from:#assistant-transcript" hx-swap="innerHTML">
        <p class="text-muted fs-12">Your recent exchanges with the assistant appear here.</p>
    </div>
</div>
