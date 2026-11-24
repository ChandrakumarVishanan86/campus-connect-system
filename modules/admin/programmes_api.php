<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
CrudController::handle(ProgrammeModel::class, ['read' => ['admin'], 'write' => ['admin'], 'label' => 'Programme']);
