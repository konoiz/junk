<?php
define("RCODE_NOERROR", 0);
define("RCODE_FORMERR", 1);
define("RCODE_SERVFAIL", 2);
define("RCODE_NXDOMAIN", 3);
define("RCODE_NOTIMP", 4);
define("RCODE_REFUSED", 5);
define("RCODE_YXDOMAIN", 6);
define("ROCDE_YXRRSET", 7);
define("RCODE_NXRRSET", 8);
define("RCODE_NOTAUTH", 9);
define("RCODE_NOTZONE", 10);
define("RCODE_BADVERS", 16);
define("RCODE_BADSIG", 16);
define("RCODE_BADKEY", 17);
define("RCODE_BADTIME", 18);
define("RCODE_BADMODE", 19);
define("RCODE_BADNAME", 20);
define("RCODE_BADALG", 21);

// database
define("DB_HOST", "127.0.0.1");
define("DB_USER", "dns");
define("DB_PASS", "****");
define("DB_NAME", "dns");

// open hosts list file
$file = $argv[1];
$fp = fopen($file, 'r');

$from = (!empty($argv[2]) && ctype_digit($argv[2])) ? $argv[2] : 1;

echo $from . "\n";

if ($fp === false) {
    echo "Initializetion error: cannnot open file {$file}\n";
    exit();
}

// open database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_errno) {
    echo "Initializetion error: {$db->connect_error}\n";
    exit(); 
}

// main loop
$pid = 0;
while (($host = fgets($fp, 1024)) !== false) {
    ++$pid;

    $host = trim($host);   // remove \r\n

    if ($pid < $from) {
        echo "[{$pid}] {$host}: skipped.\n";
        continue;
    }

    echo "[{$pid}] {$host}:\n";

    // is existing host?
    echo "  Check......";
    if (isExistHost($host, $db) !== false) {
        echo "FOUND!\n";
        continue;
    } else {
        echo "not found.\n";
    }

    echo "  Get NS records......";
    $output = getNsRecords($host, $db);
    echo "done.\n";
    
    // put host
    echo "  Insert {$host}......";
    if ($host_id = putHostDb($host, $output['status'], $db)) {
        echo "ok.({$host_id})\n";
    } else {
        echo "ERROR!\n";
        abort($db);
    }

    // check rcode
    if ($output['status'] !== RCODE_NOERROR) {
        echo "  RCODE: {$output['status']}, skipped.\n";
        continue;
    }
    
    // put authority
    foreach ($output['answer'] as $value) {
        echo "    Check {$value[3]}......";
        if (($auth_id = isExistAuth($value[3], $db)) !== false) {
            echo "FOUND! ({$auth_id})\n";
        } else {
            echo "not found.\n";
            echo "    Insert {$value[3]}......";
            if ($auth_id = putAuthDb($host_id, $value[3], $db)) {
                echo "ok. ({$auth_id})\n";
            } else {
                echo "ERROR!\n";
                abort($db);
            }
        }
        echo "  Save relation......";
        if (putHostAuthDb($host_id, $auth_id, $db)) {
            echo "ok.\n";
        } else {
            echo "ERROR!\n";
            abort($db);
        }
    }

    usleep(250000);
}

$db->close();
echo "DONE.\n";

function putHostAuthDb($host_id, $auth_id, $db) {
    $host_id = $db->real_escape_string($host_id);
    $auth_id = $db->real_escape_string($auth_id);
    $sql = "INSERT INTO `host_auth_list` (`host_id`, `auth_id`) VALUES ({$host_id}, {$auth_id})";

    if ($db->query($sql) === false) {
        return false;
    }

    return true;
}

function putAuthDb($host_id, $addr, $db) {
    $host_id = $db->real_escape_string($host_id);
    $addr    = $db->real_escape_string($addr);
    $sql = "INSERT INTO `authority` (`host_id`, `address`) VALUES ({$host_id}, '{$addr}')";

    if ($db->query($sql) === false) {
        return false;
    }

    return $db->insert_id;
}

function putHostDb($host, $rcode, $db) {
    $host  = $db->real_escape_string($host);
    $rcode = $db->real_escape_string($rcode);
    $sql   = "INSERT INTO `host` (`host_name`, `rcode`) VALUES ('{$host}', {$rcode})";

    if ($db->query($sql) === false) {
        return false;
    }

    return $db->insert_id;
}

function isExistAuth($ns, $db) {
    $ns  = $db->real_escape_string($ns);
    $sql = "SELECT * FROM `authority` WHERE `address` = '{$ns}'";

    // unknown error
    if (($res = $db->query($sql)) === false) {
        abort($db);
    }

    // not duplicate
    if ($res->num_rows === 0) {
        return false;
    }

    // exist authority
    $row = $res->fetch_assoc();
    return $row['auth_id'];
}

function isExistHost($host, $db) {
    $host = $db->real_escape_string($host);
    $sql = "SELECT * FROM `host` WHERE `host_name` = '{$host}'";

    // unknown error
    if (($res = $db->query($sql)) === false) {
        abort($db);
    }

    // not duplicate
    if ($res->num_rows === 0) {
        return false;
    }

    // exist host
    $row = $res->fetch_assoc();
    return $row['host_id'];
}

function getNsRecords($host, $db) {
    $cmd = "dig +nottl +nostat +noadd +noquestion +nocmd {$host} NS";
    $output = shell_exec($cmd);

    $lines = explode("\n", $output);

    $output = array(
        'status' => null,
        'flag'   => null,
        'answer' => array(),
    );

    foreach ($lines as $value) {
        if (empty($value)) continue;

        if (strpos($value, ';') === 0) {
            // comment section
            
            // RCODE
            if (preg_match("/status: ([A-Z]+)/", $value, $match)) {
                $rcode = 'RCODE_' . $match[1];
                $output['status'] = constant($rcode);
            }

            // FLAGS
            if (preg_match("/flags: ([a-z ]+)/", $value, $match)) {
                $flags = explode(' ', $match[1]);
                $output['flag'] = $flags;
            }
        } else  {
            // answer section
            $value = str_replace("\t", " ", $value);
            $value = preg_replace("/\s\s+/", ' ', $value);
            $value = explode(' ', $value);
            if ($value[2] !== 'NS') continue;
            $output['answer'][] = $value;
        }
    }

    return $output;
}

function abort($db = false) {
    if ($db !== false) {
        if ($db->errno) {
            var_dump($db->error_list);
        }
        $db->close();
    }
    echo "\naborted\n";
    exit;
}


