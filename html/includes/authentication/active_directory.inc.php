<?php

// easier to rewrite for Active Directory than to bash it into existing LDAP implementation

// disable certificate checking before connect if required
if (isset($config['auth_ad_check_certificates']) &&
          !$config['auth_ad_check_certificates']) {
    putenv('LDAPTLS_REQCERT=never');
};

if (isset($config['auth_ad_debug']) && $config['auth_ad_debug']) {
    ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);
}

$ldap_connection = @ldap_connect($config['auth_ad_url']);

// disable referrals and force ldap version to 3

ldap_set_option($ldap_connection, LDAP_OPT_REFERRALS, 0);
ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);

function authenticate($username, $password)
{
    global $config, $ldap_connection, $auth_error;

    if ($ldap_connection) {
        // bind with sAMAccountName instead of full LDAP DN
        if ($username && $password && ldap_bind($ldap_connection, "{$username}@{$config['auth_ad_domain']}", $password)) {
            // group membership in one of the configured groups is required
            if (isset($config['auth_ad_require_groupmembership']) &&
                $config['auth_ad_require_groupmembership']) {
                $search = ldap_search(
                    $ldap_connection,
                    $config['auth_ad_base_dn'],
                    get_auth_ad_user_filter($username),
                    array('memberOf')
                );
                $entries = ldap_get_entries($ldap_connection, $search);
                unset($entries[0]['memberof']['count']); //remove the annoying count

                foreach ($entries[0]['memberof'] as $entry) {
                    $group_cn = get_cn($entry);
                    if (isset($config['auth_ad_groups'][$group_cn]['level'])) {
                        // user is in one of the defined groups
                        adduser($username);
                        return 1;
                    }
                }

                if (isset($config['auth_ad_debug']) && $config['auth_ad_debug']) {
                    if ($entries['count'] == 0) {
                        $auth_error = 'No groups found for user, check base dn';
                    } else {
                        $auth_error = 'User is not in one of the required groups';
                    }
                } else {
                    $auth_error = 'Invalid credentials';
                }

                return 0;
            } else {
                // group membership is not required and user is valid
                adduser($username);
                return 1;
            }
        }
    }

    if (!isset($password) || $password == '') {
        $auth_error = "A password is required";
    } elseif (isset($config['auth_ad_debug']) && $config['auth_ad_debug']) {
        ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
        $auth_error = ldap_error($ldap_connection).'<br />'.$extended_error;
    } else {
        $auth_error = ldap_error($ldap_connection);
    }

    return 0;
}

function reauthenticate()
{
    // not supported so return 0
    return 0;
}


function passwordscanchange()
{
    // not supported so return 0
    return 0;
}


function changepassword()
{
    // not supported so return 0
    return 0;
}


function auth_usermanagement()
{
    // not supported so return 0
    return 0;
}


function adduser($username, $level = 0, $email = '', $realname = '', $can_modify_passwd = 0, $description = '', $twofactor = 0)
{
    // Check to see if user is already added in the database
    if (!user_exists_in_db($username)) {
        $userid = dbInsert(array('username' => $username, 'realname' => $realname, 'email' => $email, 'descr' => $description, 'level' => $level, 'can_modify_passwd' => $can_modify_passwd, 'twofactor' => $twofactor, 'user_id' => get_userid($username)), 'users');
        if ($userid == false) {
            return false;
        } else {
            foreach (dbFetchRows('select notifications.* from notifications where not exists( select 1 from notifications_attribs where notifications.notifications_id = notifications_attribs.notifications_id and notifications_attribs.user_id = ?) order by notifications.notifications_id desc', array($userid)) as $notif) {
                dbInsert(array('notifications_id'=>$notif['notifications_id'],'user_id'=>$userid,'key'=>'read','value'=>1), 'notifications_attribs');
            }
        }
        return $userid;
    } else {
        return false;
    }
}

function user_exists_in_db($username)
{
    $return = dbFetchCell('SELECT COUNT(*) FROM users WHERE username = ?', array($username), true);
    return $return;
}

function user_exists($username)
{
    global $config, $ldap_connection;

    $search = ldap_search(
        $ldap_connection,
        $config['auth_ad_base_dn'],
        get_auth_ad_user_filter($username),
        array('samaccountname')
    );
    $entries = ldap_get_entries($ldap_connection, $search);


    if ($entries['count']) {
        return 1;
    }

    return 0;
}


function get_userlevel($username)
{
    global $config, $ldap_connection;

    $userlevel = 0;
    if (isset($config['auth_ad_require_groupmembership']) && $config['auth_ad_require_groupmembership'] == 0) {
        if (isset($config['auth_ad_global_read']) && $config['auth_ad_global_read'] === 1) {
            $userlevel = 5;
        }
    }

    // Find all defined groups $username is in
    $search = ldap_search(
        $ldap_connection,
        $config['auth_ad_base_dn'],
        get_auth_ad_user_filter($username),
        array('memberOf')
    );
    $entries = ldap_get_entries($ldap_connection, $search);
    unset($entries[0]['memberof']['count']);

    // Loop the list and find the highest level
    foreach ($entries[0]['memberof'] as $entry) {
        $group_cn = get_cn($entry);
        if (isset($config['auth_ad_groups'][$group_cn]['level']) &&
             $config['auth_ad_groups'][$group_cn]['level'] > $userlevel) {
            $userlevel = $config['auth_ad_groups'][$group_cn]['level'];
        }
    }

    return $userlevel;
}


function get_userid($username)
{
    global $config, $ldap_connection;

    $attributes = array('objectsid');
    $search = ldap_search(
        $ldap_connection,
        $config['auth_ad_base_dn'],
        get_auth_ad_user_filter($username),
        $attributes
    );
    $entries = ldap_get_entries($ldap_connection, $search);

    if ($entries['count']) {
        return preg_replace('/.*-(\d+)$/', '$1', sid_from_ldap($entries[0]['objectsid'][0]));
    }

    return -1;
}


function deluser($userid)
{
    dbDelete('bill_perms', '`user_id` =  ?', array($userid));
    dbDelete('devices_perms', '`user_id` =  ?', array($userid));
    dbDelete('ports_perms', '`user_id` =  ?', array($userid));
    dbDelete('users_prefs', '`user_id` =  ?', array($userid));
    dbDelete('users', '`user_id` =  ?', array($userid));
    return dbDelete('users', '`user_id` =  ?', array($userid));
}


function get_userlist()
{
    global $config, $ldap_connection;
    $userlist = array();
    $userhash = array();

    $ldap_groups = get_group_list();

    foreach ($ldap_groups as $ldap_group) {
        $search_filter = "(memberOf=$ldap_group)";
        if ($config['auth_ad_user_filter']) {
            $search_filter = "(&{$config['auth_ad_user_filter']}$search_filter)";
        }
        $search = ldap_search($ldap_connection, $config['auth_ad_base_dn'], $search_filter, array('samaccountname','displayname','objectsid','mail'));
        $results = ldap_get_entries($ldap_connection, $search);

        foreach ($results as $result) {
            if (isset($result['samaccountname'][0])) {
                $userid = preg_replace(
                    '/.*-(\d+)$/',
                    '$1',
                    sid_from_ldap($result['objectsid'][0])
                );

                // don't make duplicates, user may be member of more than one group
                $userhash[$result['samaccountname'][0]] = array(
                    'realname' => $result['displayName'][0],
                    'user_id'  => $userid,
                    'email'    => $result['mail'][0]
                );
            }
        }
    }

    foreach (array_keys($userhash) as $key) {
        $userlist[] = array(
            'username' => $key,
            'realname' => $userhash[$key]['realname'],
            'user_id'  => $userhash[$key]['user_id'],
            'email'    => $userhash[$key]['email']
        );
    }

    return $userlist;
}


function can_update_users()
{
    // not supported so return 0
    return 0;
}


function get_user($user_id)
{
    // not supported so return 0
    return dbFetchRow('SELECT * FROM `users` WHERE `user_id` = ?', array($user_id), true);
}


function update_user($user_id, $realname, $level, $can_modify_passwd, $email)
{
    dbUpdate(array('realname' => $realname, 'can_modify_passwd' => $can_modify_passwd, 'email' => $email), 'users', '`user_id` = ?', array($user_id));
}


function get_fullname($username)
{
    global $config, $ldap_connection;

    $attributes = array('name');
    $result = ldap_search(
        $ldap_connection,
        $config['auth_ad_base_dn'],
        get_auth_ad_user_filter($username),
        $attributes
    );
    $entries = ldap_get_entries($ldap_connection, $result);
    if ($entries['count'] > 0) {
        $membername = $entries[0]['name'][0];
    } else {
        $membername = $username;
    }

    return $membername;
}


function get_group_list()
{
    global $config;

    $ldap_groups   = array();

    // show all Active Directory Users by default
    $default_group = 'Users';

    if (isset($config['auth_ad_group'])) {
        if ($config['auth_ad_group'] !== $default_group) {
            $ldap_groups[] = $config['auth_ad_group'];
        }
    }

    if (!isset($config['auth_ad_groups']) && !isset($config['auth_ad_group'])) {
        $ldap_groups[] = get_dn($default_group);
    }

    foreach ($config['auth_ad_groups'] as $key => $value) {
        $ldap_groups[] = get_dn($key);
    }

    return $ldap_groups;
}

function get_dn($samaccountname)
{
    global $config, $ldap_connection;


    $attributes = array('dn');
    $result = ldap_search(
        $ldap_connection,
        $config['auth_ad_base_dn'],
        get_auth_ad_group_filter($samaccountname),
        $attributes
    );
    $entries = ldap_get_entries($ldap_connection, $result);
    if ($entries['count'] > 0) {
        return $entries[0]['dn'];
    } else {
        return '';
    }
}

function get_cn($dn)
{
    $dn = str_replace('\\,', '~C0mmA~', $dn);
    preg_match('/[^,]*/', $dn, $matches, PREG_OFFSET_CAPTURE, 3);
    return str_replace('~C0mmA~', ',', $matches[0][0]);
}

function sid_from_ldap($sid)
{
        $sidUnpacked = unpack('H*hex', $sid);
        $sidHex = array_shift($sidUnpacked);
        $subAuths = unpack('H2/H2/n/N/V*', $sid);
        $revLevel = hexdec(substr($sidHex, 0, 2));
        $authIdent = hexdec(substr($sidHex, 4, 12));
        return 'S-'.$revLevel.'-'.$authIdent.'-'.implode('-', $subAuths);
}
