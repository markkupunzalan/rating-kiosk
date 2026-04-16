<?php
$c = new mysqli('127.0.0.1', 'root', '', 'feedbackiq');
$r=$c->query("select * from settings where setting_key='logo_url'")->fetch_assoc();
print_r($r);
