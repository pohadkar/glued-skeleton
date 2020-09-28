<?php
namespace Glued\Core\Classes\Utils;
//use Respect\Validation\Validator as v;
//use UnexpectedValueException;
use Exception;

class Utils
{


    protected $db;
    protected $settings;


    public function __construct($db, $settings) {
        $this->db = $db;
        $this->settings = $settings;
    }


    public function sql_insert_with_json($table, $row) {
        $this->db->startTransaction(); 
        $id = $this->db->insert($table, $row);
        $err = $this->db->getLastErrno();
        if ($id) {
          $updt = $this->db->rawQuery("UPDATE `".$table."` SET `c_json` = JSON_SET(c_json, '$.id', ?) WHERE c_uid = ?", Array ((int)$id, (int)$id));
          $err += $this->db->getLastErrno();
        }
        if ($err === 0) { $this->db->commit(); } else { $this->db->rollback(); throw new \Exception(__('Database error: ')." ".$err." ".$this->db->getLastError()); }
        return (int)$id;
    }

    public function fetch_uri($uri) {
        $curl_handle = curl_init();
        $curl_options = array_replace( $this->settings['curl'], [ CURLOPT_URL => $uri ] );
        curl_setopt_array($curl_handle, $curl_options);
        $data = curl_exec($curl_handle);
        curl_close($curl_handle);
        return $data;
    }


}
