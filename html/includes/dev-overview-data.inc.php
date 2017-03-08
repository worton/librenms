<?php

echo '<div class="container-fluid">';
echo "<div class='row'>
      <div class='col-md-12'>
          <div class='panel panel-default panel-condensed'>
            <div class='panel-heading'>";

if ($config['overview_show_sysDescr']) {
    echo '<i class="fa fa-id-card fa-lg icon-theme" aria-hidden="true"></i> <strong>'.$device['sysDescr'].'</strong>';
}

echo '</div>
      <table class="table table-hover table-condensed table-striped">';

$uptime = $device['uptime'];

if ($device['os'] == 'ios') {
    formatCiscoHardware($device);
}

if ($device['features']) {
    $device['features'] = '('.$device['features'].')';
}

$device['os_text'] = $config['os'][$device['os']]['text'];

echo '<tr>
        <td>System Name</td>
        <td>'.$device['sysName'].' </td>
      </tr>';

if (!empty($device['ip'])) {
     echo "<tr><td>Resolved IP</td><td>{$device['ip']}</td></tr>";
} elseif ($config['force_ip_to_sysname'] === true && is_ip($device['hostname']) === true) {
     echo "<tr><td>IP Address</td><td>{$device['hostname']}</td></tr>";
}

if ($device['hardware']) {
    echo '<tr>
        <td>Hardware</td>
        <td>'.$device['hardware'].'</td>
      </tr>';
}

echo '<tr>
        <td>Operating System</td>
        <td>'.$device['os_text'].' '.$device['version'].' '.$device['features'].' </td>
      </tr>';

if ($device['serial']) {
    echo '<tr>
        <td>Serial</td>
        <td>'.$device['serial'].'</td>
      </tr>';
}

if ($device['sysObjectID']) {
    echo '<tr>
        <td>Object ID</td>
        <td>'.$device['sysObjectID'].'</td>
      </tr>';
}

if ($device['sysContact']) {
    echo '<tr>
        <td>Contact</td>';
    if (get_dev_attrib($device, 'override_sysContact_bool')) {
        echo '
        <td>'.htmlspecialchars(get_dev_attrib($device, 'override_sysContact_string')).'</td>
      </tr>
      <tr>
        <td>SNMP Contact</td>';
    }

    echo '
        <td>'.htmlspecialchars($device['sysContact']).'</td>
      </tr>';
}

if ($device['location']) {
    echo '<tr>
        <td>Location</td>
        <td>'.$device['location'].'</td>
      </tr>';
    if (get_dev_attrib($device, 'override_sysLocation_bool') && !empty($device['real_location'])) {
        echo '<tr>
        <td>SNMP Location</td>
        <td>'.$device['real_location'].'</td>
      </tr>';
    }
}

$loc = parse_location($device['location']);
if (!is_array($loc)) {
    $loc = dbFetchRow("SELECT `lat`,`lng` FROM `locations` WHERE `location`=? LIMIT 1", array($device['location']));
}
if (is_array($loc)) {
    echo '<tr>
        <td>Lat / Lng</td>
        <td>['.$loc['lat'].','.$loc['lng'].'] <div class="pull-right"><a href="https://maps.google.com/?q='.$loc['lat'].'+'.$loc['lng'].'" target="_blank" class="btn btn-success btn-xs" role="button"><i class="fa fa-map-marker" style="color:white" aria-hidden="true"></i> Map</button></div></a></td>
    </tr>';
}

if ($uptime) {
    echo '<tr>
        <td>Uptime</td>
        <td>'.formatUptime($uptime).'</td>
      </tr>';
}

echo '</table>
      </div>
      </div>
      </div>
      </div>';
