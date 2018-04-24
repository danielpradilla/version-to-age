# Version To Age

Estimates age of browser and OS software.

### Usage
```php
use peterkahl\Version2age\Version2age;

$v2a = new Version2age;

# Location of CA certificate file
$v2a->CAbundle = '/srv/certs/ca-bundle.pem';

# Location of your cache directory
$v2a->CacheDir = '/srv/cache';

# Perhaps you have user agent string like this
# $user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) CriOS/65.0.3325.152 Mobile/15E5216a Safari/604.1';
# So you'll parse it and get this
$os_name = 'iOS';
$os_vers = '11.3';

$age = $v2a->GetAge($os_name, $os_vers);
$age = $age/31536000; # years

if ($age >= 1) {
  echo 'You will be subjected to scrutiny.';
}

```
