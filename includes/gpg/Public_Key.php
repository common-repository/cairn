<?php
/** @package    php-gpg::GPG */

/** require supporting files */
require_once("Expanded_Key.php");

define("PK_TYPE_ELGAMAL", 1);
define("PK_TYPE_RSA", 0);
define("PK_TYPE_UNKNOWN", -1);

/**
 * Pure PHP implementation of PHP/GPG public key
 *
 * @package php-gpg::GPG
 * @link http://www.verysimple.com/
 * @copyright  1997-2011 VerySimple, Inc.
 * @license    http://www.gnu.org/licenses/lgpl.html  LGPL
 * @todo implement decryption
 * @version 1.0
 */
class GPG_Public_Key {
    var $version;
	var $fp;
	var $key_id;
	var $user;
	var $public_key;
	var $type;
	
	function IsValid()
	{
		return $this->version != -1 && $this->GetKeyType() != PK_TYPE_UNKNOWN;
	}
	
	function GetKeyType()
	{
		if (!strcmp($this->type, "ELGAMAL")) return PK_TYPE_ELGAMAL;
		if (!strcmp($this->type, "RSA")) return PK_TYPE_RSA;
		return PK_TYPE_UNKNOWN;
	}
	
	function GetKeyId()
	{
		return (strlen($this->key_id) == 16) ? $this->key_id : '0000000000000000';
	}
	
	function GetPublicKey()
	{
		return str_replace("\n", "", $this->public_key);
	}
	
	function GPG_Public_Key($asc) {
		$found = 0;
		$i = strpos($asc, "-----BEGIN PGP PUBLIC KEY BLOCK-----");
		
		if($i === false)
		{
			$this->version = "";
			$this->fp = "";
			$this->key_id = "";
			$this->user = "";
			$this->public_key = "";
			
			return;
		}
		
		$a = strpos($asc, "\n", $i);
		if ($a > 0) $a = strpos($asc, "\n", $a + 1);
		$e = strpos($asc, "\n=", $i); 
		if ($a > 0 && $e > 0) $asc = substr($asc, $a + 2, $e - $a - 2); 
		else {
			$this->version = "";
			$this->fp = "";
			$this->key_id = "";
			$this->user = "";
			$this->public_key = "";
			
			return;
		}
		
		$len = 0;
		$s = base64_decode($asc);
		for($i = 0; $i < strlen($s);) {
			$tag = ord($s[(int)$i++]); //cast to int
			
			if(($tag & 128) == 0) break;
			
			if($tag & 64) {
				$tag &= 63;
				$len = ord($s[(int)$i++]);
				if ($len > 191 && $len < 224) $len = (($len - 192) << 8) + ord($s[(int)$i++]);
				else if ($len == 255) $len = (ord($s[(int)$i++]) << 24) + (ord($s[(int)$i++]) << 16) + (ord($s[(int)$i++]) << 8) + ord($s[(int)$i++]);
					else if ($len > 223 && len < 255) $len = (1 << ($len & 0x1f));
			} else {
				$len = $tag & 3;
				$tag = ($tag >> 2) & 15;
				if ($len == 0) $len = ord($s[(int)$i++]); // cast to int
				else if($len == 1) $len = (ord($s[(int)$i++]) << 8) + ord($s[(int)$i++]);
					else if($len == 2) $len = (ord($s[(int)$i++]) << 24) + (ord($s[(int)$i++]) << 16) + (ord($s[(int)$i++]) << 8) + ord($s[(int)$i++]);
						else $len = strlen($s) - 1;
			}
			
			if ($tag == 6 || $tag == 14) {
				$k = $i;
				$version = ord($s[(int)$i++]);
				$found = 1;
				$this->version = $version;
				
				$time = (ord($s[(int)$i++]) << 24) + (ord($s[(int)$i++]) << 16) + (ord($s[(int)$i++]) << 8) + ord($s[(int)$i++]);
				
				if($version == 2 || $version == 3) $valid = ord($s[(int)$i++]) << 8 + ord($s[(int)$i++]);
				
				$algo = ord($s[(int)$i++]);
				if($algo == 1 || $algo == 2) {
					$m = $i;
					$lm = (int) floor((ord($s[(int)$i]) * 256 + ord($s[(int)$i + 1]) + 7) / 8); //cast to int
					$i += $lm + 2;
					
					$mod = substr($s, $m, $lm + 2);
					$le = floor((ord($s[(int)$i]) * 256 + ord($s[(int)$i+1]) + 7) / 8);
					$i += $le + 2;
					
					$this->public_key = base64_encode(substr($s, $m, $lm + $le + 4));
					$this->type = "RSA";
					
					if ($version == 3) {
						$this->fp = '';
						$this->key_id = bin2hex(substr($mod, strlen($mod) - 8, 8));
					} else if($version == 4) {
							$pkt = chr(0x99) . chr($len >> 8) . chr($len & 255) . substr($s, $k, $len);
							$fp = sha1($pkt);
							$this->fp = $fp;
							$this->key_id = substr($fp, strlen($fp) - 16, 16);
						} else {
							$this->fp = "";
							$this->key_id = "";
						}
					$found = 2;
				} else if(($algo == 16 || $algo == 20) && $version == 4) {
						$m = $i;
						
						$lp = floor((ord($s[(int)$i]) * 256 + ord($s[(int)$i +1]) + 7) / 8);
						$i += $lp + 2;
						
						$lg = floor((ord($s[(int)$i]) * 256 + ord($s[(int)$i + 1]) + 7) / 8);
						$i += $lg + 2;
						
						$ly = floor((ord($s[(int)$i]) * 256 + ord($s[(int)$i + 1]) + 7)/8);
						$i += $ly + 2;
						
						$this->public_key = base64_encode(substr($s, $m, $lp + $lg + $ly + 6));
						
						$pkt = chr(0x99) . chr($len >> 8) . chr($len & 255) . substr($s, $k, $len);
						$fp = sha1($pkt);
						$this->fp = $fp;
						$this->key_id = substr($fp, strlen($fp) - 16, 16);
						$this->type = "ELGAMAL";
						$found = 3;
					} else {
						$i = $k + $len;
					}
			} else if ($tag == 13) {
					$this->user = substr($s, $i, $len);
					$i += $len;
				} else {
					$i += $len;
				}
		}
		
		if($found < 2) {  
			$this->version = "";
			$this->fp = "";
			$this->key_id = "";
			$this->user = ""; 
			$this->public_key = "";
		}
	}
	
	function GetExpandedKey()
	{
		$ek = new Expanded_Key($this->public_key);
	}
}

?>
