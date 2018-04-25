<?php
/**
 * Version To Age
 * Estimates age of browser and OS software.
 *
 * @version    2018-04-25 12:25:00 GMT
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2018 Peter Kahl
 * @license    Apache License, Version 2.0
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace peterkahl\Version2age;

use peterkahl\curlMaster\curlMaster;
use \Exception;

class Version2age {

  /**
   * Filename prefix for cache files.
   * @var string
   */
  const FILEPREFIX = 'VER2AGE_';

  /**
   * Filename prefix for cache files.
   * @var string
   */
  const USERAGENT = 'Mozilla/5.0 (Version2age; +https://github.com/peterkahl/Version2age)';

  /**
   * URL to fetch latest data on Firefox
   * @var string
   */
  const URLA = 'https://ftp.mozilla.org/pub/firefox/releases/';

  /**
   * URL to fetch latest data on Chrome
   * @var string
   */
  const URLB = 'http://omahaproxy.appspot.com/all';

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
   * Data array is created from local and remote sources.
   * Data array is updated if outdated.
   * Data are stored in cache.
   * @param  boolean $force ... Disables reading of cache, thus forcing
   *                            new requests to remote hosts to fetch
   *                            fresh data.
   * @throws \Exception
   */
  public function Initialise($force = false) {
    if (!is_bool($force)) {
      throw new Exception('Illegal type argument force');
    }
    if (!$this->FetchRemoteData) {
      $this->UseLocalData();
      return;
    }
    #----------------------------------
    if (empty($this->CacheDir)) {
      throw new Exception('Property CacheDir cannot be empty');
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
    if (file_exists($this->CacheFile)) {
      $temp = json_decode(file_get_contents($this->CacheFile), true);
      $this->data       = $temp['data'];
      $this->released   = $temp['released'];
    }
    else {
      $this->UseLocalData();
    }
    #----------------------------------
    if (empty($this->CAbundle)) {
      throw new Exception('Property CAbundle cannot be empty');
    }
    #----------------------------------
    $this->last_check = time();
    #----------------------------------
    # Latest browser data
    $temp = $this->getLatestVersionChrome();
    if (!empty($temp)) {
      $ver = $temp['version'];
      $arr = explode('.', $ver);
      $new = array();
      for ($n = 0; $n < 2; $n++) {
        $new[] = $arr[$n];
      }
      $ver = implode('.', $new);
      if (!isset($this->data['chrome'][$ver])) {
        $this->data['chrome'][$ver] = $temp['released'];
      }
    }
    $temp = $this->getLatestVersionFirefox();
    if (!empty($temp)) {
      $ver = $temp['version'];
      $arr = explode('.', $ver);
      $new = array();
      for ($n = 0; $n < 2; $n++) {
        $new[] = $arr[$n];
      }
      $ver = implode('.', $new);
      if (!isset($this->data['firefox'][$ver])) {
        $this->data['firefox'][$ver] = $temp['released'];
      }
    }
    $this->SaveData();
  }

  #===================================================================

  private function SaveData() {
    $temp = array(
      'description' => 'Estimates age of browser and OS software.',
      'homepage'    => 'https://github.com/peterkahl/Version2age',
      'copyright'   => 'Peter Kahl',
      'license'     => 'Apache-2.0',
      'released'    => $this->released,
      'data'        => $this->data,
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

  #===================================================================

  public function GetAge($name, $ver) {

    if (empty($this->data)) {
      $this->Initialise();
    }

    $name = $this->strlower($name);

    $alias = array(
      'mobile_safari' => 'safari',
      'crios'         => 'chrome',
    );

    if (array_key_exists($name, $alias)) {
      $name = $alias[$name];
    }

    if (!array_key_exists($name, $this->data)) {
      return 'UNKNOWN'; # The software name does not exist in our database.
    }

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
    $verNorm = $this->Str2float($ver, $name);
    foreach ($temp as $str => $time) {
      $val = $this->Str2float($str, $name);
      # We are comparing floats!
      if ($val < $verNorm) {
        $low = array('time'=>$time, 'val'=>$val);
      }
      else {
        $high = array('time'=>$time, 'val'=>$val);
        break;
      }
    }
    #----
    if (empty($high) || empty($low)) {
      return 0;
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

  private function Str2float($str, $name) {
    if (substr_count($str, '.') < 1) {
      $str .= '.0';
    }
    $arnm = array(
      'ios'       => 20,
      'macos'     => 20,
      'windows'   =>  5,
      'seamonkey' => 55,
      'lunascape' => 20,
      'firefox'   => 11,
    );
    $scale = 10;
    if (array_key_exists($name, $arnm)) {
      $scale = $arnm[$name];
    }
    $arr = explode('.', $str);
    if ($name == 'edge') {
      return $arr[1];
    }
    return (float) $arr[0] + $arr[1]/$scale;
  }

  #===================================================================

  /**
   *
   *
   */
  private function getLatestVersionChrome() {
    $this->curlm = new curlMaster;
    $this->curlm->ca_file   = $this->CAbundle;
    $this->curlm->CacheDir  = $this->CacheDir;
    $this->curlm->useragent = self::USERAGENT;
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
  private function getLatestVersionFirefox() {
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
    $this->curlm = new curlMaster;
    $this->curlm->ca_file   = $this->CAbundle;
    $this->curlm->CacheDir  = $this->CacheDir;
    $this->curlm->useragent = self::USERAGENT;
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLA);
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
      $body = $this->StripHtmlTags($body);
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
    $this->curlm = new curlMaster;
    $this->curlm->ca_file   = $this->CAbundle;
    $this->curlm->CacheDir  = $this->CacheDir;
    $this->curlm->useragent = self::USERAGENT;
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLA . $ver .'/');
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
      $new = array();
      $body = $this->StripHtmlTags($body);
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
  private function strlower($str) {
    return str_replace(' ', '_', strtolower($str));
  }

  #===================================================================

  /**
   *
   *
   */
  private function StripHtmlTags($str) {
    $str = html_entity_decode($str);
    $str = str_replace('<BODY>', '<body>', $str);
    $str = $this->EndExplode('<body>', $str);
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
  private function EndExplode($glue, $str) {
    if (strpos($str, $glue) === false) {
      return $str;
    }
    $str = explode($glue, $str);
    return end($str);
  }

  #===================================================================
}
