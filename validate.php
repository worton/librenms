#!/usr/bin/env php
<?php

/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

chdir(__DIR__); // cwd to the directory containing this script

require_once 'includes/common.php';

$options = getopt('m:h::');

if (isset($options['h'])) {
    echo
        "\n Validate setup tool

    Usage: ./validate.php [-m <module>] [-h]
        -h This help section.
        -m Any sub modules you want to run, comma separated:
          - mail: this will test your email settings  (uses default_mail option even if default_only is not set).
          - dist-poller: this will test for the install running as a distributed poller.
          - rrdcheck: this will check to see if your rrd files are corrupt

        Example: ./validate.php -m mail.

        "
    ;
    exit;
}


// critical config.php checks
if (!file_exists('config.php')) {
    print_fail('config.php does not exist, please copy config.php.default to config.php');
    exit;
}

$config_failed = false;
$syntax_check = `php -ln config.php`;
if (!str_contains($syntax_check, 'No syntax errors detected')) {
    print_fail('Syntax error in config.php');
    echo $syntax_check;
    $config_failed = true;
}

$first_line = rtrim(`head -n1 config.php`);
if (!starts_with($first_line, '<?php')) {
    print_fail("config.php doesn't start with a <?php - please fix this ($first_line)");
    $config_failed = true;
}
if (str_contains(`tail config.php`, '?>')) {
    print_fail("Remove the ?> at the end of config.php");
    $config_failed = true;
}

if ($config_failed) {
    exit;
}

$init_modules = array();
require 'includes/init.php';

// make sure install_dir is set correctly, or the next includes will fail
if (!file_exists($config['install_dir'].'/config.php')) {
    print_fail('$config[\'install_dir\'] is not set correctly.  It should probably be set to: ' . getcwd());
    exit;
}

$git_found = check_git_exists();
if ($git_found !== true) {
    print_warn('Unable to locate git. This should probably be installed.');
}

$versions = version_info();
$cur_sha = $versions['local_sha'];

echo <<< EOF
==========================================================
Component | Version
--------- | -------
LibreNMS  | {$cur_sha}
DB Schema | {$versions['db_schema']}
PHP       | {$versions['php_ver']}
MySQL     | {$versions['mysql_ver']}
RRDTool   | {$versions['rrdtool_ver']}
SNMP      | {$versions['netsnmp_ver']}
==========================================================
\n
EOF;

// Check we are running this as the root user
if (function_exists('posix_getpwuid')) {
    $userinfo = posix_getpwuid(posix_geteuid());
    $username = $userinfo['name'];
} else {
    $username = getenv('USERNAME') ?: getenv('USER'); //http://php.net/manual/en/function.get-current-user.php
}
if (!($username === 'root' || (isset($config['user']) && $username === $config['user']))) {
    print_fail('You need to run this script as root' . (isset($config['user']) ? ' or '.$config['user'] : ''));
}

if ($git_found === true) {
    if ($config['update_channel'] == 'master' && $cur_sha != $versions['github']['sha']) {
        $commit_date = new DateTime('@'.$versions['local_date'], new DateTimeZone(date_default_timezone_get()));
        print_warn("Your install is out of date, last update: " . $commit_date->format('r'));
    }
}

// Check php modules we use to make sure they are loaded
$extensions = array('pcre','curl','session','snmp','mcrypt');
foreach ($extensions as $extension) {
    if (extension_loaded($extension) === false) {
        $missing_extensions[] = $extension;
    }
}
if (!empty($missing_extensions)) {
    print_fail("We couldn't find the following php extensions, please ensure they are installed:");
    foreach ($missing_extensions as $extension) {
        echo "$extension\n";
    }
}

if (class_exists('Net_IPv4') === false) {
    print_fail("It doesn't look like Net_IPv4 is installed");
}
if (class_exists('Net_IPv6') === false) {
    print_fail("It doesn't look like Net_IPv6 is installed");
}

// Let's test the user configured if we have it
if (isset($config['user'])) {
    $tmp_user = $config['user'];
    $tmp_dir = $config['install_dir'];
    $find_result = rtrim(`find $tmp_dir \! -user $tmp_user`);
    if (!empty($find_result)) {
        // This isn't just the log directory, let's print the list to the user
        $files = explode(PHP_EOL, $find_result);
        if (is_array($files)) {
            print_fail(
                "We have found some files that are owned by a different user than $tmp_user, " .
                'this will stop you updating automatically and / or rrd files being updated causing graphs to fail.',
                "chown -R $tmp_user:$tmp_user {$config['install_dir']}"
            );
            print_list($files, "\t %s\n");
        }
    }
} else {
    print_warn('You don\'t have $config["user"] set, this most likely needs to be set to librenms');
}

// Run test on MySQL
try {
    dbConnect();
    print_ok('Database connection successful');
} catch (\LibreNMS\Exceptions\DatabaseConnectException $e) {
    print_fail('Error connecting to your database '.$e->getMessage());
}
// pull in the database config settings
mergedb();

// Test for MySQL Strict mode
$strict_mode = dbFetchCell("SELECT @@global.sql_mode");
if (str_contains($strict_mode, 'STRICT_TRANS_TABLES')) {
    //FIXME - Come back to this once other MySQL modes are fixed
    //print_fail('You have MySQL STRICT_TRANS_TABLES enabled, please disable this until full support has been added: https://dev.mysql.com/doc/refman/5.0/en/sql-mode.html');
}

if (empty($strict_mode) === false) {
    print_fail("You have not set sql_mode='' in your mysql config");
}

// Test for correct character set and collation
$collation = dbFetchRows("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA S WHERE schema_name = '" . $config['db_name'] . "' AND  ( DEFAULT_CHARACTER_SET_NAME != 'utf8' OR DEFAULT_COLLATION_NAME != 'utf8_unicode_ci')");
if (empty($collation) !== true) {
    print_fail('MySQL Database collation is wrong: ' . implode(' ', $collation[0]), 'https://t.libren.ms/-zdwk');
}
$collation_tables = dbFetchRows("SELECT T.TABLE_NAME, C.CHARACTER_SET_NAME, C.COLLATION_NAME FROM information_schema.TABLES AS T, information_schema.COLLATION_CHARACTER_SET_APPLICABILITY AS C WHERE C.collation_name = T.table_collation AND T.table_schema = '" . $config['db_name'] . "' AND  ( C.CHARACTER_SET_NAME != 'utf8' OR C.COLLATION_NAME != 'utf8_unicode_ci' );");
if (empty($collation_tables) !== true) {
    print_fail('MySQL tables collation is wrong: ', 'http://bit.ly/2lAG9H8');
    print_list($collation_tables, "\t %s\n");
}
$collation_columns = dbFetchRows("SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS  WHERE TABLE_SCHEMA = '" . $config['db_name'] . "'  AND  ( CHARACTER_SET_NAME != 'utf8' OR COLLATION_NAME != 'utf8_unicode_ci' );");
if (empty($collation_columns) !== true) {
    print_fail('MySQL column collation is wrong: ', 'https://t.libren.ms/-zdwk');
    print_list($collation_columns, "\t %s\n");
}

if (is_file('misc/db_schema.yaml')) {
    $master_schema = Symfony\Component\Yaml\Yaml::parse(
        file_get_contents('misc/db_schema.yaml')
    );

    $current_schema = dump_db_schema();
    $schema_update = array();

    foreach ((array)$master_schema as $table => $data) {
        if (empty($current_schema[$table])) {
            print_fail("Database: missing table ($table)");

            $columns = array_map('column_schema_to_sql', $data['Columns']);
            $indexes = array_map('index_schema_to_sql', $data['Indexes']);
            $def = implode(', ', array_merge(array_values($columns), array_values($indexes)));

            $schema_update[] = "CREATE TABLE `$table` ($def);";
        } else {
            $previous_column = '';
            foreach ($data['Columns'] as $column => $cdata) {
                $cur = $current_schema[$table]['Columns'][$column];
                if (empty($current_schema[$table]['Columns'][$column])) {
                    print_fail("Database: missing column ($table/$column)");

                    $sql = "ALTER TABLE `$table` ADD " . column_schema_to_sql($cdata);
                    if (!empty($previous_column)) {
                        $sql .= " AFTER `$previous_column`";
                    }
                    $schema_update[] = $sql . ';';
                } elseif ($cdata != $cur) {
                    print_fail("Database: incorrect column ($table/$column)");
                    $schema_update[] = "ALTER TABLE `$table` CHANGE `$column` " . column_schema_to_sql($cdata) . ';';
                }
                $previous_column = $column;
                unset($current_schema[$table]['Columns'][$column]); // remove checked columns
            }

            foreach ($current_schema[$table]['Columns'] as $column => $_unused) {
                print_fail("Database: extra column ($table/$column)");
                $schema_update[] = "ALTER TABLE `$table` DROP `$column`;";
            }


            foreach ($data['Indexes'] as $name => $index) {
                if (empty($current_schema[$table]['Indexes'][$name])) {
                    print_fail("Database: missing index ($table/$name)");
                    $schema_update[] = "ALTER TABLE `$table` ADD " . index_schema_to_sql($index) . ';';
                } elseif ($index != $current_schema[$table]['Indexes'][$name]) {
                    print_fail("Database: incorrect index ($table/$name)");
                    $schema_update[] = "ALTER TABLE `$table` DROP INDEX `$name`, " . index_schema_to_sql($index) . ';';
                }

                unset($current_schema[$table]['Indexes'][$name]);
            }

            foreach ($current_schema[$table]['Indexes'] as $name => $_unused) {
                print_fail("Database: extra index ($table/$name)");
                $schema_update[] = "ALTER TABLE `$table` DROP INDEX `$name`;";
            }
        }

        unset($current_schema[$table]); // remove checked tables
    }

    foreach ($current_schema as $table => $data) {
        print_fail("Database: extra table ($table)");
        $schema_update[] = "DROP TABLE `$table`;";
    }
} else {
    print_warn("We haven't detected the db_schema.yaml file");
}

if (empty($schema_update)) {
    print_ok('Database schema correct');
} else {
    print_fail("We have detected that your database schema may be wrong, please report the following to us on IRC or the community site (https://t.libren.ms/5gscd):");
    print_list($schema_update, "\t %s\n", 30);
}

$ini_tz = ini_get('date.timezone');
$sh_tz = rtrim(shell_exec('date +%Z'));
$php_tz = date('T');

if (empty($ini_tz)) {
    print_fail(
        'You have no timezone set for php.',
        'http://php.net/manual/en/datetime.configuration.php#ini.date.timezone'
    );
} elseif ($sh_tz !== $php_tz) {
    print_fail("You have a different system timezone ($sh_tz) specified to the php configured timezone ($php_tz), please correct this.");
}

// Test transports
if ($config['alerts']['email']['enable'] === true) {
    print_warn('You have the old alerting system enabled - this is to be deprecated on the 1st of June 2015: https://groups.google.com/forum/#!topic/librenms-project/1llxos4m0p4');
}

// Test rrdcached
if (!$config['rrdcached']) {
    $rrd_dir = stat($config['rrd_dir']);
    if ($rrd_dir[4] == 0 || $rrd_dir[5] == 0) {
        print_warn('Your RRD directory is owned by root, please consider changing over to user a non-root user');
    }

    if (substr(sprintf('%o', fileperms($config['rrd_dir'])), -3) != 775) {
        print_warn('Your RRD directory is not set to 0775', "chmod 775 {$config['rrd_dir']}");
    }
}

if (isset($config['rrdcached'])) {
    check_rrdcached();
}

// Disk space and permission checks
if (substr(sprintf('%o', fileperms($config['temp_dir'])), -3) != 777) {
    print_warn('Your tmp directory ('.$config['temp_dir'].") is not set to 777 so graphs most likely won't be generated");
}

$space_check = (disk_free_space($config['install_dir']) / 1024 / 1024);
if ($space_check < 512 && $space_check > 1) {
    print_warn('Disk space where '.$config['install_dir'].' is located is less than 512Mb');
}

if ($space_check < 1) {
    print_fail('Disk space where '.$config['install_dir'].' is located is empty!!!');
}

// Check programs
$bins = array('fping','fping6','rrdtool','snmpwalk','snmpget','snmpbulkwalk');
$suid_bins = array('fping', 'fping6');
foreach ($bins as $bin) {
    $cmd = rtrim(shell_exec("which {$config[$bin]} 2>/dev/null"));
    if (!$cmd) {
        print_fail("$bin location is incorrect or bin not installed. \n\tYou can also manually set the path to $bin by placing the following in config.php: \n\t\$config['$bin'] = \"/path/to/$bin\";");
    } elseif (in_array($bin, $suid_bins) && !(fileperms($cmd) & 2048)) {
        print_fail("$bin should be suid!", "chmod u+s $cmd");
    }
}

// Check that rrdtool config version is what we see
if (isset($config['rrdtool_version']) && (version_compare($config['rrdtool_version'], $versions['rrdtool_ver'], '>'))) {
    print_fail('The rrdtool version you have specified is newer than what is installed.', "Either comment out \$config['rrdtool_version'] = '{$config['rrdtool_version']}'; or set \$config['rrdtool_version'] = '{$versions['rrdtool_ver']}';");
}

$disabled_functions = explode(',', ini_get('disable_functions'));
$required_functions = array('exec','passthru','shell_exec','escapeshellarg','escapeshellcmd','proc_close','proc_open','popen');
foreach ($required_functions as $function) {
    if (in_array($function, $disabled_functions)) {
        print_fail("$function is disabled in php.ini");
    }
}

if (!function_exists('openssl_random_pseudo_bytes')) {
    print_warn("openssl_random_pseudo_bytes is not being used for user password hashing. This is a recommended function (https://secure.php.net/openssl_random_pseudo_bytes)");
    if (!is_readable('/dev/urandom')) {
        print_warn("It also looks like we can't use /dev/urandom for user password hashing. We will fall back to generating our own hash - be warned");
    }
}

// check poller
if (dbFetchCell('SELECT COUNT(*) FROM `pollers`')) {
    if (dbFetchCell('SELECT COUNT(*) FROM `pollers` WHERE `last_polled` >= DATE_ADD(NOW(), INTERVAL - 5 MINUTE)') == 0) {
        print_fail("The poller has not run in the last 5 minutes, check the cron job");
    }
} else {
    print_fail('The poller has never run, check the cron job');
}


if (dbFetchCell('SELECT COUNT(*) FROM `devices`') == 0) {
    print_warn("You have not added any devices yet.");
} else {
// check discovery last run
    if (dbFetchCell('SELECT COUNT(*) FROM `devices` WHERE `last_discovered` IS NOT NULL') == 0) {
        print_fail('Discovery has never run, check the cron job');
    } elseif (dbFetchCell("SELECT COUNT(*) FROM `devices` WHERE `last_discovered` <= DATE_ADD(NOW(), INTERVAL - 24 HOUR) AND `ignore` = 0 AND `disabled` = 0 AND `status` = 1") > 0) {
        print_fail("Discovery has not completed in the last 24 hours, check the cron job");
    }

// check devices polling
    if (count($devices = dbFetchColumn("SELECT `hostname` FROM `devices` WHERE (`last_polled` < DATE_ADD(NOW(), INTERVAL - 5 MINUTE) OR `last_polled` IS NULL) AND `ignore` = 0 AND `disabled` = 0 AND `status` = 1")) > 0) {
        print_warn("Some devices have not been polled in the last 5 minutes.
        You may have performance issues. Check your poll log and see: http://docs.librenms.org/Support/Performance/");
        print_list($devices, "\t %s\n");
    }

    if (count($devices = dbFetchColumn('SELECT `hostname` FROM `devices` WHERE last_polled_timetaken > 300 AND `ignore` = 0 AND `disabled` = 0 AND `status` = 1')) > 0) {
        print_fail("Some devices have not completed their polling run in 5 minutes, this will create gaps in data.
        Check your poll log and refer to http://docs.librenms.org/Support/Performance/");
        print_list($devices, "\t %s\n");
    }
}


if ($git_found === true) {
    if ($versions['local_branch'] != 'master') {
        print_warn("Your local git branch is not master, this will prevent automatic updates.");
    }

    // check for modified files
    $modifiedcmd = 'git diff --name-only --exit-code';
    if ($username === 'root') {
        $modifiedcmd = 'su '.$config['user'].' -c "'.$modifiedcmd.'"';
    }
    exec($modifiedcmd, $cmdoutput, $code);
    if ($code !== 0 && !empty($cmdoutput)) {
        print_warn("Your local git contains modified files, this could prevent automatic updates.\nModified files:");
        print_list($cmdoutput, "\t %s\n");
    }
}
// Modules test
$modules = explode(',', $options['m']);
foreach ($modules as $module) {
    switch ($module) {
        case 'mail':
            if ($config['alert']['transports']['mail'] === true) {
                $run_test = 1;
                if (empty($config['alert']['default_mail'])) {
                    print_fail('default_mail config option needs to be specified to test email');
                    $run_test = 0;
                } elseif ($config['email_backend'] == 'sendmail') {
                    if (empty($config['email_sendmail_path'])) {
                        print_fail("You have selected sendmail but not configured email_sendmail_path");
                        $run_test = 0;
                    } elseif (!file_exists($config['email_sendmail_path'])) {
                        print_fail("The configured email_sendmail_path is not valid");
                        $run_test = 0;
                    }
                } elseif ($config['email_backend'] == 'smtp') {
                    if (empty($config['email_smtp_host'])) {
                        print_fail('You have selected SMTP but not configured an SMTP host');
                        $run_test = 0;
                    }
                    if (empty($config['email_smtp_port'])) {
                        print_fail('You have selected SMTP but not configured an SMTP port');
                        $run_test = 0;
                    }
                    if (($config['email_smtp_auth'] === true) && (empty($config['email_smtp_username']) || empty($config['email_smtp_password']))) {
                        print_fail('You have selected SMTP auth but have not configured both username and password');
                        $run_test = 0;
                    }
                }//end if
                if ($run_test == 1) {
                    if ($err = send_mail($config['alert']['default_mail'], 'Test email', 'Testing email from NMS')) {
                        print_ok('Email has been sent');
                    } else {
                        print_fail('Issue sending email to '.$config['alert']['default_mail'].' with error '.$err);
                    }
                }
            }//end if
            break;
        case 'dist-poller':
            if ($config['distributed_poller'] !== true) {
                print_fail('You have not enabled distributed_poller');
            } else {
                if (empty($config['distributed_poller_memcached_host'])) {
                    print_fail('You have not configured $config[\'distributed_poller_memcached_host\']');
                } elseif (empty($config['distributed_poller_memcached_port'])) {
                    print_fail('You have not configured $config[\'distributed_poller_memcached_port\']');
                } else {
                    $connection = @fsockopen($config['distributed_poller_memcached_host'], $config['distributed_poller_memcached_port']);
                    if (!is_resource($connection)) {
                        print_fail('We could not get memcached stats, it is possible that we cannot connect to your memcached server, please check');
                    } else {
                        fclose($connection);
                        print_ok('Connection to memcached is ok');
                    }
                }
                if (empty($config['rrdcached'])) {
                    print_fail('You have not configured $config[\'rrdcached\']');
                } elseif (empty($config['rrd_dir'])) {
                    print_fail('You have not configured $config[\'rrd_dir\']');
                } else {
                    check_rrdcached();
                }
            }
            break;
        case 'rrdcheck':
            // Loop through the rrd_dir
            $rrd_directory = new RecursiveDirectoryIterator($config['rrd_dir']);
            // Filter out any non rrd files
            $rrd_directory_filter = new LibreNMS\RRDRecursiveFilterIterator($rrd_directory);
            $rrd_iterator = new RecursiveIteratorIterator($rrd_directory_filter);
            $rrd_total = iterator_count($rrd_iterator);
            $rrd_iterator->rewind(); // Rewind iterator in case iterator_count left iterator in unknown state

            echo "\nScanning ".$rrd_total." rrd files in ".$config['rrd_dir']."...\n";

            // Count loops so we can push status to the user
            $loopcount = 0;
            $screenpad = 0;

            foreach ($rrd_iterator as $filename => $file) {
                $rrd_test_result = rrdtest($filename, $output, $error);

                $loopcount++;
                if (($loopcount % 50) == 0) {
                        //This lets us update the previous status update without spamming in most consoles
                        echo "\033[".$screenpad."D";
                        $test_status = 'Status: '.$loopcount.'/'.$rrd_total;
                        echo $test_status;
                        $screenpad = strlen($test_status);
                }

                // A non zero result means there was some kind of error
                if ($rrd_test_result > 0) {
                        echo "\033[".$screenpad."D";
                        print_fail('Error parsing "'.$filename.'" RRD '.trim($error));
                        $screenpad = 0;
                }
            }
            echo "\033[".$screenpad."D";
            echo "Status: ".$loopcount."/".$rrd_total." - Complete\n";

            break;
    }//end switch
}//end foreach

// End


function print_ok($msg)
{
    c_echo("[%gOK%n]    $msg\n");
}//end print_ok()


function print_fail($msg, $fix = null)
{
    c_echo("[%RFAIL%n]  $msg");
    if ($fix && strlen($msg) > 72) {
        echo PHP_EOL . "       ";
    }
    print_fix($fix);
}


function print_warn($msg, $fix = null)
{
    c_echo("[%YWARN%n]  $msg");
    if ($fix && strlen($msg) > 72) {
        echo PHP_EOL . "       ";
    }
    print_fix($fix);
}

/**
 * @param $fix
 */
function print_fix($fix)
{
    if (!empty($fix)) {
        c_echo(" [%BFIX%n] %B$fix%n");
    }
    echo PHP_EOL;
}

function check_rrdcached()
{
    global $config;
    list($host,$port) = explode(':', $config['rrdcached']);
    if ($host == 'unix') {
        // Using socket, check that file exists
        if (!file_exists($port)) {
            print_fail("$port doesn't appear to exist, rrdcached test failed");
        }
    } else {
        $connection = @fsockopen($host, $port);
        if (is_resource($connection)) {
            fclose($connection);
        } else {
            print_fail('Cannot connect to rrdcached instance');
        }
    }
}//end check_rrdcached
