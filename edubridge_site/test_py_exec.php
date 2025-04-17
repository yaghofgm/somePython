<?php
$cmd = "echo '{\"student_id\": 4}' | /usr/bin/python3 site_calculateLoan_v3_FINAL.py";
$output = shell_exec($cmd);
echo "<pre>Output direto:\n" . htmlspecialchars($output) . "</pre>";
