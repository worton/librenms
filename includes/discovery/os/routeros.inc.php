<?php

if (starts_with($sysDescr, 'router') && is_numeric(snmp_get($device, 'SNMPv2-SMI::enterprises.14988.1.1.4.3.0', '-Oqv', ''))) {
    $os = 'routeros';
}

if (starts_with($sysDescr, 'RouterOS')) {
    $os = 'routeros';
}
