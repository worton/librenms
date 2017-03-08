#!/usr/bin/env php
<?php

// MYSQL Check - FIXME
// 1 UNKNOWN
$config['db_port'] = null;
include 'config.php';

if (!isset($sql_file)) {
    $sql_file = 'build.sql';
}

$sql_fh = fopen($sql_file, 'r');
if ($sql_fh === false) {
    echo 'ERROR: Cannot open SQL build script '.$sql_file."\n";
    exit(1);
}

$database_link = mysqli_connect('p:'.$config['db_host'], $config['db_user'], $config['db_pass'], null, $config['db_port']);
if ($database_link === false) {
    echo 'ERROR: Cannot connect to database: '.mysqli_error($database_link)."\n";
    exit(1);
}

$select = mysqli_select_db($database_link, $config['db_name']);
mysqli_query($database_link, "SET NAMES 'utf8'");
mysqli_query($database_link, "SET CHARACTER SET 'utf8'");
mysqli_query($database_link, "SET COLLATION_CONNECTION = 'utf8_unicode_ci'");
if ($select === false) {
    echo 'ERROR: Cannot select database: '.mysqli_error($database_link)."\n";
    exit(1);
}

$limit = 0;
while (!feof($sql_fh)) {
    $line = fgetss($sql_fh);
    if (isset($_SESSION['stage'])) {
        $limit++;
        if (isset($_SESSION['offset']) && $limit < $_REQUEST['offset']) {
            continue;
        } elseif (time()-$_SESSION['last'] > 45) {
            $_SESSION['offset'] = $limit;
            $GLOBALS['refresh'] = '<b>Installing, please wait..</b><sub>'.date('r').'</sub><script>window.location.href = "install.php?offset='.$limit.'";</script>';
            return;
        } else {
            echo 'Step #'.$limit.' ...'.PHP_EOL;
        }
    }

    if (!empty($line)) {
        $creation = mysqli_query($database_link, $line);
        if (!$creation) {
            echo 'WARNING: Cannot execute query ('.$line.'): '.mysqli_error($database_link)."\n";
        }
    }
}

fclose($sql_fh);

require 'includes/sql-schema/update.php';
