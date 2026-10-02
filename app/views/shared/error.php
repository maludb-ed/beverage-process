<?php /** @var string $message */ ?>
<div class="page-header" id="error-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Something is missing</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item">Error</li></ul>
    </div>
</div>
<div class="main-content" id="error-content">
    <div class="row"><div class="col-lg-12"><div class="card"><div class="card-body text-center py-5">
        <i class="feather-alert-circle fs-1 mb-4"></i>
        <p class="text-muted" id="error-message"><?= e($message) ?></p>
        <a href="/" class="btn btn-sm btn-primary" id="error-home-btn">Back to the dashboard</a>
    </div></div></div></div>
</div>
