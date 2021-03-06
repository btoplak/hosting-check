<?php

/**
 * Hosting (webspace) checker PHP query class + main
 * v0.3
 */

// not in PHP 5.2
//namespace HostingCheck;


define( 'LOGDIRNAME', 'log' );
define( 'LOGFILENAME', 'error.log' );
define( 'LOGTEMPLATE', "/* shared hosting error logging */
ini_set( 'error_log', '%s' );
ini_set( 'log_errors', 1 );

//date_default_timezone_set('Europe/Budapest');

/*
// upload and session directory
ini_set( 'upload_tmp_dir', '%s/tmp' );
ini_set( 'session.save_path', '%s/session' );
// comment out after first use
mkdir( '%s/tmp', 0700 );
mkdir( '%s/session', 0700 );
*/

/* WordPress defines */
/*
// for different FTP/PHP UID
define( 'FS_METHOD', 'direct' );
define( 'FS_CHMOD_DIR', (0775 & ~ umask()) );
define( 'FS_CHMOD_FILE', (0664 & ~ umask()) );
*/
define( 'WP_MEMORY_LIMIT', '96M' );
//define( 'WP_MAX_MEMORY_LIMIT', '255M' );
define( 'WP_USE_EXT_MYSQL', false );
define( 'WP_POST_REVISIONS', 10 );
// WP in a hidden subdir
//define( 'WP_CONTENT_DIR', '%s' );
//define( 'WP_CONTENT_URL', '%s' );
define( 'WP_DEBUG', true ); error_reporting( E_ALL | E_STRICT );

//  production only
//define( 'WP_DEBUG', false );
// some themes will refuse to display their option panel
define( 'DISALLOW_FILE_EDIT', true );
//define( 'DISALLOW_FILE_MODS', true );
//define( 'WP_CACHE', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
// only when Linux cron or remote cron call is set up
define( 'DISABLE_WP_CRON', true );
// comment out after first use
error_log( 'logging-test' );\n" );



class Query {

private function unow() {
    return microtime(true);
}

private function expand_shorthand($val) {
    if (empty($val)) {
        return '0';
    }

    $units = array( 'k', 'm', 'g');
    $unit = strtolower(substr($val, -1));
    $power = array_search($unit, $units);

    if ($power === FALSE) {
        $bytes = (int)$val;
    } else {
        //       (int)substr($val, 0, -1)
        $bytes = (int)$val * pow(1024, $power + 1);
    }
    return $bytes;
}

private function stress_steps($iter = 25000000) {
    $start = $this->unow();

    $steps = 0;
    for ($i = 0; $i < $iter; $i += 1) {
        $steps += $i;
    }
    return $this->unow() - $start;
}

private function stress_shuffle($iter = 500000) {
    $start = $this->unow();

    $hash = 0;
    for ($i = 0; $i < $iter; $i += 1) {
        // XOR
        $hash ^= md5(substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, rand(1,10)));
    }
    return $this->unow() - $start;
}

private function stress_aes($iter = 2500) {
    $start = $this->unow();

    $data = md5(substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, rand(1,10)));
    $keyhash = md5('secret key');
    $ivsize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($ivsize, MCRYPT_DEV_URANDOM);

    $cipherdata = '';
    for ($i = 0; $i < $iter; $i += 1) {
        // XOR
        $cipherdata ^= mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $keyhash, $data, MCRYPT_MODE_CBC, $iv);
    }
    return $this->unow() - $start;
}

private function dumpfile( $filename, $dumpsize ) {
    $onemega = '';
    for ( $i = 0; $i < 1048576; $i += 1 ) {
        // prevent compression
        $onemega .= chr( rand( 1, 255 ) );
    }

    for ( $i = 0; $i < $dumpsize; $i += 1 ) {
        if ( ! file_put_contents( $filename, $onemega, FILE_APPEND ) ) {
            unlink( $filename );
            return false;
        };
    }

    return true;
}

private function seeks( $filename, $seeks = 1000000 ) {
    // for fgets
    $size = filesize( $filename ) - 4096;

    // smaller than 10MB
    if ( $size === false || $size < 10485760) {
        return false;
    }

    $handle = fopen( $filename, 'r' );
    if ( $handle === false ) {
        return false;
    }

    for ( $i = 0; $i < $seeks; $i += 1 ) {
        fseek( $handle, rand( 0, $size ) );
        fgets( $handle, 4096 );
    }

    fclose($handle);
    unlink($filename);

    return true;
}


//////////////// ^^^ Private,  vvv Public ///////////////


public function fail() {
    print '0';
    exit;
}

public function version() {
    $current = explode('.', phpversion());

    //  = 100*major number       + minor number
    return (int)$current[0] * 100 + (int)$current[1];
}

public function memory() {
    $max_mem = ini_get('memory_limit');
    if ($max_mem === FALSE || empty($max_mem)) {
        return '0';
    }

    return $this->expand_shorthand($max_mem);
}

public function exectime() {
    $max_exec = ini_get('max_execution_time');
    if ($max_exec === FALSE || empty($max_exec)) {
        return '0';
    }

    return $max_exec;
}

public function sapi() {
    return php_sapi_name();
}

public function apachemods() {
    if (! is_callable('apache_get_modules')) {
        return '0';
    }

    $modules = apache_get_modules();

    return implode(',', $modules);
}

public function extensions() {
    //$extensions = array_merge(get_loaded_extensions(false), get_loaded_extensions(true));
    $extensions = get_loaded_extensions(false);

    return implode(',', $extensions);
}

public function timezone() {
    $tz = ini_get('date.timezone');
    if ($tz === FALSE || empty($tz)) {
        return '0';
    // elseif (date_default_timezone_get())
    //   print date_default_timezone_get();
    }

    return $tz;
}

public function mysqli($type = '') {
    // ABSPATH = pwd -> "ABSPATH . 'wp-settings.php'" must exist
    define('ABSPATH', dirname(__FILE__) . '/');

    if (file_exists(dirname(ABSPATH) . '/wp-config.php')) {
        // webroot
        $wp_config = dirname(ABSPATH) . '/wp-config.php';
    } elseif (file_exists(dirname(dirname(ABSPATH)) . '/wp-config.php' )
        && ! file_exists(dirname(dirname(ABSPATH)) . '/wp-settings.php')) {
        // above!
        $wp_config = dirname(dirname(ABSPATH)) . '/wp-config.php';
    } else {
        return '0';
    }

    if (! file_exists(ABSPATH . 'wp-settings.php')) {
        return '0';
    }

    require($wp_config);

    if (! is_callable('mysqli_real_connect')
        || ! defined('DB_HOST')
        || DB_HOST === '') {
        return '0';
    }

    $dbh = mysqli_init();
    if (! mysqli_real_connect($dbh, DB_HOST, DB_USER, DB_PASSWORD)) {
        return '0';
    };

    if ($type === 'wpoptions') {
        //mysqli_select_db($dbh, DB_NAME);
        $result = mysqli_query($dbh, "USE `" . DB_NAME . "`;");
        // "autoload" options
        $version_query = sprintf("SELECT option_name, option_value FROM `%soptions` WHERE autoload = 'yes';", $table_prefix);
    } else {
        // normal operation
        $version_query = "SHOW VARIABLES LIKE 'version'";
    }
    $result = mysqli_query($dbh, $version_query);

    if (! $result) {
        mysqli_close($dbh);
        return '0';
    }

    if ($type === 'wpoptions') {
        $total_length = 0;
        while( $row = mysqli_fetch_row($result)) {
            $total_length += strlen($row[0] . $row[1]) + 2;
        }
        mysqli_free_result($result);
        mysqli_close($dbh);

        return $total_length;
    } else {
        // normal operation
        $version_array = mysqli_fetch_row($result);
        mysqli_free_result($result);
        mysqli_close($dbh);

        if (empty($version_array[1])) {
            return '0';
        }

        return $version_array[1];
    }
}

public function wpoptions() {
    return $this->mysqli('wpoptions');
}

public function logfile() {
    $docroot = $_SERVER['DOCUMENT_ROOT'];
    if (empty($docroot)) {
        return '0';
    }

    // is it an alias?
    if (isset($_SERVER['DOCUMENT_ROOT'])
        && isset($_SERVER['SCRIPT_FILENAME'])
        && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) !== 0) {

        // 'hosting-check' . '/hc-query.php'
        $me = basename(dirname($_SERVER['SCRIPT_FILENAME'])) . '/hc-query.php';
        // correct docroot from SCRIPT_FILENAME
        if (substr($_SERVER['SCRIPT_FILENAME'], -strlen($me)) === $me) {
            $docroot = substr($_SERVER['SCRIPT_FILENAME'], 0, -strlen($me));
        }
    }

    $docroot = rtrim($docroot, '/');

    // above webroot
    $logpath = dirname($docroot);
    if (! chdir($logpath)) {
        // revert
        $logpath = $docroot;
    }

    if (!file_exists($logpath . '/' . LOGDIRNAME)) {
        // create log dir
        if (! mkdir($logpath . '/' . LOGDIRNAME, 0700)) {
            return '0';
        }
    }

    $logfile = $logpath . '/' . LOGDIRNAME . '/' . LOGFILENAME;
    if (! touch($logfile)) {
        return '0';
    }

    // WP in a hidden subdirectory
    $wp_content = $docroot . '/static';
    $wp_content_url = 'http://' . $_SERVER['HTTP_HOST'] . '/static';

    chmod($logfile, 0600);
    return sprintf(LOGTEMPLATE, $logfile, $logpath, $logpath, $logpath, $logpath, $wp_content, $wp_content_url);
}

public function safe() {
    // REMOVED as of PHP 5.4.0
    if (version_compare(PHP_VERSION, '5.4.0', '<')) {

        if (get_magic_quotes_gpc() === 1
            || ini_get('safe_mode') === 1
            || ini_get('register_globals') === 1) {
            return '0';
        }
    }
    return 'OK';
}

public function uid() {
    if ( ! is_callable( 'posix_getuid' )
        || ! is_callable( 'getmyuid' )
        || ! is_callable( 'posix_geteuid' ) ) {
        return '0';
    }

    // webserver UID
    $uid = posix_getuid();
    if ( posix_geteuid() !==  $uid ) {
        return '0';
    }
    // FTP UID
    if ( getmyuid() !== $uid ) {
        return '0';
    }
    return $uid;
}

public function http() {
    if ( is_callable( 'stream_socket_client' )
        || ( is_callable( 'curl_init' ) && is_callable( 'curl_exec' ) ) ) {
        return 'OK';
    }
    return '0';
}

public function cpuinfo() {
    $cpuinfo = '/proc/cpuinfo';
    $matches = array();

    if ( file_exists( $cpuinfo ) ) {
        $cpu = file_get_contents( $cpuinfo );

        if ( preg_match_all('/^model name\s*:\s+(.*)\s*$/mU', $cpu, $matches, PREG_SET_ORDER) > 0 ) {
            return sprintf( '%d*%s', count( $matches ), $matches[0][1] );
        } else {
            return '0';
        }
    } else {
        // no access
        return '0';
    }
}

public function stresscpu() {
    if (is_int(ini_get('max_execution_time')) && ini_get('max_execution_time') < 20) {
        if (! ini_set('max_execution_time', 20)) {
            return '0';
        }
    }
    if (! is_callable('mcrypt_get_iv_size')) {
        return '0';
    }

    // iteration ratio:  steps:shuffle:eas ~ 10000:200:1
    return sprintf("%.3f\t%.3f\t%.3f", $this->stress_steps(), $this->stress_shuffle() ,$this->stress_aes());
}

public function accesstime( $filename = './accestime.dump', $dumpsize = 1000 ) {
//public function accesstime( $filename = './accestime.dump', $dumpsize = 800 ) {
    // only to reduce
    if ( isset( $_GET['dumpsize'] ) ) {
        $tobe_size = intval( $_GET['dumpsize'] );
        if ( $tobe_size < $dumpsize ) {
            $dumpsize = $tobe_size;
        }
    }

    if ( empty( $filename ) || empty( $dumpsize ) || intval( $dumpsize ) < 10 ) {
        return '0';
    }

    // cannot create
    if ( ! touch( $filename ) ) {
        return '0';
    }

    $start = $this->unow();
    if (! $this->dumpfile( $filename, $dumpsize ) ) {
        return '0';
    }
    $dumptime = $this->unow() - $start;

    $start = $this->unow();
    if ( ! $this->seeks( $filename ) ) {
        return '0';
    }
    $seektime = $this->unow() - $start;

    // dump time TAB seek time
    return sprintf( "%.3f\t%.3f", $dumptime, $seektime );
}

//class ends here
}


/** main **/


// hide errors
error_reporting( 0 );
//DBG  error_reporting( E_ALL | E_STRICT );

$phpq = new Query;

//DBG  $_GET['q'] = $argv[1];
if ( empty( $_GET['q'] ) ) {
    $phpq->fail();
}

$method = preg_replace('/[^a-z]/', '', $_GET['q']);

if (!method_exists($phpq, $method)) {
    $phpq->fail();
}

print $phpq->$method();
