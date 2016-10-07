<?php

unset($mac_table);

if (key_exists('vrf_lite_cisco', $device) && (count($device['vrf_lite_cisco'])!=0)) {
    $vrfs_lite_cisco = $device['vrf_lite_cisco'];
} else {
    $vrfs_lite_cisco = array(array('context_name'=>null));
}

foreach ($vrfs_lite_cisco as $vrf) {
    $device['context_name']=$vrf['context_name'];

    $arp_oid = 'ipNetToPhysicalPhysAddress';
    $ipNetToPhysical_data = snmp_walk($device, $arp_oid, '-Oq', 'IP-MIB');
    if (empty($ipNetToPhysical_data)) {
        $arp_oid = 'ipNetToMediaPhysAddress';
        $ipNetToPhysical_data = snmp_walk($device, $arp_oid, '-Oq', 'IP-MIB');
    }
    $ipNetToPhysical_data = str_replace('IP-MIB::'.$arp_oid.'.', '', trim($ipNetToPhysical_data));
    $ipNetToPhysical_data = str_replace('"', '', trim($ipNetToPhysical_data));
    foreach (explode("\n", $ipNetToPhysical_data) as $data) {
        list($oid, $mac) = explode(' ', $data);
        if ($arp_oid == 'ipNetToPhysicalPhysAddress') {
            list($if, $ipv, $first, $second, $third, $fourth) = explode('.', $oid);
        } else {
            $ipv = 'ipv4';
            list($if, $first, $second, $third, $fourth) = explode('.', $oid);
        }
        $ip = $first.'.'.$second.'.'.$third.'.'.$fourth;
        if ($ip != '...' && $ipv === 'ipv4' && $mac != '0:0:0:0:0:0') {
            $interface = dbFetchRow('SELECT * FROM `ports` WHERE `device_id` = ? AND `ifIndex` = ?', array($device['device_id'], $if));

            list($m_a, $m_b, $m_c, $m_d, $m_e, $m_f) = explode(':', $mac);
            $m_a  = zeropad($m_a);
            $m_b  = zeropad($m_b);
            $m_c  = zeropad($m_c);
            $m_d  = zeropad($m_d);
            $m_e  = zeropad($m_e);
            $m_f  = zeropad($m_f);
            $md_a = hexdec($m_a);
            $md_b = hexdec($m_b);
            $md_c = hexdec($m_c);
            $md_d = hexdec($m_d);
            $md_e = hexdec($m_e);
            $md_f = hexdec($m_f);
            $mac  = "$m_a:$m_b:$m_c:$m_d:$m_e:$m_f";

            $mac_table[$if][$mac]['ip']       = $ip;
            $mac_table[$if][$mac]['ciscomac'] = "$m_a$m_b.$m_c$m_d.$m_e$m_f";
            $clean_mac = $m_a.$m_b.$m_c.$m_d.$m_e.$m_f;
            $mac_table[$if][$mac]['cleanmac'] = $clean_mac;
            $port_id = $interface['port_id'];
            $mac_table[$port_id][$clean_mac][$ip] = 1;

            if (dbFetchCell('SELECT COUNT(*) from ipv4_mac WHERE port_id = ? AND ipv4_address = ?', array($interface['port_id'], $ip))) {
                // Commented below, no longer needed but leaving for reference.
                // $sql = "UPDATE `ipv4_mac` SET `mac_address` = '$clean_mac' WHERE port_id = '".$interface['port_id']."' AND ipv4_address = '$ip'";
                $old_mac = dbFetchCell('SELECT mac_address from ipv4_mac WHERE ipv4_address=? AND port_id=?', array($ip, $interface['port_id']));

                if ($clean_mac != $old_mac && $clean_mac != '' && $old_mac != '') {
                    d_echo("Changed mac address for $ip from $old_mac to $clean_mac\n");

                    log_event("MAC change: $ip : ".mac_clean_to_readable($old_mac).' -> '.mac_clean_to_readable($clean_mac), $device, 'interface', $interface['port_id']);
                }

                dbUpdate(array('mac_address' => $clean_mac, 'context_name' => $device['context_name']), 'ipv4_mac', 'port_id=? AND ipv4_address=?', array($interface['port_id'], $ip));
                echo '.';
            } elseif (isset($interface['port_id'])) {
                echo '+';
                // echo("Add MAC $mac\n");
                $insert_data = array(
                            'port_id'      => $interface['port_id'],
                            'mac_address'  => $clean_mac,
                            'ipv4_address' => $ip,
                            'context_name' => $device['context_name'],
                           );
                dbInsert($insert_data, 'ipv4_mac');
            }//end if
        }//end if
    }//end foreach

    $sql = "SELECT * from ipv4_mac AS M, ports as I WHERE M.port_id = I.port_id and I.device_id = '".$device['device_id']."'";
    foreach (dbFetchRows($sql) as $entry) {
        $entry_mac = $entry['mac_address'];
        $entry_if  = $entry['port_id'];
        $entry_ip  = $entry['ipv4_address'];
        if (!$mac_table[$entry_if][$entry_mac][$entry_ip]) {
            dbDelete('ipv4_mac', '`port_id` = ? AND `mac_address` = ? AND `ipv4_address` = ? ', array($entry_if, $entry_mac, $entry_ip));
            d_echo("Removing MAC $entry_mac from interface ".$interface['ifName']);

            echo '-';
        }
    }
    echo "\n";
    unset($mac);
    unset($device['context_name']);
}
unset($vrfs_c);
