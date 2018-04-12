# Version To Age

Estimates age of browser and OS software.

### Usage
```php
use peterkahl\Version2age\Version2age;

$user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) CriOS/65.0.3325.152 Mobile/15E5216a Safari/604.1';

$v2a = new Version2age;

$os_name = 'iOS';
$os_vers = '11.3';

$br_name = 'CriOS';
$br_vers = '65.0.3325.152';

echo $os_name.'/'.$os_vers.' is ';
if ($v2a->GetAge($os_name, $os_vers)>365*86400){
  echo 'older ';
}

```
