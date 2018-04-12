# Version To Age

Estimates age of browser and OS software.

### Usage
```php
use peterkahl\Version2age\Version2age;

$v2a = new Version2age;

# Perhaps you have user agent string like this...
# $user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) CriOS/65.0.3325.152 Mobile/15E5216a Safari/604.1';

# then you'll parse it and get this...
$os_name = 'iOS';
$os_vers = '11.3';

if ($v2a->GetAge($os_name, $os_vers)>365*86400){
  echo 'Older than 1 year.';
}
else {
  echo 'Less than 1 year old.';
}

```
