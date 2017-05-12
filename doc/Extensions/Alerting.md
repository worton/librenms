source: Extensions/Alerting.md
Table of Content:

- [About](#about)
- [Rules](#rules)
    - [Syntax](#rules-syntax)
    - [Examples](#rules-examples)
    - [Procedure](#rules-procedure)
- [Templates](#templates)
    - [Syntax](#templates-syntax)
    - [Examples](#templates-examples)
    - [Included](#templates-included)
- [Transports](#transports)
    - [E-Mail](#transports-email)
    - [API](#transports-api)
    - [Nagios-Compatible](#transports-nagios)
    - [IRC](#transports-irc)
    - [Slack](#transports-slack)
    - [Rocket.chat](#transports-rocket)
    - [HipChat](#transports-hipchat)
    - [PagerDuty](#transports-pagerduty)
    - [Pushover](#transports-pushover)
    - [Boxcar](#transports-boxcar)
    - [Telegram](#transports-telegram)
    - [Pushbullet](#transports-pushbullet)
    - [Clickatell](#transports-clickatell)
    - [PlaySMS](#transports-playsms)
    - [VictorOps](#transports-victorops)
    - [Canopsis](#transports-canopsis)
    - [osTicket](#transports-osticket)
    - [Microsoft Teams](#transports-msteams)
    - [Cisco Spark](#transports-ciscospark)
    - [SMSEagle](#transports-smseagle)
    - [Syslog](#transports-syslog)
- [Entities](#entities)
    - [Devices](#entity-devices)
    - [BGP Peers](#entity-bgppeers)
    - [IPSec Tunnels](#entity-ipsec)
    - [Memory Pools](#entity-mempools)
    - [Ports](#entity-ports)
    - [Processors](#entity-processors)
    - [Storage](#entity-storage)
- [Macros](#macros)
    - [Device](#macros-device)
    - [Port](#macros-port)
    - [Time](#macros-time)
    - [Sensors](#macros-sensors)
    - [Misc](#macros-misc)
- [Additional Options](#extra)


# <a name="about">About</a>

LibreNMS includes a highly customizable alerting system.
The system requires a set of user-defined rules to evaluate the situation of each device, port, service or any other entity.

> You can configure all options for alerting and transports via the WebUI, config options in this document are crossed out but left for reference.

This document only covers the usage of it. See the [DEVELOPMENT.md](https://github.com/f0o/glowing-tyrion/blob/master/DEVELOPMENT.md) for code-documentation.

# <a name="rules">Rules</a>

Rules are defined using a logical language.
The GUI provides a simple way of creating basic as well as complex Rules in a self-describing manner.
More complex rules can be written manually.

## <a name="rules-syntax">Syntax</a>

Rules must consist of at least 3 elements: An __Entity__, a __Condition__ and a __Value__.
Rules can contain braces and __Glues__.
__Entities__ are provided as `%`-Noted pair of Table and Field. For Example: `%ports.ifOperStatus`.
__Conditions__ can be any of:

- Equals `=`
- Not Equals `!=`
- Like `~`
- Not Like `!~`
- Greater `>`
- Greater or Equal `>=`
- Smaller `<`
- Smaller or Equal `<=`

__Values__ can be Entities or any single-quoted data.
__Glues__ can be either `&&` for `AND` or `||` for `OR`.

__Note__: The difference between `Equals` and `Like` (and its negation) is that `Equals` does a strict comparison and `Like` allows the usage of RegExp.
Arithmetics are allowed as well.

## <a name="rules-examples">Examples</a>

Alert when:

- Device goes down: `%devices.status != '1'`
- Any port changes: `%ports.ifOperStatus != 'up'`
- Root-directory gets too full: `%storage.storage_descr = '/' && %storage.storage_perc >= '75'`
- Any storage gets fuller than the 'warning': `%storage.storage_perc >= %storage_perc_warn`
- If device is a server and the used storage is above the warning level, but ignore /boot partitions: `%storage.storage_perc > %storage.storage_perc_warn && %devices.type = "server" && %storage.storage_descr !~ "/boot"`
- VMware LAG is not using "Source ip address hash" load balancing: `%devices.os = "vmware" && %ports.ifType = "ieee8023adLag" && %ports.ifDescr !~ "Link Aggregation @, load balancing algorithm: Source ip address hash"`
- Syslog, authentication failure during the last 5m: `%syslog.timestamp >= %macros.past_5m && %syslog.msg ~ "@authentication failure@"`
- High memory usage: `%macros.device_up = "1" && %mempools.mempool_perc >= "90" && %mempools.mempool_descr = "Virtual@"`
- High CPU usage(per core usage, not overall): `%macros.device_up = "1" && %processors.processor_usage >= "90"`
- High port usage, where description is not client & ifType is not softwareLoopback: `%macros.port_usage_perc >= "80" && %port.port_descr_type != "client" && %ports.ifType != "softwareLoopback"`

## <a name="rules-procedure">Procedure</a>
You can associate a rule to a procedure by giving the URL of the procedure when creating the rule. Only links like "http://" are supported, otherwise an error will be returned. Once configured, procedure can be opened from the Alert widget through the "Open" button, which can be shown/hidden from the widget configuration box.

# <a name="templates">Templates</a>

Templates can be assigned to a single or a group of rules.
They can contain any kind of text.
The template-parser understands `if` and `foreach` controls and replaces certain placeholders with information gathered about the alert.

## <a name="templates-syntax">Syntax</a>

Controls:

- if-else (Else can be omitted):
`{if %placeholder == value}Some Text{else}Other Text{/if}`
- foreach-loop:
`{foreach %placeholder}Key: %key<br/>Value: %value{/foreach}`

Placeholders:

- Hostname of the Device: `%hostname`
- sysName of the Device: `%sysName`
- location of the Device: `%location`
- uptime of the Device (in seconds): `%uptime`
- short uptime of the Device (28d 22h 30m 7s): `%uptime_short`
- long uptime of the Device (28 days, 22h 30m 7s): `%uptime_long`
- description (purpose db field) of the Device: `%description`
- notes of the Device: `%notes`
- Title for the Alert: `%title`
- Time Elapsed, Only available on recovery (`%state == 0`): `%elapsed`
- Alert-ID: `%id`
- Unique-ID: `%uid`
- Faults, Only available on alert (`%state != 0`), must be iterated in a foreach (`{foreach %faults}`). Holds all available information about the Fault, accessible in the format `%value.Column`, for example: `%value.ifDescr`. Special field `%value.string` has most Identification-information (IDs, Names, Descrs) as single string, this is the equivalent of the default used.
- State: `%state`
- Severity: `%severity`
- Rule: `%rule`
- Rule-Name: `%name`
- Timestamp: `%timestamp`
- Transport name: `%transport`
- Contacts, must be iterated in a foreach, `%key` holds email and `%value` holds name: `%contacts`

Placeholders can be used within the subjects for templates as well although %faults is most likely going to be worthless.

> NOTE: Placeholder names which are contained within another need to be ordered correctly. As an example:

```text
Limit: %value.sensor_limit / %value.sensor_limit_low
```

Should be done as:

```text
Limit: %value.sensor_limit_low / %value.sensor_limit
```

The Default Template is a 'one-size-fit-all'. We highly recommend defining own templates for your rules to include more specific information.
Templates can be matched against several rules.

## <a name="templates-examples">Examples</a>

Default Template:
```text
%title\r\n
Severity: %severity\r\n
{if %state == 0}Time elapsed: %elapsed\r\n{/if}
Timestamp: %timestamp\r\n
Unique-ID: %uid\r\n
Rule: {if %name}%name{else}%rule{/if}\r\n
{if %faults}Faults:\r\n
{foreach %faults}  #%key: %value.string\r\n{/foreach}{/if}
Alert sent to: {foreach %contacts}%value <%key> {/foreach}
```

Conditional formatting example, will display a link to the host in email or just the hostname in any other transport:
```text
{if %transport == mail}<a href="https://my.librenms.install/device/device=%hostname/">%hostname</a>{else}%hostname{/if}
```

Note the use of double-quotes.  Single quotes (`'`) in templates will be escaped (replaced with `\'`) in the output and should therefore be avoided.

## <a name="templates-included">Included</a>

We include a few templates for you to use, these are specific to the type of alert rules you are creating. For example if you create a rule that would alert on BGP sessions then you can 
assign the BGP template to this rule to provide more information.

The included templates are:

  - BGP Sessions
  - Ports
  - Temperature

# <a name="transports">Transports</a>

Transports are located within `$config['install_dir']/includes/alerts/transports.*.php` and defined as well as configured via ~~`$config['alert']['transports']['Example'] = 'Some Options'`~~.

Contacts will be gathered automatically and passed to the configured transports.
By default the Contacts will be only gathered when the alert triggers and will ignore future changes in contacts for the incident. If you want contacts to be re-gathered before each dispatch, please set ~~`$config['alert']['fixed-contacts'] = false;`~~ in your config.php.

The contacts will always include the `SysContact` defined in the Device's SNMP configuration and also every LibreNMS-User that has at least `read`-permissions on the entity that is to be alerted.
At the moment LibreNMS only supports Port or Device permissions.
You can exclude the `SysContact` by setting:

```php
$config['alert']['syscontact'] = false;
```

To include users that have `Global-Read` or `Administrator` permissions it is required to add these additions to the `config.php` respectively:

```php
$config['alert']['globals'] = true; //Include Global-Read into alert-contacts
$config['alert']['admins']  = true; //Include Administrators into alert-contacts
```

## <a name="transports-email">E-Mail</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

For all but the default contact, we support setting multiple email addresses separated by a comma. So you can 
set the devices sysContact, override the sysContact or have your users emails set like:

`email@domain.com, alerting@domain.com`

E-Mail transport is enabled with adding the following to your `config.php`:
~
```php
$config['alert']['transports']['mail'] = true;
```
~~

The E-Mail transports uses the same email-configuration like the rest of LibreNMS.
As a small reminder, here is it's configuration directives including defaults:
~~
```php
$config['email_backend']                   = 'mail';               // Mail backend. Allowed: "mail" (PHP's built-in), "sendmail", "smtp".
$config['email_from']                      = NULL;                 // Mail from. Default: "ProjectName" <projectid@`hostname`>
$config['email_user']                      = $config['project_id'];
$config['email_sendmail_path']             = '/usr/sbin/sendmail'; // The location of the sendmail program.
$config['email_html']                      = FALSE;                // Whether to send HTML email as opposed to plaintext
$config['email_smtp_host']                 = 'localhost';          // Outgoing SMTP server name.
$config['email_smtp_port']                 = 25;                   // The port to connect.
$config['email_smtp_timeout']              = 10;                   // SMTP connection timeout in seconds.
$config['email_smtp_secure']               = NULL;                 // Enable encryption. Use 'tls' or 'ssl'
$config['email_smtp_auth']                 = FALSE;                // Whether or not to use SMTP authentication.
$config['email_smtp_username']             = NULL;                 // SMTP username.
$config['email_smtp_password']             = NULL;                 // Password for SMTP authentication.

$config['alert']['default_only']           = false;                //Only issue to default_mail
$config['alert']['default_mail']           = '';                   //Default email
```
~~

## <a name="transports-api">API</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

API transports definitions are a bit more complex than the E-Mail configuration.
The basis for configuration is ~~`$config['alert']['transports']['api'][METHOD]`~~ where `METHOD` can be `get`,`post` or `put`.
This basis has to contain an array with URLs of each API to call.
The URL can have the same placeholders as defined in the [Template-Syntax](#templates-syntax).
If the `METHOD` is `get`, all placeholders will be URL-Encoded.
The API transport uses cURL to call the APIs, therefore you might need to install `php5-curl` or similar in order to make it work.
__Note__: it is highly recommended to define own [Templates](#templates) when you want to use the API transport. The default template might exceed URL-length for GET requests and therefore cause all sorts of errors.

Example:
~~
```php
$config['alert']['transports']['api']['get'][] = "https://api.thirdparti.es/issue?apikey=abcdefg&subject=%title";
```
~~

## <a name="transports-nagios">Nagios Compatible</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

The nagios transport will feed a FIFO at the defined location with the same format that nagios would.
This allows you to use other Alerting-Systems to work with LibreNMS, for example [Flapjack](http://flapjack.io).
~~
```php
$config['alert']['transports']['nagios'] = "/path/to/my.fifo"; //Flapjack expects it to be at '/var/cache/nagios3/event_stream.fifo'
```
~~

## <a name="transports-irc">IRC</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

The IRC transports only works together with the LibreNMS IRC-Bot.
Configuration of the LibreNMS IRC-Bot is described [here](https://github.com/librenms/librenms/blob/master/doc/Extensions/IRC-Bot.md).
~~
```php
$config['alert']['transports']['irc'] = true;
```
~~

## <a name="transports-slack">Slack</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

[Using a proxy?](../Support/Configuration.md#proxy-support)

The Slack transport will POST the alert message to your Slack Incoming WebHook using the [attachments](https://api.slack.com/docs/message-attachments) option, you are able to specify multiple webhooks along with the relevant options to go with it. Simple html tags are stripped from the message. All options are optional, the only required value is for url, without this then no call to Slack will be made. Below is an example of how to send alerts to two channels with different customised options: 

~~
```php
$config['alert']['transports']['slack'][] = array('url' => "https://hooks.slack.com/services/A12B34CDE/F56GH78JK/L901LmNopqrSTUVw2w3XYZAB4C", 'channel' => '#Alerting');

$config['alert']['transports']['slack'][] = array('url' => "https://hooks.slack.com/services/A12B34CDE/F56GH78JK/L901LmNopqrSTUVw2w3XYZAB4C", 'channel' => '@john', 'username' => 'LibreNMS', 'icon_emoji' => ':ghost:');

```
~~

## <a name="transports-slack">Rocket.chat</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

The Rocket.chat transport will POST the alert message to your Rocket.chat Incoming WebHook using the [attachments](https://rocket.chat/docs/developer-guides/rest-api/chat/postmessage) option, you are able to specify multiple webhooks along with the relevant options to go with it. Simple html tags are stripped from the message. All options are optional, the only required value is for url, without this then no call to Rocket.chat will be made. Below is an example of how to send alerts to two channels with different customised options:

```php
$config['alert']['transports']['rocket'][] = array('url' => "https://rocket.url/api/v1/chat.postMessage", 'channel' => '#Alerting');

$config['alert']['transports']['rocket'][] = array('url' => "https://rocket.url/api/v1/chat.postMessage", 'channel' => '@john', 'username' => 'LibreNMS', 'icon_emoji' => ':ghost:');

```

## <a name="transports-hipchat">HipChat</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

[Using a proxy?](../Support/Configuration.md#proxy-support)

The HipChat transport requires the following:

__room_id__ = HipChat Room ID

__url__ = HipChat API URL+API Key

__from__ = The name that will be displayed

The HipChat transport makes the following optional:

__color__ = Any of HipChat's supported message colors

__message_format__ = Any of HipChat's supported message formats

__notify__ = 0 or 1

See the HipChat API Documentation for
[rooms/message](https://www.hipchat.com/docs/api/method/rooms/message)
for details on acceptable values.

> You may notice that the link points at the "deprecated" v1 API.  This is
> because the v2 API is still in beta.

Below are two examples of sending messages to a HipChat room.

~~
```php
$config['alert']['transports']['hipchat'][] = array("url" => "https://api.hipchat.com/v1/rooms/message?auth_token=9109jawregoaih",
                                                    "room_id" => "1234567",
                                                    "from" => "LibreNMS");

$config['alert']['transports']['hipchat'][] = array("url" => "https://api.hipchat.com/v1/rooms/message?auth_token=109jawregoaihj",
                                                    "room_id" => "7654321",
                                                    "from" => "LibreNMS",
                                                    "color" => "red",
                                                    "notify" => 1,
                                                    "message_format" => "text");
```
~~

> Note: The default message format for HipChat messages is HTML.  It is
> recommended that you specify the `text` message format to prevent unexpected
> results, such as HipChat attempting to interpret angled brackets (`<` and
> `>`).

## <a name="transports-pagerduty">PagerDuty</a>

> You can configure these options within the WebUI now, please avoid setting these options within config.php

[Using a proxy?](../Support/Configuration.md#proxy-support)

Enabling PagerDuty transports is almost as easy as enabling email-transports.

All you need is to create a Service with type Generic API on your PagerDuty dashboard.

Now copy your API-Key from the newly created Service and setup the transport like:

~~
```php
$config['alert']['transports']['pagerduty'] = 'MYAPIKEYGOESHERE';
```
~~

That's it!

__Note__: Currently ACK notifications are not transported to PagerDuty, This is going to be fixed within the next major version (version by date of writing: 2015.05)

## <a name="transports-pushover">Pushover</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

Enabling Pushover support is fairly easy, there are only two required parameters.

Firstly you need to create a new Application (called LibreNMS, for example) in your account on the Pushover website (https://pushover.net/apps)

Now copy your API Token/Key from the newly created Application and setup the transport in your config.php like:

~~
```php
$config['alert']['transports']['pushover'][] = array(
                                                    "appkey" => 'APPLICATIONAPIKEYGOESHERE',
                                                    "userkey" => 'USERKEYGOESHERE',
                                                    );
```
~~

To modify the Critical alert sound, add the 'sound_critical' parameter, example:

~~
```php
$config['alert']['transports']['pushover'][] = array(
                                                    "appkey" => 'APPLICATIONAPIKEYGOESHERE',
                                                    "userkey" => 'USERKEYGOESHERE',
                                                    "sound_critical" => 'siren',
                                                    );
```
~~

## <a name="transports-boxcar">Boxcar</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

Enabling Boxcar support is super easy.
Copy your access token from the Boxcar app or from the Boxcar.io website and setup the transport in your config.php like:

~~
```php
$config['alert']['transports']['boxcar'][] = array(
                                                    "access_token" => 'ACCESSTOKENGOESHERE',
                                                    );
```
~~

To modify the Critical alert sound, add the 'sound_critical' parameter, example:

~~
```php
$config['alert']['transports']['boxcar'][] = array(
                                                    "access_token" => 'ACCESSTOKENGOESHERE',
                                                    "sound_critical" => 'detonator-charge',
                                                    );
```
~~

## <a name="transports-telegram">Telegram</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

> Thank you to [snis](https://github.com/snis) for these instructions.

1. First you must create a telegram account and add BotFather to you list. To do this click on the following url: https://telegram.me/botfather

2. Generate a new bot with the command "/newbot" BotFather is then asking for a username and a normal name. After that your bot is created and you get a HTTP token. (for more options for your bot type "/help")

3. Add your bot to telegram with the following url: `http://telegram.me/<botname>` and send some text to the bot.

4. Now copy your token code and go to the following page in chrome: `https://api.telegram.org/bot<tokencode>/getUpdates`

5. You see a json code with the message you sent to the bot. Copy the Chat id. In this example that is “-9787468”
   `"message":{"message_id":7,"from":"id":656556,"first_name":"Joo","last_name":"Doo","username":"JohnDoo"},"chat":{"id":-9787468,"title":"Telegram Group"},"date":1435216924,"text":"Hi"}}]}`
   
6. Now create a new "Telegram transport" in LibreNMS (Global Settings -> Alerting Settings -> Telegram transport).
Click on 'Add Telegram config' and put your chat id and token into the relevant box.

## <a name="transports-pushbullet">Pushbullet</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

Enabling Pushbullet is a piece of cake.
Get your Access Token from your Pushbullet's settings page and set it in your config like:

~~
```php
$config['alert']['transports']['pushbullet'] = 'MYFANCYACCESSTOKEN';
```
~~

## <a name="transports-clickatell">Clickatell</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

Clickatell provides a REST-API requiring an Authorization-Token and at least one Cellphone number.
Please consult Clickatell's documentation regarding number formatting.
Here an example using 3 numbers, any amount of numbers is supported:

~~
```php
$config['alert']['transports']['clickatell']['token'] = 'MYFANCYACCESSTOKEN';
$config['alert']['transports']['clickatell']['to'][]  = '+1234567890';
$config['alert']['transports']['clickatell']['to'][]  = '+1234567891';
$config['alert']['transports']['clickatell']['to'][]  = '+1234567892';
```
~~

## <a name="transports-playsms">PlaySMS</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

PlaySMS is an open source SMS-Gateway that can be used via their HTTP-API using a Username and WebService-Token.
Please consult PlaySMS's documentation regarding number formatting.
Here an example using 3 numbers, any amount of numbers is supported:

~~
```php
$config['alert']['transports']['playsms']['url']   = 'https://localhost/index.php?app=ws';
$config['alert']['transports']['playsms']['user']  = 'user1';
$config['alert']['transports']['playsms']['token'] = 'MYFANCYACCESSTOKEN';
$config['alert']['transports']['playsms']['from']  = '+1234567892'; //Optional
$config['alert']['transports']['playsms']['to'][]  = '+1234567890';
$config['alert']['transports']['playsms']['to'][]  = '+1234567891';
```
~~

## <a name="transports-victorops">VictorOps</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

VictorOps provide a webHook url to make integration extremely simple. To get the URL required login to your VictorOps account and go to:

Settings -> Integrations -> REST Endpoint -> Enable Integration.

The URL provided will have $routing_key at the end, you need to change this to something that is unique to the system sending the alerts such as librenms. I.e:

`https://alert.victorops.com/integrations/generic/20132414/alert/2f974ce1-08fc-4dg8-a4f4-9aee6cf35c98/librenms`

~~
```php
$config['alert']['transports']['victorops']['url'] = 'https://alert.victorops.com/integrations/generic/20132414/alert/2f974ce1-08fc-4dg8-a4f4-9aee6cf35c98/librenms';
```
~~

## <a name="transports-canopsis">Canopsis</a>

Canopsis is a hypervision tool. LibreNMS can send alerts to Canopsis which are then converted to canopsis events. To configure the transport, go to:

Global Settings -> Alerting Settings -> Canopsis Transport.

You will need to fill this paramaters :

~~
```php
$config['alert']['transports']['canopsis']['host'] = 'www.xxx.yyy.zzz';
$config['alert']['transports']['canopsis']['port'] = '5672';
$config['alert']['transports']['canopsis']['user'] = 'admin';
$config['alert']['transports']['canopsis']['passwd'] = 'my_password';
$config['alert']['transports']['canopsis']['vhost'] = 'canopsis';
```
~~

For more information about canopsis and its events, take a look here :
 http://www.canopsis.org/
 http://www.canopsis.org/wp-content/themes/canopsis/doc/sakura/user-guide/event-spec.html

## <a name="transports-osticket">osTicket</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

osTicket, open source ticket system. LibreNMS can send alerts to osTicket API which are then converted to osTicket tickets. To configure the transport, go to:

Global Settings -> Alerting Settings -> osTicket Transport.

This can also be done manually in config.php :

~~
```php
$config['alert']['transports']['osticket']['url'] = 'http://osticket.example.com/api/http.php/tickets.json';
$config['alert']['transports']['osticket']['token'] = '123456789';
```
~~

## <a name="transports-msteams">Microsoft Teams</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

Microsoft Teams. LibreNMS can send alerts to Microsoft Teams Connector API which are then posted to a specific channel. To configure the transport, go to:

Global Settings -> Alerting Settings -> Microsoft Teams Transport.

This can also be done manually in config.php :

~
```php
$config['alert']['transports']['msteams']['url'] = 'https://outlook.office365.com/webhook/123456789';
```
~

## <a name="transports-ciscospark">Cisco Spark</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)


Cisco Spark. LibreNMS can send alerts to a Cisco Spark room. To make this possible you need to have a RoomID and a token. 

For more information about Cisco Spark RoomID and token, take a look here :
 https://developer.ciscospark.com/getting-started.html
 https://developer.ciscospark.com/resource-rooms.html

To configure the transport, go to:

Global Settings -> Alerting Settings -> Cisco Spark transport.

This can also be done manually in config.php :

~
```php
$config['alert']['transports']['ciscospark']['token'] = '1234567890QWERTYUIOP';
$config['alert']['transports']['ciscospark']['roomid'] = '1234567890QWERTYUIOP';
```
~

## <a name="transports-smseagle">SMSEagle</a>

[Using a proxy?](../Support/Configuration.md#proxy-support)

SMSEagle is a hardware SMS Gateway that can be used via their HTTP-API using a Username and password
Please consult their documentation at [www.smseagle.eu](http://www.smseagle.eu)
Destination numbers are one per line, with no spaces. They can be in either local or international dialling format.

~
```php
$config['alert']['transports']['smseagle']['url']   = 'ip.add.re.ss';
$config['alert']['transports']['smseagle']['user']  = 'smseagle_user';
$config['alert']['transports']['smseagle']['token'] = 'smseagle_user_password';
$config['alert']['transports']['smseagle']['to'][]  = '+3534567890';
$config['alert']['transports']['smseagle']['to'][]  = '0834567891';
```
~

## <a name="transports-syslog">Syslog</a>

You can have LibreNMS emit alerts as syslogs complying with RFC 3164.
More information on RFC 3164 can be found here: https://tools.ietf.org/html/rfc3164
Example output: `<26> Mar 22 00:59:03 librenms.host.net librenms[233]: [Critical] network.device.net: Port Down - port_id => 98939; ifDescr => xe-1/1/0;`
Each fault will be sent as a separate syslog.

~
```php
$config['alert']['transports']['syslog']['syslog_host']   = '127.0.0.1';
$config['alert']['transports']['syslog']['syslog_port']  = 514;
$config['alert']['transports']['syslog']['syslog_facility'] = 3;
```
~

# <a name="entities">Entities

Entities as described earlier are based on the table and column names within the database, if you are unsure of what the entity is you want then have a browse around inside MySQL using `show tables` and `desc <tablename>`.

## <a name="entity-devices">Devices</a>

__devices.hostname__ = The devices hostname.

__devices.location__ = The devices location.

__devices.status__ = The status of the device, 1 = up, 0 = down.

__devices.status_reason__ = The reason the device was detected as down (icmp or snmp).

__devices.ignore__ = If the device is ignored this will be set to 1.

__devices.disabled__ = If the device is disabled this will be set to 1.

__devices.last_polled__ = The the last polled datetime (yyyy-mm-dd hh:mm:ss).

__devices.type__ = The device type such as network, server, firewall, etc.

## <a name="entity-bgppeers">BGP Peers</a>

__bgpPeers.astext__ = This is the description of the BGP Peer.

__bgpPeers.bgpPeerIdentifier__ = The IP address of the BGP Peer.

__bgpPeers.bgpPeerRemoteAs__ = The AS number of the BGP Peer.

__bgpPeers.bgpPeerState__ = The operational state of the BGP session.

__bgpPeers.bgpPeerAdminStatus__ = The administrative state of the BGP session.

__bgpPeers.bgpLocalAddr__ = The local address of the BGP session.

## <a name="entity-ipsec">IPSec Tunnels</a>

__ipsec_tunnels.peer_addr__ = The remote VPN peer address.

__ipsec_tunnels.local_addr__ = The local VPN address.

__ipsec_tunnels.tunnel_status__ = The VPN tunnels operational status.

## <a name="entity-mempools">Memory pools</a>

__mempools.mempool_type__ = The memory pool type such as hrstorage, cmp and cemp.

__mempools.mempool_descr__ = The description of the pool such as Physical memory, Virtual memory and System memory.

__mempools.mempool_perc__ = The used percentage of the memory pool.

## <a name="entity-ports">Ports</a>

__ports.ifDescr__ = The interface description.

__ports.ifName__ = The interface name.

__ports.ifSpeed__ = The port speed in bps.

__ports.ifHighSpeed__ = The port speed in mbps.

__ports.ifOperStatus__ = The operational status of the port (up or down).

__ports.ifAdminStatus__ = The administrative status of the port (up or down).

__ports.ifDuplex__ = Duplex setting of the port.

__ports.ifMtu__ = The MTU setting of the port.

## <a name="entity-processors">Processors</a>

__processors.processor_usage__ = The usage of the processor as a percentage.

__processors.processor_descr__ = The description of the processor.

## <a name="entity-storage">Storage</a>

__storage.storage_descr__ = The description of the storage.

__storage.storage_perc__ = The usage of the storage as a percentage.

# <a name="macros">Macros</a>

Macros are shorthands to either portion of rules or pure SQL enhanced with placeholders.
You can define your own macros in your `config.php`.

Example macro-implementation of Debian-Devices
```php
$config['alert']['macros']['rule']['is_debian'] = '%devices.features ~ "@debian@"';
```
And in the Rule:
```
...  && %macros.is_debian = "1" && ...
```

This Example-macro is a Boolean-macro, it applies a form of filter to the set of results defined by the rule.
All macros that are not unary should return Boolean.

You can only apply _Equal_ or _Not-Equal_ Operations on Boolean-macros where `True` is represented by `"1"` and `False` by `"0"`.

Note, if using a /, spaces must be inserted around it.

Example 
```php
((%ports.ifInOctets_rate*8) / %ports.ifSpeed)*100
```

## <a name="macros-device">Device</a> (Boolean)

Entity: `%macros.device`

Description: Only select devices that aren't deleted, ignored or disabled.

Source: `(%devices.disabled = "0" && %devices.ignore = "0")`

### <a name="macros-device-up">Device is up</a> (Boolean)

Entity: `%macros.device_up`

Description: Only select devices that are up.

Implies: %macros.device

Source: `(%devices.status = "1" && %macros.device)`

### <a name="macros-device-down">Device is down</a> (Boolean)

Entity: `%macros.device_down`

Description: Only select devices that are down.

Implies: %macros.device

Source: `(%devices.status = "0" && %macros.device)`

## <a name="macros-port">Port</a> (Boolean)

Entity: `%macros.port`

Description: Only select ports that aren't deleted, ignored or disabled.

Source: `(%ports.deleted = "0" && %ports.ignore = "0" && %ports.disabled = "0")`

### <a name="macros-port-up">Port is up</a> (Boolean)

Entity: `%macros.port_up`

Description: Only select ports that are up and also should be up.

Implies: %macros.port

Source: `(%ports.ifOperStatus = "up" && %ports.ifAdminStatus = "up" && %macros.port)`

### <a name="macros-port-down">Port is down</a> (Boolean)

Entity: `%macros.port_down`

Description: Only select ports that are down.

Implies: %macros.port

Source: `(%ports.ifOperStatus = "down" && %ports.ifAdminStatus != "down" && %macros.port)`

### <a name="macros-port-usage-perc">Port-Usage in Percent</a> (Decimal)

Entity: `%macros.port_usage_perc`

Description: Return port-usage in percent.

Source: `((%ports.ifInOctets_rate*8) / %ports.ifSpeed)*100`

## <a name="macros-time">Time</a>

### <a name="macros-time-now">Now</a> (Datetime)

Entity: `%macros.now`

Description: Alias of MySQL's NOW()

Source: `NOW()`

### <a name="macros-time-past-Nm">Past N Minutes</a> (Datetime)

Entity: `%macros.past_$m`

Description: Returns a MySQL Timestamp dated `$` Minutes in the past. `$` can only be a supported Resolution.

Example: `%macros.past_5m` is Last 5 Minutes.

Resolution: 5,10,15,30,60

Source: `DATE_SUB(NOW(),INTERVAL $ MINUTE)`

## <a name="macros-sensors">Sensors</a> (Boolean)

Entity: `%macros.sensor`

Description: Only select sensors that aren't ignored.

Source: `(%sensors.sensor_alert = 1)`

## <a name="macros-misc">Misc</a> (Boolean)

### Packet Loss

Entity: `(%macros.packet_loss_5m)`

Description: Packet loss % value for the device within the last 5 minutes.

Example: `%macros.packet_loss_5m` > 50

Entity: `(%macros.packet_loss_15m)`

Description: Packet loss % value for the device within the last 15 minutes.

Example: `%macros.packet_loss_15m` > 50

### Ports in usage perc (Int)

Entity: `((%ports.ifInOctets_rate*8)/%ports.ifSpeed)*100`

Description: Port in used more than 50%

Example: `%macros.port_in_usage_perc > 50

### Ports out usage perc (Int)

Entity: `((%ports.ifOutOctets_rate*8)/%ports.ifSpeed)*100`

Description: Port out used more than 50%

Example: `%macros.port_out_usage_perc > 50

### Ports now down (Boolean)

Entity: `%ports.ifOperStatus != %ports.ifOperStatus_prev && %ports.ifOperStatus_prev = "up" && %ports.ifAdminStatus = "up"`

Description: Ports that were previously up and have now gone down.

Example: `%macros.port_now_down = "1"`

### Device component down [JunOS]

Entity: `%sensors.sensor_class = "state" && %sensors.sensor_current != "6" && %sensors.sensor_type = "jnxFruState" && %sensors.sensor_current != "2"`

Description: Device component is down such as Fan, PSU, etc for JunOS devices.

Example: `%macros.device_component_down_junos = "1"`

### Device component down [Cisco]

Entity: `%sensors.sensor_current != "1" && %sensors.sensor_current != "5" && %sensors.sensor_type ~ "^cisco.*State$"`

Description: Device component is down such as Fan, PSU, etc for Cisco devices.

Example: `%macros.device_component_down_cisco = "1"`

### PDU over amperage [APC]

Entity: `%sensors.sensor_class = "current" && %sensors.sensor_descr = "Bank Total" && %sensors.sensor_current > %sensors.sensor_limit && %devices.os = "apc"`

Description: APC PDU over amperage

Example: `%macros.pdu_over_amperage_apc = "1"`

# <a name="extra">Additional Options</a>

Here are some of the other options available when adding an alerting rule:

- Rule name: The name associated with the rule.
- Severity: How "important" the rule is.
- Max alerts: The maximum number of alerts sent for the event.  `-1` means unlimited.
- Delay: The amount of time in seconds to wait after a rule is matched before sending an alert.
- Interval: The interval of time in seconds between alerts for an event until Max is reached.
- Mute alerts: Disable sending alerts for this rule.
- Invert match: Invert the matching rule (ie. alert on items that _don't_ match the rule).
