<?php

//
// This file contains small helper functions
//

function console_log($output) {    
  echo "<script>console.log(" . json_encode($output, JSON_HEX_TAG) . ");</script>";
}

?>