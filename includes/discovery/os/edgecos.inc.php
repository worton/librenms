<?php
/**
 * edgecos.inc.php
 *
 * LibreNMS os discovery module for Edgocore OS
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2016 Neil Lathwood
 * @author     Neil Lathwood <neil@lathwood.co.uk>
 */

$items = array(
    '.1.3.6.1.4.1.259.6.10.94',
    '.1.3.6.1.4.1.259.10.1.45.103',
    '.1.3.6.1.4.1.259.10.1.24.104',
    '.1.3.6.1.4.1.259.10.1.24.103',
    '.1.3.6.1.4.1.259.10.1.22.101',
    '.1.3.6.1.4.1.259.10.1.42.101',
);

if (starts_with($sysObjectId, $items)) {
    $os = 'edgecos';
}
