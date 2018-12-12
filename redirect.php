<?php
@header('Content-type: text/html;charset=UTF-8');
$contents= base64_decode(gzinflate($_GET["res"]));
$content=html_entity_decode($contents);
echo $content;
?>
