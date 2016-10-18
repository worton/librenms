source: Support/FAQ.md
### Getting started
 - [How do I install LibreNMS?](#faq1)
 - [How do I add a device?](#faq2)
 - [How do I get help?](#faq3)
 - [What are the supported OSes for installing LibreNMS on?](#faq4)
 - [Do you have a demo available?](#faq5)

### Support
 - [Why do I get blank pages sometimes in the WebUI?](#faq6)
 - [Why do I not see any graphs?](#faq10)
 - [How do I debug pages not loading correctly?](#faq7)
 - [How do I debug the discovery process?](#faq11)
 - [How do I debug the poller process?](#faq12)
 - [Why do I get a lot apache or rrdtool zombies in my process list?](#faq14)
 - [Why do I see traffic spikes in my graphs?](#faq15)
 - [Why do I see gaps in my graphs?](#faq17)
 - [How do I change the IP / hostname of a device?](#faq16)
 - [My device doesn't finish polling within 300 seconds](#faq19)
 - [Things aren't working correctly?](#faq18)
 - [What do the values mean in my graphs?](#faq21)
 - [Why does a device show as a warning?](#faq22)
 - [Why do I not see all interfaces in the Overall traffic graph for a device?](#faq23)

### Developing
 - [How do I add support for a new OS?](#faq8)
 - [What information do you need to add a new OS?](#faq20)
 - [What can I do to help?](#faq9)
 - [How can I test another users branch?](#faq13)

#### <a name="faq1"> How do I install LibreNMS?</a>

This is currently well documented within the doc folder of the installation files.

Please see the following [doc](http://docs.librenms.org/Installation/Installing-LibreNMS/)

#### <a name="faq2"> How do I add a device?</a>

You have two options for adding a new device into LibreNMS.

 1. Using the command line via ssh you can add a new device by changing to the directory of your LibreNMS install and typing (be sure to put the correct details).

```ssh
./addhost.php [community] [v1|v2c] [port] [udp|udp6|tcp|tcp6]
```

> Please note that if the community contains special characters such as `$` then you will need to wrap it in `'`. I.e: `'Pa$$w0rd'`.

 2. Using the web interface, go to Devices and then Add Device. Enter the details required for the device that you want to add and then click 'Add Host'.

#### <a name="faq3"> How do I get help?</a>

We have a few methods for you to get in touch to ask for help.

[Mailing List](https://groups.google.com/forum/#!forum/librenms-project)

[IRC](https://webchat.freenode.net/) Freenode ##librenms

[Bug Reports](https://github.com/librenms/librenms/issues)

#### <a name="faq4"> What are the supported OSes for installing LibreNMS on?</a>

Supported is quite a strong word :) The 'officially' supported distros are:

 - Ubuntu / Debian
 - Red Hat / CentOS
 - Gentoo

However we will always aim to help wherever possible so if you are running a distro that isn't one of the above then give it a try anyway and if you need help then jump on the irc channel.

#### <a name="faq5"> Do you have a demo available?</a>

We do indeed, you can find access to the demo [here](https://demo.librenms.org)

#### <a name="faq6"> Why do I get blank pages sometimes in the WebUI?</a>

The first thing to do is to add /debug=yes/ to the end of the URI (I.e /devices/debug=yes/).

If the page you are trying to load has a substantial amount of data in it then it could be that the php memory limit needs to be increased in php.ini and then your web service reloaded.

#### <a name="faq10"> Why do I not see any graphs?</a>

The easiest way to check if all is well is to run `./validate.php` as root from within your install directory. This should give you info on why things aren't working.

One other reason could be a restricted snmpd.conf file or snmp view which limits the data sent back. If you use net-snmp then we suggest using 
the (included snmpd.conf)[https://raw.githubusercontent.com/librenms/librenms/master/snmpd.conf.example] file.

#### <a name="faq7"> How do I debug pages not loading correctly?</a>

A debug system is in place which enables you to see the output from php errors, warnings and notices along with the MySQL queries that have been run for that page.

To enable the debug option, add /debug=yes/ to the end of any URI (I.e /devices/debug=yes/) or ?debug=yes if you are debugging a graph directly.

You will then have a two options in the footer of the website - Show SQL Debug and Show PHP Debug. These will both popup that pages debug window for you to view. If the page itself has generated a fatal error then this will be displayed directly on the page.

#### <a name="faq11"> How do I debug the discovery process?</a>

Please see the [Discovery Support](http://docs.librenms.org/Support/Discovery Support) document for further details.

#### <a name="faq12"> How do I debug the poller process?</a>

Please see the [Poller Support](http://docs.librenms.org/Support/Poller Support) document for further details.

#### <a name="faq14"> Why do I get a lot apache or rrdtool zombies in my process list?</a>

If this is related to your web service for LibreNMS then this has been tracked down to an issue within php which the developers aren't fixing. We have implemented a work around which means you
shouldn't be seeing this. If you are, please report this in [issue 443](https://github.com/librenms/librenms/issues/443).

#### <a name="faq15"> Why do I see traffic spikes in my graphs?</a>

This occurs either when a counter resets or the device sends back bogus data making it look like a counter reset. We have enabled support for setting a maximum value for rrd files for ports.
Before this all rrd files were set to 100G max values, now you can enable support to limit this to the actual port speed.

rrdtool tune will change the max value when the interface speed is detected as being changed (min value will be set for anything 10M or over) or when you run the included script (scripts/tune_port.php).

#### <a name="faq17"> Why do I see gaps in my graphs?</a>

This is most commonly due to the poller not being able to complete it's run within 300 seconds. Check which devices are causing this by going to /poll-log/ within the Web interface.

When you find the device(s) which are taking the longest you can then look at the Polling module graph under Graphs -> Poller -> Poller Modules Performance. Take a look at what modules are taking the longest and disabled un used modules.

If you poll a large number of devices / ports then it's recommended to run a local recurisve dns server such as pdns-recursor.

Running RRDCached is also highly advised in larger installs but has benefits no matter the size.

#### <a name="faq16"> How do I change the IP / hostname of a device?</a>

There is a host rename tool called renamehost.php in your librenms root directory. When renaming you are also changing the device's IP / hostname address for monitoring.
Usage:
```bash
./renamehost.php <old hostname> <new hostname>
```

#### <a name="faq19"> My device doesn't finish polling within 300 seconds</a>

We have a few things you can try:

  - Disable unnecessary polling modules under edit device.
  - Set a max repeater value within the snmp settings for a device.
    What to set this to is tricky, you really should run an snmpbulkwalk with -Cr10 through -Cr50 to see what works best. 50 is usually a good choice if the device can cope.

#### <a name="faq18"> Things aren't working correctly?</a>

Run `./validate.php` as root from within your install.

Re-run `./validate.php` once you've resolved any issues raised.

You have an odd issue - we'd suggest you join our irc channel to discuss.

#### <a name="faq21"> What do the values mean in my graphs?</a>

The values you see are reported as metric values. Thanks to a post on [Reddit](https://www.reddit.com/r/networking/comments/4xzpfj/rrd_graph_interface_error_label_what_is_the_m/) 
here are those values:

```
10^-18  a - atto
10^-15  f - femto
10^-12  p - pico
10^-9   n - nano
10^-6   u - micro
10^-3   m - milli
0    (no unit)
10^3    k - kilo
10^6    M - mega
10^9    G - giga
10^12   T - tera
10^15   P - peta
```

#### <a name="faq22"> Why does a device show as a warning?</a>

This is indicating that the device has rebooted within the last 24 hours (by default). If you want to adjust this 
threshold then you can do so by setting `$config['uptime_warning']` in config.php. The value must be in seconds.

#### <a name="faq23"> Why do I not see all interfaces in the Overall traffic graph for a device?</a>

By default numerous interface types and interface descriptions are excluded from this graph. The excluded defailts are:

```php
$config['device_traffic_iftype'][] = '/loopback/';
$config['device_traffic_iftype'][] = '/tunnel/';
$config['device_traffic_iftype'][] = '/virtual/';
$config['device_traffic_iftype'][] = '/mpls/';
$config['device_traffic_iftype'][] = '/ieee8023adLag/';
$config['device_traffic_iftype'][] = '/l2vlan/';
$config['device_traffic_iftype'][] = '/ppp/';

$config['device_traffic_descr'][] = '/loopback/';
$config['device_traffic_descr'][] = '/vlan/';
$config['device_traffic_descr'][] = '/tunnel/';
$config['device_traffic_descr'][] = '/bond/';
$config['device_traffic_descr'][] = '/null/';
$config['device_traffic_descr'][] = '/dummy/';
```

If you would like to re-include l2vlan interfaces for instance, you first need to `unset` the config array and set your options:

```php
unset($config['device_traffic_iftype']);
$config['device_traffic_iftype'][] = '/loopback/';
$config['device_traffic_iftype'][] = '/tunnel/';
$config['device_traffic_iftype'][] = '/virtual/';
$config['device_traffic_iftype'][] = '/mpls/';
$config['device_traffic_iftype'][] = '/ieee8023adLag/';
$config['device_traffic_iftype'][] = '/ppp/';
```

#### <a name="faq8"> How do I add support for a new OS?</a>

The easiest way to show you how to do that is to link to an existing pull request that has been merged in on [GitHub](https://github.com/librenms/librenms/pull/352/files)

To go into a bit more detail, the following are usually needed:

**includes/definitions.inc.php**
Update this file to include the required definitions for the new OS.
**includes/discovery/os/ciscowlc.inc.php**
This file just sets the $os variable, done by checking the SNMP tree for a particular value that matches the OS you are adding.  Typically, this will come from the presence of specific values in
sysObjectID or sysDescr, or the existence of a particular enterprise tree.
**includes/polling/os/ciscowlc.inc.php**
This file will usually set the variables for $version and $hardware gained from an snmp lookup.
**html/images/os/$os.png**
This is a 32x32 png format image of the OS you are adding support for.

You will also need to supply a test unit within `tests/OSDiscoveryTest.php`. Please see [Support-New-OS](Support-New-OS.md) for further information.

#### <a name="faq20"> What information do you need to add a new OS?</a>

Under the device, click the gear and select Capture. 
Please provide the output of Discovery, Poller, and Snmpwalk as separate non-expiring pastebin.com links.

You can also use the command line to obtain the information.  Especially, if snmpwalk results in a large amount of data.
Replace the relevant information in these commands such as HOSTNAME and COMMUNITY. Use `snmpwalk` instead of `snmpbulkwalk` for v1 devices.

```bash
./discovery.php -h HOSTNAME -d -m os
./poller.php -h HOSTNAME -r -f -d -m os
snmpbulkwalk -OUneb -v2c -c COMMUNITY HOSTNAME .
```

If possible please also provide what the OS name should be if it doesn't exist already.

#### <a name="faq9"> What can I do to help?</a>

Thanks for asking, sometimes it's not quite so obvious and everyone can contribute something different. So here are some ways you can help LibreNMS improve.

- Code. This is a big thing. We want this community to grow by the software developing and evolving to cater for users needs. The biggest area that people can help make this happen is by providing code support. This doesn't necessarily mean contributing code for discovering a new device:
    - Web UI, a new look and feel has been adopted but we are not finished by any stretch of the imagination. Make suggestions, find and fix bugs, update the design / layout.
    - Poller / Discovery code. Improving it (we think a lot can be done to speed things up), adding new device support and updating old ones.
    - The LibreNMS main website, this is hosted on Git Hub like the main repo and we accept use contributions here as well :)
- Hardware. We don't physically need it but if we are to add device support, it's made a whole lot easier with access to the kit via snmp.
    - If you've got mibs, they are handy as well :)
    - If you know the vendor and can get permission to use logos that's also great.
- Bugs. Found one? We want to know about it. Most bugs are fixed after being spotted and reported by someone, I'd love to say we are amazing developers and will fix all bugs before you spot them but that's just not true.
- Feature requests. Can't code / won't code. No worries, chuck a feature request into Git Hub with enough detail and someone will take a look. A lot of the time this might be what interests someone, they need the same feature or they just have time. Please be patient, everyone who contributes does so in their own time.
- Be nice, this is the foundation of this project. We expect everyone to be nice. People will fall out, people will disagree but please do it so in a respectable way.
- Ask questions. Sometimes just by asking questions you prompt deeper conversations that can lead us to somewhere amazing so please never be afraid to ask a question.

#### <a name="faq13"> How can I test another users branch?</a>

LibreNMS can and is developed by anyone, this means someone may be working on a new feature or support for a device that you want.
It can be helpful for others to test these new features, using Git, this is made easy.

```bash
cd /opt/librenms
```

Firstly ensure that your current branch is in good state:
```bash
git status
```

If you see `nothing to commit, working directory clean` then let's go for it :)

Let's say that you want to test a users (f0o) new development branch (issue-1337) then you can do the following:

```bash
git remote add f0o https://github.com/f0o/librenms.git
git remote update f0o
git checkout issue-1337
```

Once you are done testing, you can easily switch back to the master branch:

```bash
git checkout master
```

If you want to pull any new updates provided by f0o's branch then whilst you are still in it, do the following:

```bash
git pull f0o issue-1337
```
