<?php
/*
 * voltages for raspberry pi
 * requires snmp extend agent script from librenms-agent
 */
$raspberry = snmp_get($device, 'HOST-RESOURCES-MIB::hrSystemInitialLoadParameters.0', '-Osqnv');
if (preg_match("/(bcm).+(boardrev)/", $raspberry)) {
    $sensor_type = "rasbperry_volts";
    $oid = '.1.3.6.1.4.1.8072.1.3.2.4.1.2.9.114.97.115.112.98.101.114.114.121.';
    for ($volt = 2; $volt < 6; $volt++) {
        switch ($volt) {
            case "2":
                $descr = "Core";
                break;
            case "3":
                $descr = "SDRAMc";
                break;
            case "4":
                $descr = "SDRAMi";
                break;
            case "5":
                $descr = "SDRAMp";
                break;
        }
        $value = snmp_get($device, $oid.$volt, '-Oqv');
        if (is_numeric($value)) {
            discover_sensor($valid['sensor'], 'voltage', $device, $oid.$volt, $volt, $sensor_type, $descr, '1', '1', null, null, null, null, $value);
        }
    }
    /*
     * other linux os
     */
}

$oids = snmp_walk($device, '.1.3.6.1.4.1.10876.2.1.1.1.1.3', '-OsqnU', 'SUPERMICRO-HEALTH-MIB');
d_echo($oids."\n");

$oids = trim($oids);
if ($oids) {
    echo 'Supermicro ';
}

$type    = 'supermicro';
$divisor = '1000';
foreach (explode("\n", $oids) as $data) {
    $data = trim($data);
    if ($data) {
        list($oid,$kind) = explode(' ', $data);
        $split_oid       = explode('.', $oid);
        $index           = $split_oid[(count($split_oid) - 1)];
        if ($kind == 1) {
            $volt_oid     = '.1.3.6.1.4.1.10876.2.1.1.1.1.4.'.$index;
            $descr_oid    = '.1.3.6.1.4.1.10876.2.1.1.1.1.2.'.$index;
            $monitor_oid  = '.1.3.6.1.4.1.10876.2.1.1.1.1.10.'.$index;
            $limit_oid    = '.1.3.6.1.4.1.10876.2.1.1.1.1.5.'.$index;
            $lowlimit_oid = '.1.3.6.1.4.1.10876.2.1.1.1.1.6.'.$index;

            $descr    = snmp_get($device, $descr_oid, '-Oqv', 'SUPERMICRO-HEALTH-MIB');
            $current  = (snmp_get($device, $volt_oid, '-Oqv', 'SUPERMICRO-HEALTH-MIB') / $divisor);
            $limit    = (snmp_get($device, $limit_oid, '-Oqv', 'SUPERMICRO-HEALTH-MIB') / $divisor);
            $lowlimit = (snmp_get($device, $lowlimit_oid, '-Oqv', 'SUPERMICRO-HEALTH-MIB') / $divisor);
            $monitor  = snmp_get($device, $monitor_oid, '-Oqv', 'SUPERMICRO-HEALTH-MIB');
            $descr    = trim(str_ireplace('Voltage', '', $descr));

            if ($monitor == 'true') {
                discover_sensor($valid['sensor'], 'voltage', $device, $volt_oid, $index, $type, $descr, $divisor, '1', $lowlimit, null, null, $limit, $current);
            }
        }
    }//end if
}
