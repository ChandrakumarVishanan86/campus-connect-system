<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'Lost & found'; $pageSub = 'Help things find their way home'; $activeNav = 'lost'; $libs = ['flatpickr']; $rail = true; $pageScripts = ['assets/js/lost.js'];
$pageActions = '<button class="btn btn-gradient" id="btnNew"><i class="bi bi-plus-lg me-1"></i>New report</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="chips flex-grow-1 mb-0" id="lfKind"><button class="chip on" data-kind="">All</button><button class="chip" data-kind="Lost">Lost</button><button class="chip" data-kind="Found">Found</button></div>
    <select class="form-select form-select-sm" id="lfStatus" style="width:auto" aria-label="Status"><option value="open">Still open</option><option value="mine">My reports &amp; claims</option><option value="Resolved">Resolved</option></select>
    <input class="form-control form-control-sm" id="lfSearch" style="width:170px" placeholder="Search items" aria-label="Search items">
</div>
<div class="row g-3" id="lfGrid"></div>
<div class="modal fade" id="lfModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="lfForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title">New report</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="row g-3">
        <div class="col-12"><div class="btn-group w-100" role="group" aria-label="Kind"><input type="radio" class="btn-check" name="kind" id="kLost" value="Lost" checked><label class="btn btn-outline-secondary" for="kLost"><i class="bi bi-search me-1"></i>I lost something</label><input type="radio" class="btn-check" name="kind" id="kFound" value="Found"><label class="btn btn-outline-secondary" for="kFound"><i class="bi bi-box-seam me-1"></i>I found something</label></div></div>
        <div class="col-12"><label class="form-label" for="lfTitle">Item *</label><input class="form-control" id="lfTitle" name="title" maxlength="100" placeholder="e.g. Blue water bottle"></div>
        <div class="col-md-7"><label class="form-label" for="lfLoc">Where *</label><input class="form-control" id="lfLoc" name="location" maxlength="100" placeholder="e.g. Library, 2nd floor"></div>
        <div class="col-md-5"><label class="form-label" for="lfDate">When *</label><input class="form-control" id="lfDate" name="happened_on" autocomplete="off"></div>
        <div class="col-12"><label class="form-label" for="lfDesc">Description</label><textarea class="form-control" id="lfDesc" name="description" rows="3" maxlength="400" placeholder="Colour, brand, marks. Keep one detail back to verify the owner."></textarea></div>
    </div><div class="text-danger small mt-2" id="lfErr" role="alert"></div></div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient">Post report</button></div>
</form></div></div>
<div class="modal fade" id="claimModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3">
    <h5 class="mb-1" id="clTitle">Claim this item</h5><p class="small text-muted" id="clHelp"></p><textarea class="form-control" id="clNote" rows="3" maxlength="300"></textarea><div class="text-danger small mt-1" id="clErr"></div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient flex-fill" id="clGo">Send</button></div>
</div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
