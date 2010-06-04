![sphasps](http://yscik.com/sphasps/logo.png)

This is a [SASS](http://sass-lang.com/) parser for PHP. Enjoy.

About
-----

The project's goal is to easily incorporate SASS to your PHP application without additional dependency on Ruby. 

Most of the code was written 3 months ago, and at that time it contained some syntax extensions bringing it closer to PHP - like $ prefix for variables.
The recent version 3 of SASS includes this, so it's not really an extra feature here, but similar additions might appear in the future. 

Current status
--------------

Most SASS features are complete and ready to use. These are not:

* @extend
* Functions
* Type conversions
* Color operations

Output formatting is a bit messy though, and other smaller bits and pieces of functionality might be missing. 

SCSS syntax is not supported.

Usage
-----
     include("sphasps/sphasps.php");
     
     $css = Sphasps::parse("stylesheet.sass");

And propably something like:

     file_put_contents("styles.css", $css);

Note that this library is not intented for use in production environment. You should only deploy the static CSS files, not parse them from SASS at every page request.  

License
-------

[MIT](http://github.com/yscik/sphasps/blob/master/LICENSE)