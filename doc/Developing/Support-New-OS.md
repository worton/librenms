source: Developing/Support-New-OS.md
This document will explain how to add basic and full support for a new OS. **Some knowledge in PHP is needed for the full support.**


#### BASIC SUPPORT FOR A NEW OS

### MIB

If we have the MIB, we can copy the file into the default directory:

```bash
/opt/librenms/mibs
```

#### New OS definition
Let's begin to declare the new OS in LibreNMS. At first we create a new definition file located here:

```bash
includes/definitions/$os.yaml
```

This is a [Yaml file](https://en.wikipedia.org/wiki/YAML). Please be careful of the formatting of this file.

```yaml
os: pulse
text: 'Pulse Secure'
type: firewall
icon: pulse
over:
    - { graph: device_bits, text: 'Device Traffic' }
    - { graph: device_processor, text: 'CPU Usage' }
    - { graph: device_mempool, text: 'Memory Usage' }
discovery:
    - sysDescr:
        - Pulse Connect Secure
        - Pulse Secure
        - Juniper Networks,Inc,VA-DTE
        - VA-SPE
```

#### Icon and Logo

Create an SVG image of the icon and logo.  Legacy PNG bitmaps are also supported but look bad on HiDPI.
- A vector image should not contain padding.  
- The file should not be larger than 20 Kb. Simplify paths to reduce large files.
- Use plain SVG without gzip compression.

##### Icon
- Save the icon SVG to **html/images/os/$os.svg**.
- Icons should look good when viewed at 32x32 px.
- Square icons are preferred to full logos with text.
- Remove small ornaments that are almost not visible when displayed with 32px width (e.g. ® or ™).

##### Logo
- Save the logo SVG to **html/images/logos/$os.svg**.
- Logos can be any dimension, but often are wide and contain the company name.
- If a logo is not present, the icon will be used.

##### Hints

Hints for [Inkscape](https://inkscape.org/):

- You can open a PDF to extract the logo.
- Ungroup elements to isolate the logo.
- Use `Path -> Simplify` to simplify paths of large files.
- Use `File -> Document Properties… -> Resize page to content…` to remove padding.
- Use `File -> Clean up document` to remove unused gradients, patterns, or markers.
- Use `File -> Save As -> Plain SVG` to save the final image.

By optimizing the SVG you can shrink the file size in some cases to less than 20 %.
[SVG Optimizer](https://github.com/svg/svgo) does a great job. There is also an [online version](https://jakearchibald.github.io/svgomg/).

#### OS Discovery
The discovery section of the OS yaml file contains information needed to detect this OS.

##### Discovery Operators
 - `sysObjectId` The preferred operator. Checks if the sysObjectID starts with one of the strings under this item
 - `sysDescr` Use this in addition to sysObjectId if required. Check that the sysDescr contains one of the strings under this item
 - `sysDescr_regex` Please avoid use of this. Checks if the sysDescr matches one of the regex statements under this item

##### Discoery Logic
YAML is converted to an array in PHP.  Consider the following YAML:
```yaml
discovery: 
  - sysObjectId: foo
  - 
    sysDescr: [ snafu, exodar ]
    sysObjectId: bar

```
This is how the discovery array would look in PHP:
```php
[
     [
       "sysObjectId" => "foo",
     ],
     [
       "sysDescr" => [
         "snafu",
         "exodar",
       ],
       "sysObjectId" => "bar",
     ]
]
```


The logic for the discovery is as follows:
1. One of the first level items must match
2. ALL of the second level items must match (sysObjectId, sysDescr)
3. One of the third level items (foo, [snafu,exodar], bar) must match

So, considering the example:
 - `sysObjectId: foo, sysDescr: ANYTHING` matches
 - `sysObjectId: bar, sysDescr: ANYTHING` does not match
 - `sysObjectId: bar, sysDescr: exodar` matches 
 - `sysObjectId: bar, sysDescr: snafu` matches 

#### Basic OS information polling

Here is the file location for polling the new OS within a vendor MIB or a standard one:

```bash
includes/polling/os/pulse.inc.php
```
This file will usually set the variables for $version, $hardware and $hostname retrieved from an snmp lookup.

```php
<?php

$version = preg_replace('/[\r\n\"]+/', ' ', snmp_get($device, "productVersion.0", "-OQv", "PULSESECURE-PSG-MIB"));
$hardware = "Juniper " . preg_replace('/[\r\n\"]+/', ' ', snmp_get($device, "productName.0", "-OQv", "PULSESECURE-PSG-MIB"));
$hostname = trim($poll_device['sysName'], '"');
```

Quick explanation and examples :

```bash
snmpwalk -v2c -c public -m SNMPv2-MIB -M mibs
//will give the overall OIDs that can be retrieve with this standard MIB. OID on the left side and the result on the right side
//Then we have just to pick the wanted OID and do a check

snmpget -v2c -c public -OUsb -m SNMPv2-MIB -M /opt/librenms/mibs -t 30 HOSTNAME SNMPv2-SMI::mib-2.1.1.0
//sysDescr.0 = STRING: Juniper Networks,Inc,Pulse Connect Secure,VA-DTE,8.1R1 (build 33493)

snmpget -v2c -c public -OUsb -m SNMPv2-MIB -M /opt/librenms/mibs -t 30 HOSTNAME SNMPv2-SMI::mib-2.1.5.0
//sysName.0 = STRING: pulse-secure

//Here the same with the vendor MIB and the specific OID
snmpget -v2c -c public -OUsb -m PULSESECURE-PSG-MIB -M /opt/librenms_old/mibs -t 30 HOSTNAME productName.0
//productName.0 = STRING: "Pulse Connect Secure,VA-DTE"

snmpget -v2c -c public -OUsb -m PULSESECURE-PSG-MIB -M /opt/librenms/mibs -t 30 HOSTNAME productVersion.0
//productVersion.0 = STRING: "8.1R1 (build 33493)"
```

#### The final check

Discovery
```bash
./discovery.php -h HOSTNAME
```

Polling
```bash
./poller.php -h HOSTNAME
```

At this step we should see all the values retrieved in LibreNMS.

### Full support for a new OS

#### MIB

At first we copy the MIB file into the default directory:

```bash
/opt/librenms/mibs
```

We are now ready to look at inside the file and find the OID we want to use. _For this documentation we'll use Pulse Secure devices._

Then we can test it with the snmpget command (hostname must be reachable):

```bash
//for example the OID iveCpuUtil.0:
snmpget -v2c -c public -OUsb -m PULSESECURE-PSG-MIB -M /opt/librenms/mibs -t 30 HOSTNAME iveCpuUtil.0
//quick explanation : snmpget -v2c -c COMMUNITY -OUsb -m MIBFILE -M MIB DIRECTORY HOSTNAME OID

//Result here:
iveCpuUtil.0 = Gauge32: 28
```

#### New OS definition
Let's begin to declare the new OS in LibreNMS. At first we create a new definition file located here:

```bash
includes/definitions/$os.yaml
```

This is a [Yaml file](https://en.wikipedia.org/wiki/YAML). Please be careful of the formatting of this file.

```yaml
os: pulse
text: 'Pulse Secure'
type: firewall
icon: pulse
over:
    - { graph: device_bits, text: 'Device Traffic' }
    - { graph: device_processor, text: 'CPU Usage' }
    - { graph: device_mempool, text: 'Memory Usage' }
```

If you are adding custom graphs, please add the following to `includes/definitions.inc.php`:
```php
//Don't forget to declare the specific graphs if needed. It will be located near the end of the file.

//Pulse Secure Graphs
$config['graph_types']['device']['pulse_users']['section']         = 'firewall';
$config['graph_types']['device']['pulse_users']['order']           = '0';
$config['graph_types']['device']['pulse_users']['descr']           = 'Active Users';
$config['graph_types']['device']['pulse_sessions']['section']      = 'firewall';
$config['graph_types']['device']['pulse_sessions']['order']        = '0';
$config['graph_types']['device']['pulse_sessions']['descr']        = 'Active Sessions';
```

#### Discovery OS

We create a new file named as our OS definition and in this directory:

```bash
includes/discovery/os/pulse.inc.php
```

Look at other files to get help in the code structure. For this example, it can be like this :

```php
// Pulse Secure OS definition
if (str_contains($sysDescr, array('Pulse Connect Secure', 'Pulse Secure', 'Juniper Networks,Inc,VA-DTE', 'VA-SPE'))) {
    $os = 'pulse';
}
```

As we declared Memory and CPU graphs before, we declare the OID in a PHP file :


**Memory**

```bash
includes/discovery/mempools/pulse.inc.php
```

```php
<?php
//
// Hardcoded discovery of Memory usage on Pulse Secure devices.
//
if ($device['os'] == 'pulse') {
    echo 'PULSE-MEMORY-POOL: ';

    $usage = str_replace('"', "", snmp_get($device, 'PULSESECURE-PSG-MIB::iveMemoryUtil.0', '-OvQ'));

    if (is_numeric($usage)) {
        discover_mempool($valid_mempool, $device, 0, 'pulse-mem', 'Main Memory', '100', null, null);
    }
}
```

**CPU**

```bash
includes/discovery/processors/pulse.inc.php
```

```php
<?php
//
// Hardcoded discovery of CPU usage on Pulse Secure devices.
//
if ($device['os'] == 'pulse') {
    echo 'Pulse Secure : ';

    $descr = 'Processor';
    $usage = str_replace('"', "", snmp_get($device, 'PULSESECURE-PSG-MIB::iveCpuUtil.0', '-OvQ'));

    if (is_numeric($usage)) {
        discover_processor($valid['processor'], $device, 'PULSESECURE-PSG-MIB::iveCpuUtil.0', '0', 'pulse-cpu', $descr,
 '100', $usage, null, null);
    }
}
```

_Please keep in mind that the PHP code is often different for the needs of the devices and the information we retrieve._

#### Polling OS

We will now do the same for the polling process:

**Memory**

```bash
includes/polling/mempools/pulse-mem.inc.php
```

```php
<?php

// Simple hard-coded poller for Pulse Secure
echo 'Pulse Secure MemPool'.'\n';

if ($device['os'] == 'pulse') {
  $perc     = str_replace('"', "", snmp_get($device, "PULSESECURE-PSG-MIB::iveMemoryUtil.0", '-OvQ'));
  $memory_available = str_replace('"', "", snmp_get($device, "UCD-SNMP-MIB::memTotalReal.0", '-OvQ'));
  $mempool['total'] = $memory_available;

  if (is_numeric($perc)) {
    $mempool['used'] = ($memory_available / 100 * $perc);
    $mempool['free'] = ($memory_available - $mempool['used']);
  }

  echo "PERC " .$perc."%\n";
  echo "Avail " .$mempool['total']."\n";

}
```


**CPU**

```bash
includes/polling/processors/pulse-cpu.inc.php
```

```php
<?php
// Simple hard-coded poller for Pulse Secure
echo 'Pulse Secure CPU Usage';

if ($device['os'] == 'pulse') {
    $usage = str_replace('"', "", snmp_get($device, 'PULSESECURE-PSG-MIB::iveCpuUtil.0', '-OvQ'));

    if (is_numeric($usage)) {
        $proc = ($usage * 100);
    }
}
```

Here is the file location for the specific graphs based on the OID in the vendor MIB:

```bash
includes/polling/os/pulse.inc.php
```
We declare two specific graphs for users and sessions numbers. Theses two graphs will be displayed on the firewall section of the graphs tab as it was written in the definition include file.

```php
<?php

$version = preg_replace('/[\r\n\"]+/', ' ', snmp_get($device, "productVersion.0", "-OQv", "PULSESECURE-PSG-MIB"));
$hardware = "Juniper " . preg_replace('/[\r\n\"]+/', ' ', snmp_get($device, "productName.0", "-OQv", "PULSESECURE-PSG-MIB"));
$hostname = trim($poll_device['sysName'], '"');

$users = snmp_get($device, 'iveConcurrentUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (is_numeric($users)) {
    $rrd_def = 'DS:users:GAUGE:600:0:U';

    $fields = array(
        'users' => $users,
    );

    $tags = compact('rrd_def');
    data_update($device, 'pulse_users', $tags, $fields);
    $graphs['pulse_users'] = true;
}

$sessions = snmp_get($device, 'iveConcurrentUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (is_numeric($sessions)) {
    $rrd_def = 'DS:sessions:GAUGE:600:0:U';

    $fields = array(
        'sessions' => $sessions,
    );

    $tags = compact('rrd_def');
    data_update($device, 'pulse_sessions', $tags, $fields);
    $graphs['pulse_sessions'] = true;
}
```
We finish in the declaration of the two graph types in the database:

We can do that within a file to share our work and contribute in the development of LibreNMS. :-)

```bash
sql-schema/xxx.sql
//check the file number in GitHub

php includes/sql-schema/update.php
```

Or put the SQL commands directly in Mysql or PhpMyadmin for our tests:

```php
INSERT INTO `graph_types`(`graph_type`, `graph_subtype`, `graph_section`, `graph_descr`, `graph_order`) VALUES ('device',  'pulse_users',  'firewall',  'Active Users',  '');
INSERT INTO `graph_types`(`graph_type`, `graph_subtype`, `graph_section`, `graph_descr`, `graph_order`) VALUES ('device',  'pulse_sessions',  'firewall',  'Active Sessions',  '');
```

#### Displaying

The specific graphs are not displayed automatically so we need to write the following PHP code:

**Pulse Sessions**

```bash
html/includes/graphs/device/pulse_sessions.inc.php
```

```php
<?php

$rrd_filename = rrd_name($device['hostname'], 'pulse_sessions');

require 'includes/graphs/common.inc.php';

$ds = 'sessions';

$colour_area = '9999cc';
$colour_line = '0000cc';

$colour_area_max = '9999cc';

$graph_max = 1;
$graph_min = 0;

$unit_text = 'Sessions';

require 'includes/graphs/generic_simplex.inc.php';
```

**Pulse Users**

```bash
html/includes/graphs/device/pulse_users.inc.php
```

```php
<?php

$rrd_filename = rrd_name($device['hostname'], 'pulse_users');

require 'includes/graphs/common.inc.php';

$ds = 'users';

$colour_area = '9999cc';
$colour_line = '0000cc';

$colour_area_max = '9999cc';

$graph_max = 1;

$unit_text = 'Users';

require 'includes/graphs/generic_simplex.inc.php';
```


#### The final check

Discovery
```bash
./discovery.php -h HOSTNAME
```

Polling
```bash
./poller.php -h HOSTNAME
```

At this step we should see all the values retrieved in LibreNMS.

### OS Test units
We have a testing unit for new OS', please ensure you add a test for any new OS' or updates to existing OS discovery.

The OS test unit file is located `tests/OSDiscoveryTest.php`. An example of this is as follows:

```php
    public function testNios()
    {
        $this->checkOS('nios');
        $this->checkOS('nios', 'nios-ipam');
    }
```


We utilise [snmpsim](http://snmpsim.sourceforge.net/) to do unit testing for OS discovery. For this to work you need
to supply an snmprec file. This is pretty simple and using nios as the example again this would look like:
```
1.3.6.1.2.1.1.1.0|4|Linux 3.14.25 #1 SMP Thu Jun 16 18:19:37 EDT 2016 x86_64
1.3.6.1.2.1.1.2.0|6|1.3.6.1.4.1.7779.1.1402
```

During testing LibreNMS will use any info in the snmprec file for snmp calls.  This one provides
sysDescr (`.1.3.6.1.2.1.1.1.0`, 4 = Octet String) and sysObjectID (`.1.3.6.1.2.1.1.2.0`, 6 = Object Identifier),
 which is the minimum that should be provided for new snmprec files.

To look up the numeric OID and type of an string OID with snmptranslate:
```bash
snmptranslate -On -Td SNMPv2-MIB::sysDescr.0
```

Common OIDs used in discovery:

| String OID                          | Numeric OID                 |
| ----------------------------------- | --------------------------- |
| SNMPv2-MIB::sysDescr.0              | 1.3.6.1.2.1.1.1.0           |
| SNMPv2-MIB::sysObjectID.0           | 1.3.6.1.2.1.1.2.0           |
| ENTITY-MIB::entPhysicalDescr.1      | 1.3.6.1.2.1.47.1.1.1.1.2.1  |
| ENTITY-MIB::entPhysicalMfgName.1    | 1.3.6.1.2.1.47.1.1.1.1.12.1 |
| SML-MIB::product-Name.0             | 1.3.6.1.4.1.2.6.182.3.3.1.0 |

List of SNMP data types:

| Type              | Value         |
| ----------------- | ------------- |
| OCTET STRING      | 4             |
| Integer32         | 2             |
| NULL              | 5             |
| OBJECT IDENTIFIER | 6             |
| IpAddress         | 64            |
| Counter32         | 65            |
| Gauge32           | 66            |
| TimeTicks         | 67            |
| Opaque            | 68            |
| Counter64         | 70            |

You can run `./scripts/pre-commit.php -u` to run the unit tests to check your code.
If you would like to run tests locally against a full snmpsim instance, run `./scripts/pre-commit.php -u --snmpsim`.
