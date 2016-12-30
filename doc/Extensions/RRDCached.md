source: Extensions/RRDCached.md
# Setting up RRDCached

This document will explain how to setup RRDCached for LibreNMS.

> If you are using rrdtool / rrdcached version 1.5 or above then this now supports creating rrd files over rrdcached. 
If you have rrdcached 1.5.5 or above, we can also tune over rrdcached.
To enable this set the following config:

```php
$config['rrdtool_version'] = '1.5.5';
```

### Support matrix

Shared FS: Is a shared filesystem required?

Features: Supported features in the version indicated.

          G = Graphs.

          C = Create RRD files.

          U = Update RRD files.

          T = Tune RRD files.

| Version | Shared FS | Features |
| ------- | :-------: | -------- |
| 1.4.x   | Yes       | G,U      |
| <1.5.5  | Yes       | G,U      |
| >=1.5.5 | No        | G,C,U    |
| >=1.6.x | No        | G,C,U    |

### RRDCached installation Ubuntu 16
```ssh
sudo apt-get install rrdcached
```

- Edit /opt/librenms/config.php to include:
```php
$config['rrdcached']    = "unix:/var/run/rrdcached.sock";
```
- Edit /etc/default/rrdcached to include:
```ssh
DAEMON=/usr/bin/rrdcached
WRITE_TIMEOUT=1800
WRITE_JITTER=1800
BASE_PATH=/opt/librenms/rrd/
JOURNAL_PATH=/var/lib/rrdcached/journal/
PIDFILE=/var/run/rrdcached.pid
SOCKFILE=/var/run/rrdcached.sock
SOCKGROUP=librenms
BASE_OPTIONS="-B -F"
```

### RRDCached installation CentOS 6
This example is based on a fresh LibreNMS install, on a minimal CentOS 6 installation.
In this example, we'll use the Repoforge repository.

```ssh
rpm -ivh http://pkgs.repoforge.org/rpmforge-release/rpmforge-release-0.5.3-1.el6.rf.x86_64.rpm
vi /etc/yum.repos.d/rpmforge.repo
```
- Enable the Extra repo

```ssh
yum update rrdtool
vi /etc/yum.repos.d/rpmforge.repo
```
- Disable the [rpmforge] and [rpmforge-extras] repos again

```ssh
vi /etc/sysconfig/rrdcached

# Settings for rrdcached
OPTIONS="-w 1800 -z 1800 -f 3600 -s librenms -U librenms -G librenms -B -R -j /var/tmp -l unix:/var/run/rrdcached/rrdcached.sock -t 4 -F -b /opt/librenms/rrd/"
RRDC_USER=librenms

mkdir /var/run/rrdcached
chown librenms:librenms /var/run/rrdcached/
chown librenms:librenms /var/rrdtool/
chown librenms:librenms /var/rrdtool/rrdcached/
chkconfig rrdcached on
service rrdcached start
```

- Edit /opt/librenms/config.php to include:
```php
$config['rrdcached']    = "unix:/var/run/rrdcached/rrdcached.sock";
```
### RRDCached installation CentOS 7
This example is based on a fresh LibreNMS install, on a minimal CentOS 7.x installation.
We'll use the epel-release and setup a RRDCached as a service.
It is recommended that you monitor your LibreNMS server with LibreNMS so you can view the disk I/O usage delta.
See [Installation (RHEL CentOS)][1] for localhost monitoring.

- Install the EPEL and update the repos and RRDtool.
```ssh
yum install epel-release
yum update
yum update rrdtool
```

- Create the needed directories, set ownership and permissions.
```ssh
mkdir /var/run/rrdcached
chown librenms:librenms /var/run/rrdcached
chmod 755 /var/run/rrdcached
```

- Create an rrdcached service for easy daemon management.
```ssh
touch /etc/systemd/system/rrdcached.service
```
- Edit rrdcached.service and paste the example config:
```ssh
[Unit]
Description=Data caching daemon for rrdtool
After=network.service

[Service]
Type=forking
PIDFile=/run/rrdcached.pid
ExecStart=/usr/bin/rrdcached -w 1800 -z 1800 -f 3600 -s librenms -U librenms -G librenms -B -R -j /var/tmp -l unix:/var/run/rrdcached/rrdcached.sock -t 4 -F -b /opt/librenms/rrd/

[Install]
WantedBy=default.target
```

- Reload the systemd unit files from disk, so it can recognize the newly created rrdcached.service. Enable the rrdcached.service on boot and start the service.
```ssh
systemctl daemon-reload
systemctl enable --now rrdcached.service
```

- Edit /opt/librenms/config.php to include:
```ssh
$config['rrdcached']    = "unix:/var/run/rrdcached/rrdcached.sock";
```

- Restart Apache
```ssh
systemctl restart httpd
```

Check to see if the graphs are being drawn in LibreNMS. This might take a few minutes.
After at least one poll cycle (5 mins), check the LibreNMS disk I/O performance delta.
Disk I/O can be found under the menu Devices>All Devices>[localhost hostname]>Health>Disk I/O.

Depending on many factors, you should see the Ops/sec drop by ~30-40%.

#### Securing RRCached
Please see [RRDCached Security](RRDCached-Security.md)

[1]: http://librenms.readthedocs.org/Installation/Installation-CentOS-7-Apache/
"Add localhost to LibreNMS"
