<?php
$c = new mysqli('localhost', 'root', '', 'footstepszs');
$r = $c->query('DESCRIBE keuangan');
while($row = $r->fetch_assoc()) echo $row['Field'] . "\n";
?>
