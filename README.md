# Composer plugin allowing you to extend any vendor class

So.. some people might think it's easier to extend just the 10 row
method you're patching instead of copying the whole 1000+ row file over
to your local namespace and whatever else bullshit you're forced
to endure if you do not follow the OneTrueParadigm IoC.

So for real world people, with real world problems - you are welcome!


  example conf (root level of any composer.json file, root or vendor):

  ```
      "extra": {
          "composer-extend-class": {
              "Namespace\\For\\Buggyclass": "App\\Patches\\Buggyclass"
          }
      },
  ```

The new class saved under "namespace" `App\Patches` called `Buggyclass`
source:

```
<?php
namespace Namespace\For;   // using same namespace as old class

class Buggyclass extends Buggyclass_Old  // name to extend == name + _Old
{

    public function brokenFunction($payload)
    {
        return $payload * 100;
    }
}
```

Simple as that. Old class file will be copied and source altered for it to have
the new unique class name of "Class_Old" but same old namespace.

Not quality checked code, no tests, no warranty. Copyleft KludgeWorks LLC.