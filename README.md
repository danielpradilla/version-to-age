# Version To Age

[![License](http://img.shields.io/:license-apache-blue.svg)](http://www.apache.org/licenses/LICENSE-2.0.html)
[![If this project has business value for you then don't hesitate to support me with a small donation.](https://img.shields.io/badge/Donations-via%20Paypal-blue.svg)](https://www.paypal.me/PeterK93)

Software age gauge. Estimates age of browser and OS software. The script stores an associative array of software names and version and timestamp pairs. It also periodically connects to external servers to fetch the latest information on Firefox and Chrome browsers.

![image](https://github.com/peterkahl/version-to-age/blob/master/screen-shot.png "Screenshot of user agent and age of software.")

### Limitations

This script knows only that which is stored in its data array.

Browsers:
* chrome
* crios
* edge
* explorer
* firefox
* lunascape
* maxthon
* safari
* mobile safari
* netsurf
* opera
* samsungbrowser
* seamonkey

Operating Systems:
* android
* ios
* macos
* windows

### Usage
```php
use peterkahl\Version2age\Version2age;

$v2a = new Version2age;

# Location of CA certificate file
# You may download and install on your server this Mozilla CA bundle
# from this page: <https://curl.haxx.se/docs/caextract.html>
$v2a->CAbundle = '/srv/certs/ca-bundle.pem';

# Location of your cache directory
$v2a->CacheDir = '/srv/cache';

# Perhaps you have user agent string like this
# $user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) CriOS/65.0.3325.152 Mobile/15E5216a Safari/604.1';
# I have used this parser at <https://github.com/peterkahl/user-agent-parser>
# to parse the above string down to
$os_name = 'iOS'; # case insensitive
$os_vers = '11.3';

$age = $v2a->GetAge($os_name, $os_vers);
$age = $age/31536000; # years

if (is_string($age) && $age == 'UNKNOWN') {
  echo 'I\'m sorry. I don\'t know this software.'
}
elseif ($age >= 1) {
  echo 'Your software is 1 year old or older.';
}

```

### Crontab Job to keep up-to-date
Run the script below every 6 hours. This forces connection to external servers in order to fetch the most up-to-date data on Firefox and Chrome browsers.
```php
use peterkahl\Version2age\Version2age;

# Location of CA certificate file
# You may download and install on your server this Mozilla CA bundle
# from this page: <https://curl.haxx.se/docs/caextract.html>
$v2a->CAbundle = '/srv/certs/ca-bundle.pem';

# Location of your cache directory
$v2a->CacheDir = '/srv/cache';

$v2a->Initialise(true);

```
