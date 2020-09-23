<?php
namespace Glued\Core\Install;
use Composer\Script\Event;
use Glued\Core\Classes\Crypto\Crypto;

class ComposerHooks
{
    public static function preInstall(Event $event) {
        $io = $event->getIO();
        if ($io->askConfirmation("Are you sure you want to proceed? ", false)) {
            return true;
        }
        exit;
    }

    public static function postPackageInstall(Event $event) {
        $installedPackage = $event->getComposer()->getPackage();
        // any tasks to run after the package is installed
    }

    public static function configTool(Event $event) {
        echo "*** STARTING THE COFIGURATION TESTING AND SETUP TOOL" . PHP_EOL;
        // get access to the current Composer instance
        $composer = $event->getComposer();
        // get access to the current ComposerIOConsoleIO
        // stream for terminal input/output
        $io = $event->getIO();
        // paths
        $fn['privkey']       = getcwd().'/private/crypto/private.key';
        $fn['pubkey']        = getcwd().'/private/crypto/public.key';
        $fn['geoip-city']    = getcwd().'/private/data/core/maxmind-geolite2-city.mmdb.tar.gz';
        $fn['phinx']         = getcwd().'/phinx.yml';
        $fn['settings']      = getcwd().'/config/settings.php';
        // get settings interactively
        if ( !file_exists($fn['phinx']) or !file_exists($fn['settings']) ) {
          $ioresp['dbhost'] = $io->ask(">>> Mysql database host [127.0.0.1]: ", "127.0.0.1");
          $ioresp['dbname'] = $io->ask(">>> Mysql database name [glued]: ", "glued");
          $ioresp['dbuser'] = $io->ask(">>> Mysql database user [glued]: ", "glued");
          $ioresp['dbpass'] = $io->ask(">>> Mysql database pass [glued-pw]: ", "glued-pw");
        }
        if ( !file_exists($fn['privkey']) ) {
          $ioresp['rsabit'] = $io->ask(">>> What rsa key bitsize do you want to use, should be >=1024 [2048]: ", "2048");
        }
        
        // sanity check
        if ( !file_exists($fn['phinx']) or !file_exists($fn['settings']) ) {
          echo "*** Testing MySQL connection ..." . PHP_EOL;
          $link = mysqli_connect($ioresp['dbhost'], $ioresp['dbuser'], $ioresp['dbpass'], $ioresp['dbname']);
          if (!$link) {
            echo "!!! Unable to connect to MySQL." . PHP_EOL;
            echo "!!! Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "!!! Debugging error: " . mysqli_connect_error() . PHP_EOL;
            die();
          }
          echo "+++ MySQL connection OK to " . mysqli_get_host_info($link) . PHP_EOL;
          mysqli_close($link);
        }
        
        // do whats missing
        if ( !file_exists($fn['privkey']) ) {
          echo "*** Generating private key, this can take a while. If it goes on for too long, reduce keybit size." . PHP_EOL;
          exec("openssl genrsa -out ".$fn['privkey']." ".$ioresp['rsabit']);
          if ( file_exists($fn['pubkey']) ) { rename($fn['pubkey'], $fn['pubkey'].'.bak'); }
        }
        if ( !file_exists($fn['pubkey']) ) {
          echo "*** Generating public key." . PHP_EOL;
          exec("openssl rsa -in ".$fn['privkey']." -pubout -out ".$fn['pubkey']);
        }
        if ( !file_exists($fn['phinx']) ) {
          echo "*** Generating phinx.yml." . PHP_EOL;
          $str=file_get_contents(getcwd().'/phinx.dist.yml');
          $str=str_replace("db_host", $ioresp['dbhost'], $str);
          $str=str_replace("production_db", $ioresp['dbname'], $str);
          $str=str_replace("db_user", $ioresp['dbuser'], $str);
          $str=str_replace("db_pass", $ioresp['dbpass'], $str);
          file_put_contents($fn['phinx'], $str);
          exec("php vendor/bin/phinx test -e production");
        }
        if ( !file_exists($fn['settings']) ) {
          echo "*** Generating /config/settings.php." . PHP_EOL;
          $crypto = new Crypto;
          $str=file_get_contents(getcwd().'/config/settings.dist.php');
          $str=str_replace("mail-encryption-key", $crypto->genkey_base64(), $str);
          $str=str_replace("reqparams-encryption-key", $crypto->genkey_base64(), $str);
          $str=str_replace("db_host", $ioresp['dbhost'], $str);
          $str=str_replace("db_name", $ioresp['dbname'], $str);
          $str=str_replace("db_user", $ioresp['dbuser'], $str);
          $str=str_replace("db_pass", $ioresp['dbpass'], $str);
          file_put_contents($fn['settings'], $str);
        }
        // get geolite2
        if (true) {
          echo "*** Getting Maxmind GeoLite2 database ...";
          // Ugly $settings getter without too much work + download uris
          define("__ROOT__", getcwd());
          $_SERVER['SERVER_NAME'] = "(composer)";
          $settings = require_once(getcwd() . '/config/settings.php');
          $data_uri = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key='.$settings['geoip']['maxmind_licence_key'].'&suffix=tar.gz';
          $hash_uri = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key='.$settings['geoip']['maxmind_licence_key'].'&suffix=tar.gz.sha256';
          $hash_file = '';
          $hash_dist = '';
          // Get geoip database
          $stream = @fopen($data_uri, 'r');
          if ( $stream ) {
              file_put_contents($fn['geoip-city'], $stream);
              file_put_contents($fn['geoip-city'].'.sha256', @fopen($hash_uri, 'r'));
          }
          // If geoip database gzip and checksum files are present, compare the sha256
          if (file_exists($fn['geoip-city'].'.sha256')) { $hash_dist = explode(" ", file_get_contents($fn['geoip-city'].'.sha256', 'r'), 2)[0]; }
          if (file_exists($fn['geoip-city'])) { $hash_file = hash_file('sha256',$fn['geoip-city']); }

          // If we have the database and its sha256 fits, unpack it and enable geoip in glued's config
          if (($hash_dist == $hash_file) and ( $hash_file != '' )) { 
            // enable geoip in glued's config
            $str=file_get_contents($fn['settings']);
            $str=str_replace("'geoip_engine' => false,", "'geoip_engine' => 'maxmind',", $str);
            file_put_contents($fn['settings'], $str);
            // unpack the data
            $phar = new \PharData($fn['geoip-city']);
            $pattern = '.mmdb';
            foreach($phar as $item) {
                if($item->isDir()) {
                    $dir = new \PharData($item->getPathname());
                    foreach($dir as $child) {
                        // loop over files in subdirectories present in the archive. If filename fits the $pattern, extract it. $child assumes the form of
                        // phar:///path/to/glued/private/data/core/maxmind-geolite2-country.mmdb.tar.gz/GeoLite2-Country_20200609/GeoLite2-Country.mmdb
                        if ( strpos(basename((string)$child), $pattern ) !== false) {
                            // get the relative path within the archive (i.e. GeoLite2-Country_20200609/GeoLite2-Country.mmdb)
                            $relpath = str_replace( basename($fn['geoip-city']).'/', '', strstr( (string)$child, basename($fn['geoip-city']) ) );
                            $phar->extractTo(__ROOT__ . '/private/data/core', $relpath, true);
                            //echo __ROOT__ . '/private/data/core/' . str_replace( 'tar.gz', '', basename( $fn['geoip-city']) );
                            copy(__ROOT__ . '/private/data/core/' . $relpath, __ROOT__ . '/private/data/core/' . str_replace( '.tar.gz', '', basename( $fn['geoip-city']) ));
                            unlink(__ROOT__ . '/private/data/core/' . $relpath);
                            rmdir(dirname(__ROOT__ . '/private/data/core/' . $relpath));
                        }
                    }
                }
            }    
            echo "[pass] - ensuring geoip is enabled in settings.php." . PHP_EOL;

          } else { 
            $str=file_get_contents($fn['settings']);
            $str=str_replace("'geoip_engine' => 'maxmind',", "'geoip_engine' => false,", $str);
            file_put_contents($fn['settings'], $str);
            echo "[fail] - ensuring geoip is disabled in settings.php." . PHP_EOL;
            echo "    Please provide a correct (free) GeoLite2 license key in settings.php and make sure your instance can reach maxmind.com servers." . PHP_EOL;
            echo "    Glued will run without a geoip database, but the the capability to detect fradulent account access will be limited." . PHP_EOL;
          }


        }
        echo "+++ ALL IS WELL CONFIGURED." . PHP_EOL;
    }

}
