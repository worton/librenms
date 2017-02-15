<?php
/*
 * LibreNMS
 *
 * Copyright (c) 2016 Neil Lathwood <neil@lathwood.co.uk>
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

if ($device['os'] === 'junos') {
    $pre_cache['junos_oids'] = snmpwalk_cache_multi_oid($device, 'JnxDomCurrentEntry', array(), 'JUNIPER-DOM-MIB', 'junos');
    d_echo($pre_cache);
}
