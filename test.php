<?php

include_once 'HTMLTable2JSON.php';
$helper = new HTMLTable2JSON();
$helper->tableToJSON('http://kdic.grinnell.edu/programs/schedule/', TRUE);
//$helper->tableToJSON('http://lightswitch05.github.io/table-to-json/', FALSE);
?>