#!/usr/bin/env php
<?php

/**
 * LibreNMS
 *
 *   This file is part of LibreNMS.
 *
 * @package    LibreNMS
 * @subpackage discovery
 * @copyright  (C) 2006 - 2012 Adam Armstrong
 */

$init_modules = array('discovery');
require __DIR__ . '/includes/init.php';

$start         = microtime(true);
$sqlparams     = array();
$options       = getopt('h:m:i:n:d::v::a::q', array('os:','type:'));

if (!isset($options['q'])) {
    echo $config['project_name_version']." Discovery\n";
    $versions = version_info(false);
    echo "Version info:\n";
    $cur_sha = $versions['local_sha'];
    echo "Commit SHA: $cur_sha\n";
    echo "DB Schema: ".$versions['db_schema']."\n";
    echo "PHP: ".$versions['php_ver']."\n";
    echo "MySQL: ".$versions['mysql_ver']."\n";
    echo "RRDTool: ".$versions['rrdtool_ver']."\n";
    echo "SNMP: ".$versions['netsnmp_ver']."\n";
}

if (isset($options['h'])) {
    if ($options['h'] == 'odd') {
        $options['n'] = '1';
        $options['i'] = '2';
    } elseif ($options['h'] == 'even') {
        $options['n'] = '0';
        $options['i'] = '2';
    } elseif ($options['h'] == 'all') {
        $where = ' ';
        $doing = 'all';
    } elseif ($options['h'] == 'new') {
        $where = 'AND `last_discovered` IS NULL';
        $doing = 'new';
    } elseif ($options['h']) {
        if (is_numeric($options['h'])) {
            $where = "AND `device_id` = '".$options['h']."'";
            $doing = $options['h'];
        } else {
            $where = "AND `hostname` LIKE '".str_replace('*', '%', mres($options['h']))."'";
            $doing = $options['h'];
        }
    }//end if
}//end if

if (isset($options['os'])) {
        $where .= " AND os = ?";
        $sqlparams[] = $options['os'];
}

if (isset($options['type'])) {
        $where .= " AND type = ?";
        $sqlparams[] = $options['type'];
}

if (isset($options['i']) && $options['i'] && isset($options['n'])) {
    $where .= ' AND MOD(device_id,'.$options['i'].") = '".$options['n']."'";
    $doing = $options['n'].'/'.$options['i'];
}

if (isset($options['d']) || isset($options['v'])) {
    echo "DEBUG!\n";
    if (isset($options['v'])) {
        $vdebug = true;
    }
    $debug = true;
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_reporting', 1);
} else {
    $debug = false;
    // ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 0);
    // ini_set('error_reporting', 0);
}

if (!$where) {
    echo "-h <device id> | <device hostname wildcard>  Poll single device\n";
    echo "-h odd                                       Poll odd numbered devices  (same as -i 2 -n 0)\n";
    echo "-h even                                      Poll even numbered devices (same as -i 2 -n 1)\n";
    echo "-h all                                       Poll all devices\n";
    echo "-h new                                       Poll all devices that have not had a discovery run before\n";
    echo "--os <os_name>                               Poll devices only with specified operating system\n";
    echo "--type <type>                                Poll devices only with specified type\n";
    echo "-i <instances> -n <number>                   Poll as instance <number> of <instances>\n";
    echo "                                             Instances start at 0. 0-3 for -n 4\n";
    echo "\n";
    echo "Debugging and testing options:\n";
    echo "-d                                           Enable debugging output\n";
    echo "-v                                           Enable verbose debugging output\n";
    echo "-m                                           Specify single module to be run\n";
    echo "\n";
    echo "Invalid arguments!\n";
    exit;
}

require 'includes/sql-schema/update.php';

$discovered_devices = 0;

if (!empty($config['distributed_poller_group'])) {
    $where .= ' AND poller_group IN('.$config['distributed_poller_group'].')';
}

foreach (dbFetch("SELECT * FROM `devices` WHERE status = 1 AND disabled = 0 $where ORDER BY device_id DESC", $sqlparams) as $device) {
    discover_device($device, $options);
}

$end      = microtime(true);
$run      = ($end - $start);
$proctime = substr($run, 0, 5);

if ($discovered_devices) {
    dbInsert(array('type' => 'discover', 'doing' => $doing, 'start' => $start, 'duration' => $proctime, 'devices' => $discovered_devices, 'poller' => $config['distributed_poller_name']), 'perf_times');
}

$string = $argv[0]." $doing ".date($config['dateformat']['compact'])." - $discovered_devices devices discovered in $proctime secs";
d_echo("$string\n");

if (!isset($options['q'])) {
    printStats();
}

logfile($string);
