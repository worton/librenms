<?php

$version = trim(snmp_get($device, "swOpCodeVer.1", "-OQv", "ES3528MO-MIB"), '"');
$hardware = "Edge-Core " . trim(snmp_get($device, "swProdName.0", "-OQv", "ES3528MO-MIB"), '"');
