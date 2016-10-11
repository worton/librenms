<?php
/*
 * LibreNMS QNAP NAS temperature information module
 *
 * Copyright (c) 2016 Cercel Valentin <crc@nuamchefazi.ro>
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

if ($device['os'] == 'qnap') {
    echo 'QNAP: ';

    $system_temperature_oid = '.1.3.6.1.4.1.24681.1.3.6.0';
    $system_temperature = snmp_get($device, $system_temperature_oid, '-Oqv');
    discover_sensor($valid['sensor'], 'temperature', $device, $system_temperature_oid, '99', 'snmp', 'System Temperature', '1', '1', null, null, null, null, $system_temperature);

    $temps_oid = '24681.1.2.11.1.3.';
    $serials_oid = '24681.1.2.11.1.5.';

    $disk_temperature_oid = '.1.3.6.1.4.1.24681.1.2.11.1.3';
    $disk_serial_oid = '1.3.6.1.4.1.24681.1.2.11.1.5';

    $hdd_temps = snmpwalk_cache_multi_oid($device, $disk_temperature_oid, array());
    $hdd_serials = snmpwalk_cache_multi_oid($device, $disk_serial_oid, array());

    if (is_array($hdd_temps) && !empty($hdd_temps)) {
        foreach ($hdd_temps as $index => $entry) {
            $index = str_replace($temps_oid, '', $index);
            $disk_temperature = $entry['enterprises'];
            $disk_serial = str_replace('"', '', $hdd_serials[$serials_oid . $index]['enterprises']);

            if ($disk_serial == '--') {
                $disk_descr = "HDD $index empty bay";
            } else {
                $disk_descr = "HDD $index $disk_serial";
            }

            discover_sensor($valid['sensor'], 'temperature', $device, $disk_temperature_oid . '.' . $index, $index, 'snmp', $disk_descr, '1', '1', null, null, null, null, $disk_temperature);
        }
    }
}
