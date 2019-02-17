<?php
/**
 * Version To Age
 * Estimates age of browser and OS software.
 *
 * @version    2019-02-17 06:31:00 UTC
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2018-2019 Peter Kahl
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
   * Cipher string for cURL (optional)
   * @var string ... example 'AESGCM:!PSK'
   */
  public $CipherString = '';

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


  /**
   * Data array is created from local and remote sources.
   * Data array is updated if outdated.
   * Data are stored in cache.
   * @param  boolean $force ... Forces instant requests to remote hosts to fetch
   *                            fresh values.
   * @throws \Exception
   */
  public function Initialise($force = false) {

    if (!is_bool($force)) {
      throw new Exception('Illegal type argument force');
    }

    $this->LoadDefaultData();

    if (!$this->FetchRemoteData) {
      # This is for applications when internet connection is not available/desired.
      return;
    }

    if (empty($this->CacheDir)) {
      throw new Exception('Property CacheDir cannot be empty');
    }

    # Local cache file
    $this->CacheFile = $this->CacheDir .'/'. self::FILEPREFIX .'data.json';

    if (!$force && file_exists($this->CacheFile) && filemtime($this->CacheFile) > time()-86400) {
      # This is normal mode of operation, especially when crontab
      # job is properly set up, that's once every 6-23 hours.

      # Read from local cache
      $cache = json_decode(file_get_contents($this->CacheFile), true);

      # Is the cache file outdated (cache file has older time stamp than local file)?
      # Must check which- local file OR local cache file
      # has newer values (timestamp)
      if ($cache['released'] >= $this->released) {
        # Cache file is newer than the local file.
        # The cache file is good.
        $this->data     = $cache['data'];
        $this->released = $cache['released'];
        return;
      }
    }

    # Now, either force==true (crontab job) OR cache file is outdated/non-existent.
    # Ideally, this should always be case ONLY for crontab job.

    # Fetch latest Chrome version string and timestamp
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
        # Write the value into data
        $this->data['chrome'][$ver] = $temp['released'];
        $this->released = time();
      }
    }

    # Fetch latest Firefox version string and timestamp
    $temp = $this->getLatestVersionFirefox();
    if (!empty($temp) && strpos($temp['version'], '.') != false) {
      $ver = $temp['version'];
      $arr = explode('.', $ver);
      $new = array();
      for ($n = 0; $n < 2; $n++) {
        $new[] = $arr[$n];
      }
      $ver = implode('.', $new);
      if (!isset($this->data['firefox'][$ver])) {
        # Write the value into data
        $this->data['firefox'][$ver] = $temp['released'];
        $this->released = time();
      }
    }

    $this->last_check = time();

    $this->SaveData();
  }


  /**
   * Saves data file in JSON format.
   *
   */
  private function SaveData() {

    $data = array(
      'description' => 'Estimates age of browser and OS software.',
      'homepage'    => 'https://github.com/peterkahl/Version2age',
      'copyright'   => 'Peter Kahl',
      'license'     => 'Apache-2.0',
      'released'    => $this->released,
      'data'        => $this->data,
    );

    file_put_contents($this->CacheFile, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  }


  /**
   * Loads data from local file.
   * @throws \Exception
   */
  private function LoadDefaultData() {

    $LocalFile = __DIR__ .'/data.json';

    if (file_exists($LocalFile)) {
      $temp = json_decode(file_get_contents($LocalFile), true);
      $this->data     = $temp['data'];
      $this->released = $temp['released'];
      return;
    }

    throw new Exception('Cannot read/find file '. $LocalFile);
  }


  /**
   * Returns age of browser/OS in seconds.
   * @param  string   $name ... name of browser/OS
   * @param  string   $ver .... version
   * @throws \Exception
   */
  public function GetAge($name, $ver) {

    if (empty($this->data)) {
      $this->Initialise();
    }

    $lcnam = $this->strlower($name);

    $alias = array(
      'mobile_safari' => 'safari',
      'crios'         => 'chrome',
    );

    if (array_key_exists($lcnam, $alias)) {
      $lcnam = $alias[$lcnam];
    }

    if (!array_key_exists($lcnam, $this->data)) {
      return 'UNKNOWN'; # The software name does not exist in our database.
    }

    if ($lcnam == 'edge') {
      $ver = $this->EndExplode('.', $ver);
    }

    # Strip away non-numeric
    $ver = preg_replace('/[a-z]/i', ' ', $ver);
    $ver = $this->EndExplode(' ', $ver);

    if (array_key_exists($ver, $this->data[$lcnam])) {
      return time() - $this->data[$lcnam][$ver];
    }
    if (substr_count($ver, '.') > 1) {
      $arr = explode('.', $ver);
      $ver = $arr[0] .'.'. $arr[1];
      if (array_key_exists($ver, $this->data[$lcnam])) {
        return time() - $this->data[$lcnam][$ver];
      }
    }
    $temp = $this->data[$lcnam];
    $verNorm = $this->Str2float($ver, $lcnam);
    foreach ($temp as $str => $time) {
      $val = $this->Str2float($str, $lcnam);
      # We are comparing floats!
      if ($val < $verNorm) {
        $low = array('time'=>$time, 'val'=>$val);
      }
      else {
        $high = array('time'=>$time, 'val'=>$val);
        break;
      }
    }

    if (empty($high) || empty($low)) {
      return 0;
    }

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


  /**
   * Converts string to float or integer (removes subsequent periods).
   * @param  string   $str ..... version of browser
   * @param  string   $name .... nickname of browser
   * @return mixed
   */
  private function Str2float($str, $name) {

    if ($name == 'edge') {
      return (integer) $str;
    }

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

    return (float) $arr[0] + $arr[1]/$scale;
  }


  /**
   * Makes HTTP request to external URL and returns a string.
   * @return string
   * @throws \Exception
   */
  private function HTTPRequest($url) {

    if (empty($this->CAbundle)) {
      throw new Exception('Property CAbundle cannot be empty');
    }

    $this->curlm = new curlMaster;
    $this->curlm->ca_file           = $this->CAbundle;
    $this->curlm->CacheDir          = $this->CacheDir;
    $this->curlm->useragent         = self::USERAGENT;
    $this->curlm->CipherString      = $this->CipherString;
    $this->curlm->ForcedCacheMaxAge = -1;

    $answer = $this->curlm->Request($url, 'GET', $data = array(), true);

    $body   = $answer['body'];
    $status = $answer['status'];

    unset($answer);

    if ($status == '200') {
      return $body;
    }

    return '';
  }


  /**
   * Makes HTTP request to external URL and returns the latest version of Chrome.
   * @return array
   * @throws \Exception
   */
  private function getLatestVersionChrome() {

    $body = $this->HTTPRequest(self::URLB);

    if (!empty($body)) {
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


  /**
   * Makes HTTP request to external URL and returns the latest version of Firefox.
   * @return array
   * @throws \Exception
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


  /**
   * Makes HTTP request to external URL and returns all versions of Firefox.
   * @return array
   * @throws \Exception
   */
  public function getAllVersionsFirefox() {

    $body = $this->HTTPRequest(self::URLA);

    if (!empty($body)) {
      $new = array();
      $body = $this->StripHtmlTags($body);
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


  /**
   * Returns release date (epoch) for given Firefox version.
   * @param  string $ver
   * @return integer
   * @throws \Exception
   */
  public function getEpochFirefoxVersion($ver) {

    $body = $this->HTTPRequest(self::URLA);

    if (!empty($body)) {
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


  /**
   * Converts string to lower case and replaces whitespace with '_'.
   * @param  string   $str
   * @return string
   */
  private function strlower($str) {
    return str_replace(' ', '_', strtolower($str));
  }


  /**
   * Strips all HTML tags.
   * @param  string   $str
   * @return string
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


  /**
   * Returns last element of a string after an identifiable glue.
   * @param  string   $glue  $str
   * @return string
   */
  private function EndExplode($glue, $str) {
    if (strpos($str, $glue) === false) {
      return $str;
    }
    $str = explode($glue, $str);
    return end($str);
  }


}
