# Autoloader
PSR4 compatible autoloader

While I try to use composer for everything autoloader related.

Sometimes you need an autolaoder outside of composer.  Maybe for a small project etc...

```
use evo\autoloader\Autoloader;

require_once 'src/evo/autoloader/Autoloader.php';

$Autoloader = Autoloader::getInstance();
```

By default autoloader registers an empty namespace in the location it is placed.  

```
$Autoloader->registerPath('', '');

```

This is the most important method besides the `getInstance()` method.  It's defined as follows:

```
registerPath($namespace, $path, [$priority = 100])
```

Where `$namespace` is a namespace such as `evo\\autoloader` and path is a path like `__DIR__.'/scr/evo/'`.  For the most part the autoloader will account for differences like this `\\evo\\autoloader\\` for the namespace, and simularly the path `__DIR__.'/scr/evo'`.  You can use a relative or absolute path with the autoloader.  The `$priority` sorts the registered paths in asending order grouped by namespace.  So priorities that are lower are called first withing namespaces with multiple paths.

Let's go back to the default setting, this is no namespace, no path.  So it will be rooted to whatever path calls the class it's trying to load and starts with an empty namespace.  The simplest way to explain how it figures out paths is like this, it searches the `registerPaths` for the `$namespace` then it adds `$path +  class info` for that namespace.

Now lets assume this file is not `README.md` but is instead `index.php`.  In this same folder we have a simple class like this:

```
//location  /
class A
{
}
```

With just the default settings this class can be loaded.  The autolaoder looks up the namespace finds `''` empty, then it adds the empty path an the class name `A.php` and we get the relative path to the same folder `index.php` is located in and with no namespace we simply look for the filename, which is found.

Now lets try a few more examples:

```
//location  /src/evo/
namespace src\evo;

class B
{
}
```

Like class `A` this class will load just fine.  Let's work out the path, remember we had `''` for both arguments in `registerPath` which is the default path that gets registered.  Like the first example, we again find the current location and add the namespace from the class and the class name, this works out to `/src/evo/B.php`.  You might be thinking that the autoloader searches for the `registerPath` based on the namespace, it is what I said after all, but we never registered the `src\evo` namespace with a path. Lucky for us the autolaoder is smart enough that if it doesn't find a path for a given namespace it will work backwards through the namespace given by the class.  It starts looking in `registerPath` with `src\evo` which it doesn't find. Then it removes one segment and looks for `src`, again which it doesn't find. Finally it remove the last segment and looks for `''` which we do have registered, by default.  Then it takes the path for that namespace and adds the namespaces for the class and the class name.  The path for this works out to be `/src/evo/B.php` which is correct for this file.

Let's look at a clss that can't be loaded with the default settings:

```
//location  /src/evo/
namespace evo;

class C
{
}
```

Here we are missing the `src` part of the namespace, and it's not accounted for in the path.  In this case the autoloader will not find the file to load.  It should be pretty obvious why. The autoloader can't find the namespace so it uses the default again just like described above, then adds the info for the class. This works out to `/evo/C.php` but we're located above the `src` folder so that folder is missing from the autolaoder's calculation.  Lucky for us we can register as many namespace/path pairs as we need to and the way to register this path is simply:

```
$Autoloader->registerPath('', __DIR__.'/src');
```

Now when we add the arguments and class information up we get this `src + evo + C.php` or `src/evo/C.php` which is exactly what we need.

Ok, we've gotten pretty far without touching the namespace argument at all.  The above class could have also been registered like this:

```
$Autoloader->registerPath('evo', __DIR__.'/src/evo');
```

This to will correctly reslove the path to the file.  The way it figures this out is that the autolaoder looks up the namespace `evo` which it finds with the path of `__DIR__.'/src/evo'`.  Now because it found the `evo` part of the class namespace it doesn't add this to the path, and there is nothing left in the namespace in this case, so it just adds the class name and we wind up with this `__DIR__.'/src/evo/C.php`. Which is essentially the same thing as the above example.

The amount of work the autolaoder had to do in the above example is a bit less then the preceeding one, so you might get a bit of performance gain for registering the namespace this way.  That said, the real advantage of this method comes into play when the class is located in a path that has no logical relationship to the namespace.  For example:

```
//location  /src/evo/
namespace foo\bar;

class D
{
}
```

As you can see the namespace `foo\bar` does not match the path at all `src/evo`.  Less flexable autoloader might call it a day on this setup, but we can handle this just fine by using this namespace / path pair.

```
$Autoloader->registerPath('foo\bar', __DIR__.'/src/evo');
```

Like the previous example ( the second example for class C ), the autolaoder looks up the namespace in our `registerPaths` and finds the above path `__DIR__.'/src/evo'`, then because the whole namespace was found it just adds the class name on for `__DIR__.'/src/evo/D.php'` which is exactly where the class is located.

The last thing we need to cover is setting multiple paths for the same namespace, yes that is a thing.

```
$Autoloader->registerPath('foo\bar', __DIR__.'/src/evo');
$Autoloader->registerPath('foo\bar', __DIR__.'/foo/bar');
```

Now the autoloader will go though each of these paths when it finds the namespace.


Hopefully those examples how explain how the autoloader process works. There are a few other methods I'd like to mention.  The first of which is the debugging method.  This is important because autoloading can be hard sometimes.  Another chalange is autoloaders do a lot, so just printing stuff out can wind up spewing out so much stuff that it becomes worthless. Let's look at the debug method and how it's uses.

```
$Autoloader = Autoloader::getInstance();
$Autoloader->setDebug(true);
new C();
$Autoloader->setDebug(false);
```

Sense the autoloader is a singleton, we can call `getInstance` and get the same instance of the class as was created before.  This is true no matter where we call it from.  There is no need to have more then one instance of an autolaoder like this one. The dubug method is pretty simple, it accepts a `true` or `false` boolean value.  True turns on output, false turns it off.  So here we are turning output on calling a class we had issues with, then turning it off.  This prevents the autolaoder from spitting out an overwhelming amount of information. 

For this example will use class `C` that we couldn't load at first, the debug output looks something like this:

```
============================ evo\autoloader\Autoloader::debug ============================
evo\autoloader\Autoloader::splAutoload evo\C
Checking class: C
Checking namespace: evo
checking pathname:/evo/C.php
==========================================================================================
```

Now we will look at the same class but the second example where we got the class to load by registering the namespace with a path:

```
============================ evo\autoloader\Autoloader::debug ============================
evo\autoloader\Autoloader::splAutoload evo\C
Checking class: C
Checking namespace: evo
checking pathname:/evo/src/C.php
Found: /evo/src/C.php
==========================================================================================
```
If you compare these, you will notice the first does no have the `Found: ` bit that just indicates where the file was found at. It's also possible to have multiple `checking pathname:` entries if it has to walk back thought the `registerPaths` and namespaces as described above.

Most of the other methods should be pretty self explanatory, so below you can find a list of all the public methods.

```
//Get the autoloader instance
public static function getInstance($throw = false, $prepend = false)

//set debug (covered above)
public function setDebug($debug = false)

//called whenever a class is loaded, has to be public but not really intended for use
public function splAutoload($class)

//register a namespace path pair
public function getRegisteredPaths($namespace = null)

//register a namespace path pair (covered above)
public function registerPath($namespace, $path, $priority = self::DEFAULT_PRIORITY)

//remove a path that was registered, you can remove a whole namespace or a path within the namespace
public function unloadPath($namespace, $path = null)

//check if a namespace is registered
public function isRegistered($namespace, $path = null)

//get the path that a class was found at, null get all classes and their paths
public function getLoadedFile($class = null);

``` 

Enjoy




