<?php 
   header('Content-Type: audio/mpeg');
   header('Content-length: ' . filesize($_GET['media']));
   print file_get_contents($_GET['media']);
?>
