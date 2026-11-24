<?php
$tabs = ['moderation' => ['Moderation', 'bi-shield-check'], 'insights' => ['Insights', 'bi-graph-up-arrow'], 'users' => ['Users', 'bi-person-gear'], 'clubs' => ['Clubs', 'bi-collection'], 'programmes' => ['Programmes', 'bi-mortarboard'], 'audit' => ['Audit log', 'bi-journal-text']];
echo '<ul class="nav nav-pills mb-3 gap-1 flex-wrap">';
foreach ($tabs as $k => [$l, $i]) { echo '<li class="nav-item"><a class="nav-link ' . ($adminTab === $k ? 'active' : '') . '" href="' . url('admin/' . $k) . '"><i class="bi ' . $i . ' me-1"></i>' . $l . '</a></li>'; }
echo '</ul>';
