<?php
/**
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2008-2018 Peter Kahl
 * @license    Apache License, Version 2.0, http://www.apache.org/licenses/LICENSE-2.0
 */

namespace peterkahl\UApolice;

use peterkahl\curlMaster\curlMaster;
use \Exception;

class UApolice {

  /**
   * Filename prefix for cache files.
   * @var string
   */
  const FILEPREFIX = 'UAPOLICE_';

  const URLGITJSON = 'https://github.com/peterkahl/UApolice/src/data.json';

  /**
   * Path of cache directory.
   * @var string
   */
  public $CacheDir;
  
  public $CAbundle;

  /**
   * Enable fetching data from remote hosts.
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
  
  #===================================================================
  
  public function __construct($force = false) {
    if (!is_bool($force)) {
      throw new Exception('Illegal type argument force');
    }
    $this->force = $force;
    $this->curlm = new curlMaster;
    $this->curlm->CacheDir = $this->CacheDir;
    $this->curlm->ca_file  = $this->CAbundle;
    #----------------------------------
    # Local cache
    $CacheFile = $this->CacheDir .'/'. self::FILEPREFIX .'data.json';
    if (!$force && file_exists($CacheFile) && filemtime($CacheFile) > time()-86400) {
      $data = json_decode(file_get_contents($CacheFile), true);
      $this->browsers = $data['browsers'];
      $this->osystems = $data['osystems'];
      $this->epoch    = $data['epoch'];
      return;
    }
    #----------------------------------
    # Fetch from GitHub
    $this->curlm->ForcedCacheMaxAge = 2*86400;
    $answer   = $this->curlm->Request(self::URLGITJSON, 'GET', array(), $this->force);
    $body     = $answer['body'];
    $status   = $answer['status'];
    $error    = $answer['error'];
    unset($answer);
    
    if ($status == '200' && !empty($body)) {
      $data = json_decode($body, true);
      $this->browsers = $data['browsers'];
      $this->osystems = $data['osystems'];
      $this->epoch    = $data['epoch'];
      #--------------------------------
      # Latest browser data
      if ($this->GetBrowserInfoAll()) {
        $data['browsers'] = $this->browsers;
        $data['epoch']    = time();
        $this->epoch      = $data['epoch'];
      }
      file_put_contents($CacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
      return;
    }
    #----------------------------------
    # Local data file
    $LocalFile = __DIR__ .'/data.json';
    if (file_exists($LocalFile)) {
      $data = json_decode(file_get_contents($LocalFile), true);
      $this->browsers = $data['browsers'];
      $this->osystems = $data['osystems'];
      $this->epoch    = $data['epoch'];
      return;
    }

    throw new Exception('No data files found');
  }

  #===================================================================

  public function GetClassName() {
    return __CLASS__;
  }

  #===================================================================

  private function GetBrowserInfoAll() {

    $chr = $this->getLatestVersionChrome();
    $fox = $this->getLatestVersionFirefox();

    $extra = array(
      'chrome'  => $chr['version'],
      'crios'   => $chr['version'],
      'firefox' => $fox['version'],
    );

    $this->browsers = array_merge($this->browsers, $extra);
    return $this->browsers;
  }

  #===================================================================

  private function GetBrowserInfo($br) {

    $br = $this->strlower($br);

    if (array_key_exists($br, $this->$browsers)) {
      return array(
        'version'    => $this->$browsers[$br],
        'released'   => $this->epoch,
        'last_check' => $this->epoch,
      );
    }

    if ($br == 'chrome') {
      return self::getLatestVersionChrome();
    }
    elseif ($br == 'crios') {
      return self::getLatestVersionChrome();
    }
    elseif ($br == 'firefox') {
      return self::getLatestVersionFirefox();
    }

    return array();
  }

  #===================================================================

  public static function getBrCurrentVer($br) {

    $br = self::strlower($br);

    if ($br == 'unknown') {
      return '';
    }

    $found = self::GetBrowserInfo($br);

    if (!empty($found['version'])) {
      return $found['version'];
    }

    return ''; # uncommon browser
  }

  #===================================================================

  public static function getOsCurrentVer($os) {

    $os = self::strlower($os);

    if ($os == 'unknown') {
      return '';
    }

    if (!array_key_exists($os, self::$osystems)) {
      return '';
    }

    return self::$osystems[$os];
  }

  #===================================================================

  public static function isBrOutdated($name, $ver = '') {

    $name = self::strlower($name);

    if ($name == 'unknown' || $ver == '') {
      return array(
        'bool'   => false,
        'factor' => 0,
      );
    }

    $master = self::getBrCurrentVer($name);

    if (empty($master)) {
      return array(
        'bool'   => false, # uncommon browser
        'factor' => 0,
      );
    }

    $outdated = self::VersionDistance($ver, $master);

    if ($outdated) {
      return array(
        'bool'   => true,
        'factor' => $outdated,
      );
    }

    return array(
      'bool'   => false, # current
      'factor' => 0,
    );
  }

  #===================================================================

  public static function isOsOutdated($name, $ver = '') {

    $name = self::strlower($name);

    if ($name == 'unknown' || $ver == '') {
      return array(
        'bool'   => false,
        'factor' => 0,
      );
    }

    if (!array_key_exists($name, self::$osystems)) {
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
          'bool'   => false, # current
          'factor' => 0,
        );
      }
      if (version_compare($ver, '6.3', '>=')) { # Windows 8.1
        return array(
          'bool'   => false,
          'factor' => .5,
        );
      }
      if (version_compare($ver, '6.2', '>=')) { # Windows 8.0
        return array(
          'bool'   => false,
          'factor' => .81,
        );
      }
      if (version_compare($ver, '6.1', '>=')) { # Windows 7.0
        return array(
          'bool'   => false,
          'factor' => .88,
        );
      }
      if (version_compare($ver, '6.0', '>=')) { # Vista
        return array(
          'bool'   => true,
          'factor' => 4,
        );
      }
      if (version_compare($ver, '5.2', '>=')) { # XP
        return array(
          'bool'   => true,
          'factor' => 5,
        );
      }
      # Older
      return array(
        'bool'   => true,
        'factor' => 6,
      );
    }

    $outdated = self::VersionDistance($ver, self::$osystems[$name]);

    if ($outdated) {
      return array(
        'bool'   => true,
        'factor' => $outdated,
      );
    }

    return array(
      'bool'   => false, # current
      'factor' => 0,
    );
  }

  #===================================================================

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

  public static function getLatestVersionChrome() {

    $filename = $this->CacheDir .'/'. self::FILEPREFIX .'VER_CHROME_STABLE.serial';

    if (!this->$force && file_exists($filename) && filemtime($filename) > time() - 2*3600) {
      return unserialize(FileGetContents($filename));
    }

    $this->curlm->ForcedCacheMaxAge = -1;
    $answer   = $this->curlm->Request('http://omahaproxy.appspot.com/all'); # CSV file
    $body     = $answer['body'];
    $status   = $answer['status'];
    $error    = $answer['error'];
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

      FilePutContents($filename, serialize($arr), LOCK_EX);
      return $arr;
    }

    # Try using the aged cache file
    if (file_exists($filename)) {
      return unserialize(FileGetContents($filename));
    }

    return array();
  }

  #===================================================================

  public function getLatestVersionFirefox() {

    $filename = $this->CacheDir .'/'. self::FILEPREFIX .'VER_FIREFOX_STABLE.serial';

    if (!$this->force && file_exists($filename) && filemtime($filename) > time() - 2*3600) {
      return unserialize(FileGetContents($filename));
    }

    $all = $this->getAllVersionsFirefox();
    $version = end($all);

    $epoch = $this->getEpochFirefoxVersion($version);

    $arr = array(
      'version'    => $version,
      'released'   => $epoch,
      'last_check' => time(),
    );

    FilePutContents($filename, serialize($arr), LOCK_EX);
    return $arr;
  }

  #===================================================================

  public function getAllVersionsFirefox() {

    $this->curlm->ForcedCacheMaxAge = -1;
    $answer   = $this->curlm->Request('https://ftp.mozilla.org/pub/firefox/releases/');
    $body     = $answer['body'];
    $status   = $answer['status'];
    $error    = $answer['error'];
    unset($answer);

    if ($status != '200') {
      throw new Exception('HTTP request failed with status '. $status .' '. $error);
    }

    $body = self::StripHtmlTags($body);
    $body = str_replace("\r\n", "\n", $body);
    $body = str_replace("\r", "\n", $body);
    $body = preg_replace("/\n\n+/", "\n", $body);
    $body = explode("\n", $body);
    $new  = array();

    foreach ($body as $line) {
      if (is_numeric(substr($line, 0, 1)) && !strpos($line, '-') &&  !strpos($line, 'b') &&  !strpos($line, 'esr') &&  !strpos($line, 'plugin') &&  !strpos($line, 'rc')) {
        $new[] = rtrim($line, '/');
      }
    }

    natcasesort($new);

    return $new;
  }

  #===================================================================

  public static function getEpochFirefoxVersion($ver) {

    $this->curlm->ForcedCacheMaxAge = -1;
    $answer   = $this->curlm->Request('https://ftp.mozilla.org/pub/firefox/releases/'. $ver .'/');
    $body     = $answer['body'];
    $status   = $answer['status'];
    $error    = $answer['error'];
    unset($answer);

    if ($status != '200') {
      throw new Exception('HTTP request failed with status '. $status .' '. $error);
    }

    $body = self::StripHtmlTags($body);
    $body = str_replace("\r", "\n", $body);
    $body = preg_replace("/\n\n+/", "\n", $body);
    $body = explode("\n", $body);
    $new  = array();

    foreach ($body as $line) {
      if (preg_match('/^\d\d?-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d\d\d\d/', $line)) {
        $new[] = $line;
      }
    }

    $latest = end($new);
    return strtotime($latest .' GMT');
  }

  #===================================================================

  public static function getBrowserLogo($browser, $pixelDim) {
    $browser = self::strlower($browser);
    $file = __DIR__ .'/svg/browser/'. $browser .'.svg';
    if (file_exists($file)) {
      return '<img src="data:image/svg+xml;base64,'. base64_encode(FileGetContents($file)) .'" width="'. $pixelDim .'" height="'. $pixelDim .'">';
    }
    return '';
  }

  #===================================================================

  public static function getOSLogo($os, $pixelDim) {
    $os = self::strlower($os);
    $file = __DIR__ .'/svg/os/'. $os .'.svg';
    if (file_exists($file)) {
      return '<img src="data:image/svg+xml;base64,'. base64_encode(FileGetContents($file)) .'" width="'. $pixelDim .'" height="'. $pixelDim .'">';
    }
    return '';
  }

  #===================================================================

  private static function strlower($str) {
    return str_replace(' ', '_', strtolower($str));
  }

  #===================================================================

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

  private static function EndExplode($glue, $str) {
    if (strpos($str, $glue) === false) {
      return $str;
    }
    $str = explode($glue, $str);
    return end($str);
  }

  #===================================================================
}
