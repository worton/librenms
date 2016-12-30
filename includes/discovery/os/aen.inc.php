<?php
/*
 * LibreNMS Acceidan MetroNID OS Discovery module
 *
 * Copyright (c) 2016 Chris A. Evans <thecityofguanyu@outlook.com>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

if (str_contains($sysDescr, array('AMN-','AEN-','AMO-'))) {
    $os = 'aen';
}
