<?php
/**
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2008-2018 Peter Kahl
 * @license    Apache License, Version 2.0, http://www.apache.org/licenses/LICENSE-2.0
 */

namespace peterkahl\Sage;

use peterkahl\curlMaster\curlMaster;
use \Exception;

class Sage {

  /**
   * Filename prefix for cache files.
   * @var string
   */
  const FILEPREFIX = 'SAGE_';

  /**
   * GitHub URL to fetch latest data
   * @var string
   */
  const URLA = 'https://github.com/peterkahl/Sage/src/data.json';

  /**
   * URL to fetch latest data on Chrome
   * @var string
   */
  const URLB = 'http://omahaproxy.appspot.com/all';

  /**
   * URL to fetch latest data on Firefox
   * @var string
   */
  const URLC = 'https://ftp.mozilla.org/pub/firefox/releases/';

  /**
   * Oldest date of interest.
   * Anything before this doesn't matter, outside of our range.
   * @var integer
   */
  const EPOCH_ZERO = 1199145600; # 1 Jan 2008

  /**
   * Path of cache directory.
   * @var string
   */
  public $CacheDir;

  /**
   * Path to CA Certificate Bundle
   * @var string
   */
  public $CAbundle;

  /**
   * Enable fetching data from remote hosts.
   * If true, data is fetched remotely and 
   * stored in cache.
   * If false, only local data file is used, caching
   * is not used.
   * @var boolean
   */
  public $FetchRemoteData = true;

  /**
   * Array holding data.
   * @var array
   */
  private $data;

  /**
   * Epoch when data was last changed.
   * @var integer
   */
  private $released;

  /**
   * Epoch when the system checked for data.
   * @var integer
   */
  private $last_check;
  
  #===================================================================

  /**
   * Constructor
   * Data array is created from local and remote sources.
   * Data array is updated if outdated.
   * Data are stored in cache.
   * @param  boolean $force ... Disables reading of cache, thus forcing
   *                            new requests to remote hosts to fetch 
   *                            fresh data.
   * @throws \Exception
   */
  public function __construct($force = false) {
    if (!is_bool($force)) {
      throw new Exception('Illegal type argument force');
    }
    $this->force = $force;
    if (!$this->FetchRemoteData) {
      $this->UseLocalData();
      return;
    }
    #----------------------------------
    # Local cache
    $this->CacheFile = $this->CacheDir .'/'. self::FILEPREFIX .'data.json';
    if (!$force && file_exists($this->CacheFile) && filemtime($this->CacheFile) > time()-86400) {
      $temp = json_decode(file_get_contents($this->CacheFile), true);
      $this->data       = $temp['data'];
      $this->released   = $temp['released'];
      $this->last_check = time();
      return;
    }
    #----------------------------------
    # Fetch from GitHub
    $this->curlm = new curlMaster;
    $this->curlm->CacheDir = $this->CacheDir;
    $this->curlm->ca_file  = $this->CAbundle;
    $this->curlm->ForcedCacheMaxAge = 2*86400;
    $answer = $this->curlm->Request(self::URLA, 'GET', array(), $this->force);
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200' && !empty($body)) {
      $temp = json_decode($body, true);
      $this->data       = $temp['data'];
      $this->released   = $temp['released'];
      $this->last_check = time();
      #--------------------------------
      # Latest browser data
      $this->GetBrowserInfoAll();
      $this->SaveData();
      return;
    }

    $this->UseLocalData();
  }

  #===================================================================

  private function SaveData() {
    $temp = array(
      'data'        => $this->data,
      'released'    => $this->released,
      'homepage'    => 'https://github.com/peterkahl/Sage',
      'description' => 'Estimates age of browser and OS software.',
      'copyright'   => 'Peter Kahl',
      'license'     => 'Apache-2.0',
    );
    file_put_contents($this->CacheFile, json_encode($temp, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  }

  #===================================================================

  /**
   * Loads data from local file.
   * @throws \Exception
   */
  private function UseLocalData() {
    $LocalFile = __DIR__ .'/data.json';
    if (file_exists($LocalFile)) {
      $temp = json_decode(file_get_contents($LocalFile), true);
      $this->data       = $temp['data'];
      $this->released   = $temp['released'];
      $this->last_check = $temp['released'];;
      return;
    }
    throw new Exception('No data files found');
  }

  #==================================================================

  public function GetAge($name, $ver) {
    if (array_key_exists($ver, $this->data[$name])) {
      return time() - $this->data[$name][$ver];
    }
    if (substr_count($ver, '.') > 1) {
      $arr = explode('.', $ver);
      $ver = $arr[0] .'.'. $arr[1];
      if (array_key_exists($ver, $this->data[$name])) {
        return time() - $this->data[$name][$ver];
      }
    }
    $temp = $this->data[$name];
    $verNorm = $this->Str2Val($ver, $name);
    foreach ($temp as $str => $time) {
      $val = $this->Str2Val($str, $name);
      if ($val < $verNorm) {
        $low = array('time'=>$time, 'val'=>$val);
      }
      elseif ($val == $verNorm) {
        return time() - $time;
      }
      else {
        $high = array('time'=>$time, 'val'=>$val);
        break;
      }
    }
    #----
    $timeSpan = $high['time'] - $low['time'];
    $incrt = (integer) $timeSpan/20;
    $time  = $low['time'];
    $val   = $low['val'];
    $valSpan  = $high['val'] - $low['val'];
    $incrv = $valSpan/20;
    for ($step = 1; $step < 20; $step++) {
      $time += $incrt;
      $val  += $incrv;
      if ($val < $verNorm) {
        $epoch = $time;
      }
      else {
        break;
      }
    }
    return time() - $epoch;
  }

  #===================================================================

  private function Str2Val($str, $name) {
    if (substr_count($str, '.') < 1) {
      $str .= '.0';
    }
    $arnm = array(
      'ios'       => 20,
      'macos'     => 20,
      'windows'   =>  5,
      'seamonkey' => 55,
      'lunascape' => 20,
    );
    $scale = 10;
    if (array_key_exists($name, $arnm)) {
      $scale = $arnm[$name];
    }
    $arr = explode('.', $str);
    if ($name == 'edge') {
      return $arr[1];
    }
    return $arr[0] + $arr[1]/$scale;
  }

  #===================================================================

  /**
   * Returns data on specific software.
   * @param  string $br
   * @return array
   */
  private function GetInfo($name) {
    $name = $this->strlower($name);
    if ($this->FetchRemoteData) {
      if ($name == 'chrome') {
        return self::getLatestVersionChrome();
      }
      elseif ($name == 'crios') {
        return self::getLatestVersionChrome();
      }
      elseif ($name == 'firefox') {
        return self::getLatestVersionFirefox();
      }
    }
    if (array_key_exists($name, $this->data)) {
      return array(
        'version'    => $this->data[$name],
        'released'   => $this->released,
        'last_check' => $this->last_check,
      );
    }
    return array();
  }

  #===================================================================

  /**
   *
   *
   */
  public function getCurrentVer($name) {
    $name = $this->strlower($name);
    if ($name == '') {
      return '';
    }
    if (!array_key_exists($name, $this->data)) {
      return '';
    }
    return $this->data[$os]; # need to get the largest
  }

  #===================================================================

  /**
   *
   *
   */
  public function isOutdated($ver, $name) {
    $name = $this->strlower($name);
  }

  #===================================================================

  /**
   *
   *
   */
  public function getLatestVersionChrome() {
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLB); # CSV file
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
      $body = str_replace("\r\n", "\n", $body);
      $body = str_replace("\r", "\n", $body);
      $body = preg_replace("/\n\n+/", "\n", $body);
      $body = explode("\n", $body);
      # os,channel,current_version,previous_version,current_reldate,previous_reldate,branch_base_commit,branch_base_position,branch_commit,true_branch,v8_version
      # mac,stable,62.0.3202.89,62.0.3202.75,11/06/17,10/26/17,fa6a5d87adff761bc16afc5498c3f5944c1daa68,499098,ba7a0041073a5e9928d277806bfe24c325d113e5,3202,6.2.414.40
      # 0     1         2            3          4         5
      foreach ($body as $line) {
        if (substr($line, 0, 10) == 'mac,stable') {
          $temp = explode(',', $line);
        }
      }
      if (!isset($temp)) {
        throw new Exception('Unable to find line starting with "mac,stable"');
      }
      list($month, $day, $year) = explode('/', $temp[4]);
      return array(
        'version'  => $temp[2],
        'released' => strtotime('20'. $year .'-'. $month .'-'. $day.' 00:00:00 GMT'),
      );
    }
    return array();
  }

  #===================================================================

  /**
   *
   *
   */
  public function getLatestVersionFirefox() {
    $all = $this->getAllVersionsFirefox();
    $version = end($all);
    $epoch = $this->getEpochFirefoxVersion($version);
    return array(
      'version'  => $version,
      'released' => $epoch,
    );
  }

  #===================================================================

  /**
   *
   *
   */
  public function getAllVersionsFirefox() {
    $new = array();
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLC);
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
      $body = self::StripHtmlTags($body);
      $body = str_replace("\r\n", "\n", $body);
      $body = str_replace("\r", "\n", $body);
      $body = preg_replace("/\n\n+/", "\n", $body);
      $body = explode("\n", $body);
      foreach ($body as $line) {
        if (is_numeric(substr($line, 0, 1)) && !strpos($line, '-') &&  !strpos($line, 'b') &&  !strpos($line, 'esr') &&  !strpos($line, 'plugin') &&  !strpos($line, 'rc')) {
          $new[] = rtrim($line, '/');
        }
      }
      natcasesort($new);
    }
    return $new;
  }

  #===================================================================

  /**
   * Returns release date (epoch) for given Firefox version.
   * @param  string $ver
   * @return integer
   */
  public function getEpochFirefoxVersion($ver) {
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLC . $ver .'/');
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
      $new = array();
      $body = self::StripHtmlTags($body);
      $body = str_replace("\r", "\n", $body);
      $body = preg_replace("/\n\n+/", "\n", $body);
      $body = explode("\n", $body);
      foreach ($body as $line) {
        if (preg_match('/^\d\d?-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d\d\d\d/', $line)) {
          $new[] = $line;
        }
      }
      $latest = end($new);
      return strtotime($latest .' GMT');
    }
    return '';
  }

  #===================================================================

  /**
   *
   *
   */
  private static function strlower($str) {
    return str_replace(' ', '_', strtolower($str));
  }

  #===================================================================

  /**
   *
   *
   */
  private static function StripHtmlTags($str) {
    $str = html_entity_decode($str);
    $str = str_replace('<BODY>', '<body>', $str);
    $str = self::EndExplode('<body>', $str);
    # Strip HTML
    $str = preg_replace('#<br[^>]*?>#siu',                  "\n", $str);
    $str = preg_replace('#<style[^>]*?>.*?</style>#siu',      '', $str);
    $str = preg_replace('#<script[^>]*?.*?</script>#siu',     '', $str);
    $str = preg_replace('#<object[^>]*?.*?</object>#siu',     '', $str);
    $str = preg_replace('#<embed[^>]*?.*?</embed>#siu',       '', $str);
    $str = preg_replace('#<applet[^>]*?.*?</applet>#siu',     '', $str);
    $str = preg_replace('#<noframes[^>]*?.*?</noframes>#siu', '', $str);
    $str = preg_replace('#<noscript[^>]*?.*?</noscript>#siu', '', $str);
    $str = preg_replace('#<noembed[^>]*?.*?</noembed>#siu',   '', $str);
    $str = preg_replace('#<figcaption>.+</figcaption>#siu',   '', $str);
    $str = strip_tags($str);
    # Trim whitespace
    $str = str_replace("\t", '', $str);
    $str = preg_replace('/\ +/', ' ', $str);
    return trim($str);
  }

  #===================================================================

  /**
   *
   *
   */
  private static function EndExplode($glue, $str) {
    if (strpos($str, $glue) === false) {
      return $str;
    }
    $str = explode($glue, $str);
    return end($str);
  }

  #===================================================================
}
