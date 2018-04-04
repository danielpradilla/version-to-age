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
   * Oldest date possible.
   * @var integer
   */
  const EPOCH_ZERO = 946684800; # 1 Jan 2000

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
   * Most recent version of browsers.
   * @var array
   */
  private $browsers;

  /**
   * Most recent version of OS.
   * @var array
   */
  private $osystems;

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
      $data = json_decode(file_get_contents($this->CacheFile), true);
      $this->browsers   = $data['browsers'];
      $this->osystems   = $data['osystems'];
      $this->released   = $data['released'];
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
      $data = json_decode($body, true);
      $this->browsers   = $data['browsers'];
      $this->osystems   = $data['osystems'];
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
    $data['browsers']  = $this->browsers;
    $data['osystems']  = $this->osystems;
    $data['released']  = $this->released;
    $data['homepage']  = 'https://github.com/peterkahl/Sage';
    $data['copyright'] = '2018 Peter Kahl';
    $data['license']   = 'Apache-2.0';
    file_put_contents($this->CacheFile, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  }

  #===================================================================

  /**
   * Loads data from local file.
   * @throws \Exception
   */
  private function UseLocalData() {
    $LocalFile = __DIR__ .'/data.json';
    if (file_exists($LocalFile)) {
      $data = json_decode(file_get_contents($LocalFile), true);
      $this->browsers   = $data['browsers'];
      $this->osystems   = $data['osystems'];
      $this->released   = $data['released'];
      $this->last_check = time();
      return;
    }
    throw new Exception('No data files found');
  }

  #===================================================================

  public function GetAgeOs($name, $ver) {
    if (array_key_exists($ver, $this->osystems[$name])) {
      return time() - $this->osystems[$name][$ver];
    }
    $temp = $this->osystems[$name];
    $temp = array_flip($temp);
    $new = array();
    foreach ($temp as $str => $time) {
      $new[$time] = $this->Str2Val($str);
    }
  }

  #===================================================================

  private function Str2Val($str) {
    for ($n = 0; $n < 2; $n++) {
      if (substr_count($str, '.') < 2) {
        $str .= '.0';
      }
    }
    list($int, $frs, $sec) = explode('.', $str);
    return $int + $frs/10 + $sec/100;
  }

  #===================================================================

  /**
   * Returns name of the class.
   * @return string
   */
  public function GetClassName() {
    return __CLASS__;
  }

  #===================================================================

  /**
   * Fetches latest data on Firefox and Chrome and adds these
   * to the browser array.
   */
  private function GetBrowserInfoAll() {

    $chr = $this->getLatestVersionChrome();
    $fox = $this->getLatestVersionFirefox();

    $this->browsers['chrome']  = $chr['version'];
    $this->browsers['crios']   = $chr['version'];
    $this->browsers['firefox'] = $fox['version'];

    $this->released = max($fox['released'], $chr['released'], $this->released);
  }

  #===================================================================

  /**
   * Returns data on specific browser.
   * @param  string $br
   * @return array
   */
  private function GetBrowserInfo($br) {

    $br = $this->strlower($br);

    if ($this->FetchRemoteData) {
      if ($br == 'chrome') {
        return self::getLatestVersionChrome();
      }
      elseif ($br == 'crios') {
        return self::getLatestVersionChrome();
      }
      elseif ($br == 'firefox') {
        return self::getLatestVersionFirefox();
      }
    }

    if (array_key_exists($br, $this->browsers)) {
      return array(
        'version'    => $this->browsers[$br],
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
  public function getBrCurrentVer($br) {

    $br = $this->strlower($br);

    if ($br == '') {
      return '';
    }

    $found = $this->GetBrowserInfo($br);

    if (!empty($found['version'])) {
      return $found['version'];
    }

    return ''; # uncommon browser
  }

  #===================================================================

  /**
   *
   *
   */
  public function getOsCurrentVer($os) {

    $os = $this->strlower($os);

    if ($os == '') {
      return '';
    }

    if (!array_key_exists($os, $this->osystems)) {
      return '';
    }

    return $this->osystems[$os];
  }

  #===================================================================

  /**
   *
   *
   */
  public function isBrOutdated($name, $ver = '') {

    $name = $this->strlower($name);

    if ($name == '' || $ver == '') {
      return array(
        'bool'   => false,
        'factor' => 0,
      );
    }

    $master = $thus->getBrCurrentVer($name);

    if (empty($master)) {
      return array(
        'bool'   => false, # uncommon browser
        'factor' => 0,
      );
    }

    $outdated = $this->VersionDistance($ver, $master);

    if ($outdated > 1) {
      return array(
        'bool'   => true,
        'factor' => $outdated,
      );
    }

    return array(
      'bool'   => false, # current
      'factor' => $outdated,
    );
  }

  #===================================================================

  /**
   *
   *
   */
  public function isOsOutdated($name, $ver = '') {

    $name = $this->strlower($name);

    if ($name == '' || $ver == '') {
      return array(
        'bool'   => false,
        'factor' => 0,
      );
    }

    if (!array_key_exists($name, $this->osystems)) {
      return array(
        'bool'   => false, # uncommon OS
        'factor' => 0,
      );
    }

    if ($name == 'windows') {
      #  NT        Windows
      #------------------
      # 10.0 ..... 10
      #  6.3 ..... 8.1
      #  6.2 ..... 8.0
      #  6.1 ..... 7.0
      #  6.0 ..... Vista
      #  5.2 ..... XP
      #  5.1 ..... XP
      #  5.0 ..... 2000
      if (version_compare($ver, '10.0', '>=')) {
        return array(
          'bool'     => false, # current
          'factor'   => 0,
          'released' => 1438128000, # 29 July 2015
        );
      }
      if (version_compare($ver, '6.3', '>=')) { # Windows 8.1
        return array(
          'bool'     => false,
          'factor'   => .5,
          'released' => 1396915200, # 8 April 2014
        );
      }
      if (version_compare($ver, '6.2', '>=')) { # Windows 8.0
        return array(
          'bool'     => false,
          'factor'   => .81,
          'released' => 1351209600, # 26 October 2012
        );
      }
      if (version_compare($ver, '6.1', '>=')) { # Windows 7.0
        return array(
          'bool'     => false,
          'factor'   => .88,
          'released' => 1256169600, # 22 Oct 2009
        );
      }
      if (version_compare($ver, '6.0', '>=')) { # Vista
        return array(
          'bool'     => true,
          'factor'   => 4,
          'released' => 1164844800, # 30 Nov 2006
        );
      }
      if (version_compare($ver, '5.2', '>=')) { # XP
        return array(
          'bool'     => true,
          'factor'   => 5,
          'released' => 1048809600, # 28 March 2003
        );
      }
      # Older, use default
      return array(
        'bool'     => true,
        'factor'   => 6,
        'released' => 946684800, # 1 Jan 2000
      );
    }

    $distance = $this->VersionDistance($ver, self::$osystems[$name]);

    if ($distance > 1) {
      $outdated = true;
    }
    else {
      $outdated = false;
    }

    return array(
      'bool'   => $outdated,
      'factor' => $distance,
    );
  }

  #===================================================================

  /**
   *
   *
   */
  public static function VersionDistance($test, $master) {

    if ($test === $master) {
      return 0;
    }
    
    if (substr_count('.', $master) < 1) {
      $master = $master .'.0';
    }
    
    if (substr_count('.', $test) < 1) {
      $test = $test .'.0';
    }

    $master = explode('.', $master);
    $test   = explode('.', $test);

    #    major  minor  build  patch
    #         \  \    /     /
    #          \  |  |     /
    #           | |  |    |
    # Version: 62.0.3202.89
    # $n ....   0 1 2
    # We're concerned only with major & minor!

    for ($n = 0; $n < 2; $n++) {
      $test[$n]   = (integer) $test[$n];
      $master[$n] = (integer) $master[$n];
    }

    # Normalise values
    $big = max($test[1], $master[1]);
    $test[1]   = $test[1]  /($big*1.01);
    $master[1] = $master[1]/($big*1.01);
    
    $t = $test[0]   + $test[1];
    $m = $master[0] + $master[1];

    # Subtract
    $diff = $m - $t;

    if ($diff > 0) {
      return $diff;
    }
    return 0;
  }

  #===================================================================

  /**
   *
   *
   */
  public function getLatestVersionChrome() {

    $filename = $this->CacheDir .'/'. self::FILEPREFIX .'VER_CHROME_STABLE.json';

    if (!this->$force && file_exists($filename) && filemtime($filename) > time() - 2*3600) {
      return json_decode(FileGetContents($filename), true);
    }

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

      $arr = array(
        'version'    => $temp[2],
        'released'   => strtotime('20'. $year .'-'. $month .'-'. $day.' 00:00:00 GMT'),
        'last_check' => time(),
      );

      FilePutContents($filename, json_encode($arr, JSON_UNESCAPED_UNICODE), LOCK_EX);
      return $arr;
    }

    # Try using the aged cache file
    if (file_exists($filename)) {
      return unserialize(FileGetContents($filename));
    }

    return array();
  }

  #===================================================================

  /**
   *
   *
   */
  public function getLatestVersionFirefox() {

    $filename = $this->CacheDir .'/'. self::FILEPREFIX .'VER_FIREFOX_STABLE.json';

    if (!$this->force && file_exists($filename) && filemtime($filename) > time() - 2*3600) {
      return json_decode(FileGetContents($filename), true);
    }

    $all = $this->getAllVersionsFirefox();
    $version = end($all);

    $epoch = $this->getEpochFirefoxVersion($version);

    $arr = array(
      'version'    => $version,
      'released'   => $epoch,
      'last_check' => time(),
    );

    FilePutContents($filename, json_encode($arr, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $arr;
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
    $new = array();
    $this->curlm->ForcedCacheMaxAge = -1;
    $answer = $this->curlm->Request(self::URLC . $ver .'/');
    $body   = $answer['body'];
    $status = $answer['status'];
    unset($answer);
    if ($status == '200') {
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
  public static function getBrowserLogo($browser, $pixelDim) {
    $browser = self::strlower($browser);
    $file = __DIR__ .'/svg/browser/'. $browser .'.svg';
    if (file_exists($file)) {
      return '<img src="data:image/svg+xml;base64,'. base64_encode(FileGetContents($file)) .'" width="'. $pixelDim .'" height="'. $pixelDim .'">';
    }
    return '';
  }

  #===================================================================

  /**
   *
   *
   */
  public static function getOSLogo($os, $pixelDim) {
    $os = self::strlower($os);
    $file = __DIR__ .'/svg/os/'. $os .'.svg';
    if (file_exists($file)) {
      return '<img src="data:image/svg+xml;base64,'. base64_encode(FileGetContents($file)) .'" width="'. $pixelDim .'" height="'. $pixelDim .'">';
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
