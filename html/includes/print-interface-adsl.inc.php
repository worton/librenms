<?php

// This file prints a table row for each interface
$port['device_id'] = $device['device_id'];
$port['hostname']  = $device['hostname'];

$if_id = $port['port_id'];

$port = ifLabel($port);

if (!is_integer($i / 2)) {
    $row_colour = $list_colour_a;
} else {
    $row_colour = $list_colour_b;
}

if ($port['ifInErrors_delta'] > 0 || $port['ifOutErrors_delta'] > 0) {
    $error_img = generate_port_link($port, "<i class='fa fa-flag fa-lg' style='color:red' aria-hidden='true'></i>", 'port_errors');
} else {
    $error_img = '';
}

echo "<tr style=\"background-color: $row_colour; padding: 5px;\" valign=top onmouseover=\"this.style.backgroundColor='$list_highlight';\" onmouseout=\"this.style.backgroundColor='$row_colour';\"
onclick=\"location.href='device/".$device['device_id'].'/port/'.$port['port_id']."/'\" style='cursor: pointer;'>
 <td valign=top width=350>";
echo '        <span class=list-large>
              '.generate_port_link($port, $port['ifIndex'].'. '.$port['label']).'
           </span><br /><span class=interface-desc>'.display($port['ifAlias']).'</span>';

if ($port['ifAlias']) {
    echo '<br />';
}

unset($break);
if ($port_details) {
    foreach (dbFetchRows('SELECT * FROM `ipv4_addresses` WHERE `port_id` = ?', array($port['port_id'])) as $ip) {
        echo "$break <a class=interface-desc href=\"javascript:popUp('netcmd.php?cmd=whois&amp;query=".$ip['ipv4_address']."')\">".$ip['ipv4_address'].'/'.$ip['ipv4_prefixlen'].'</a>';
        $break = ',';
    }

    foreach (dbFetchRows('SELECT * FROM `ipv6_addresses` WHERE `port_id` = ?', array($port['port_id'])) as $ip6) {
        ;
        echo "$break <a class=interface-desc href=\"javascript:popUp('netcmd.php?cmd=whois&amp;query=".$ip6['ipv6_address']."')\">".Net_IPv6::compress($ip6['ipv6_address']).'/'.$ip6['ipv6_prefixlen'].'</a>';
        $break = ',';
    }
}

echo '</span>';

$width  = '120';
$height = '40';
$from   = $config['time']['day'];

echo '</td><td width=135>';
echo (formatRates(($port['ifInOctets_rate'] * 8))." <i class='fa fa-arrows-v fa-lg icon-theme' aria-hidden='true'></i> ".formatRates(($port['ifOutOctets_rate'] * 8)));
echo '<br />';
$port['graph_type'] = 'port_bits';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td><td width=135>';
echo ''.formatRates($port['adslAturChanCurrTxRate']).'/'.formatRates($port['adslAtucChanCurrTxRate']);
echo '<br />';
$port['graph_type'] = 'port_adsl_speed';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td><td width=135>';
echo ''.formatRates($port['adslAturCurrAttainableRate']).'/'.formatRates($port['adslAtucCurrAttainableRate']);
echo '<br />';
$port['graph_type'] = 'port_adsl_attainable';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td><td width=135>';
echo ''.$port['adslAturCurrAtn'].'dB/'.$port['adslAtucCurrAtn'].'dB';
echo '<br />';
$port['graph_type'] = 'port_adsl_attenuation';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td><td width=135>';
echo ''.$port['adslAturCurrSnrMgn'].'dB/'.$port['adslAtucCurrSnrMgn'].'dB';
echo '<br />';
$port['graph_type'] = 'port_adsl_snr';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td><td width=135>';
echo ''.$port['adslAturCurrOutputPwr'].'dBm/'.$port['adslAtucCurrOutputPwr'].'dBm';
echo '<br />';
$port['graph_type'] = 'port_adsl_power';
echo generate_port_link(
    $port,
    "<img src='graph.php?type=".$port['graph_type'].'&amp;id='.$port['port_id'].'&amp;from='.$from.'&amp;to='.$config['time']['now'].'&amp;width='.$width.'&amp;height='.$height.'&amp;legend=no&amp;bg='.str_replace('#', '', $row_colour)."'>",
    $port['graph_type']
);

echo '</td>';
