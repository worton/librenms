<?php

echo 'RFC1628 ';

$oids = snmp_walk($device, '.1.3.6.1.2.1.33.1.2.5', '-Osqn', 'UPS-MIB');
d_echo($oids."\n");

$oids = trim($oids);
foreach (explode("\n", $oids) as $data) {
    $data = trim($data);
    if ($data) {
        list($oid,$descr) = explode(' ', $data, 2);
        $split_oid        = explode('.', $oid);
        $volt_id          = $split_oid[(count($split_oid) - 1)];
        $volt_oid         = ".1.3.6.1.2.1.33.1.2.5.$volt_id";
        $divisor = get_device_divisor($device, $pre_cache['poweralert_serial'], 'voltage', $volt_oid);

        $volt  = (snmp_get($device, $volt_oid, '-O vq') / $divisor);
        $descr = 'Battery'.(count(explode("\n", $oids)) == 1 ? '' : ' '.($volt_id + 1));
        $type  = 'rfc1628';
        $index = '1.2.5.'.$volt_id;

        discover_sensor($valid['sensor'], 'voltage', $device, $volt_oid, $index, $type, $descr, $divisor, '1', null, null, null, null, $volt);
    }
}

$oids = trim(snmp_walk($device, '.1.3.6.1.2.1.33.1.4.3.0', '-OsqnU'));
d_echo($oids."\n");

list($unused,$numPhase) = explode(' ', $oids);
for ($i = 1; $i <= $numPhase; $i++) {
    $volt_oid = ".1.3.6.1.2.1.33.1.4.4.1.2.$i";
    $descr    = 'Output';
    if ($numPhase > 1) {
        $descr .= " Phase $i";
    }

    $type    = 'rfc1628';
    $divisor = get_device_divisor($device, $pre_cache['poweralert_serial'], 'voltage', $volt_oid);

    $current = (snmp_get($device, $volt_oid, '-Oqv') / $divisor);
    $index   = $i;

    discover_sensor($valid['sensor'], 'voltage', $device, $volt_oid, $index, $type, $descr, $divisor, '1', null, null, null, null, $current);
}

$oids = trim(snmp_walk($device, '.1.3.6.1.2.1.33.1.3.2.0', '-OsqnU'));
d_echo($oids."\n");

list($unused,$numPhase) = explode(' ', $oids);
for ($i = 1; $i <= $numPhase; $i++) {
    $volt_oid = ".1.3.6.1.2.1.33.1.3.3.1.3.$i";
    $descr    = 'Input';
    if ($numPhase > 1) {
        $descr .= " Phase $i";
    }

    $type    = 'rfc1628';
    $divisor = get_device_divisor($device, $pre_cache['poweralert_serial'], 'voltage', $volt_oid);

    $current = (snmp_get($device, $volt_oid, '-Oqv') / $divisor);

    $index   = (100 + $i);

    discover_sensor($valid['sensor'], 'voltage', $device, $volt_oid, $index, $type, $descr, $divisor, '1', null, null, null, null, $current);
}

$oids = trim(snmp_walk($device, '.1.3.6.1.2.1.33.1.5.2.0', '-OsqnU'));
d_echo($oids."\n");

list($unused,$numPhase) = explode(' ', $oids);
for ($i = 1; $i <= $numPhase; $i++) {
    $volt_oid = ".1.3.6.1.2.1.33.1.5.3.1.2.$i";
    $descr    = 'Bypass';
    if ($numPhase > 1) {
        $descr .= " Phase $i";
    }

    $type    = 'rfc1628';
    $divisor = get_device_divisor($device, $pre_cache['poweralert_serial'], 'voltage', $volt_oid);

    $current = (snmp_get($device, $volt_oid, '-Oqv') / $divisor);
    $index   = (200 + $i);

    discover_sensor($valid['sensor'], 'voltage', $device, $volt_oid, $index, $type, $descr, $divisor, '1', null, null, null, null, $current);
}
