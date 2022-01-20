<?php

/**
 * @copyright Copyright (c) 2022 Philipp Stappert <mail@phsta.de>
 *
 * @author Philipp Stappert <mail@phsta.de>
 *
 * @license MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DLRG-Kursplanungstool by Philipp Stappert
 * https://www.phsta.de/
 */

use \kpt\core\exception\BaseException;
use \kpt\core\helper\LogHelper;
use \kpt\core\model\Configuration as conf;

/**
 * Preparation
 */
/** register error handler */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
	throw new \kpt\core\exception\BaseException($errstr);
});

/** autoload needed classes from /lib/ folder with spl autoload function */
spl_autoload_register(function ($className) {
	$className = str_replace("\\", DIRECTORY_SEPARATOR, $className); // replace \ of class with /
	include_once $_SERVER["DOCUMENT_ROOT"] . "/lib/" . $className . ".php"; // autoload matching files
});



/**
 * Site
 */
$config = new conf; // make config.php avaliable as $config

/** check host matches the given one in config.php */
if ($_SERVER['HTTP_HOST'] !== $config->getConfigValue("site.host")) {
	// throw error and exit if host doesn't match
	throw new BaseException("Die Adresse, mit der Sie das KPT aufrufen, ist nicht gültig. Bitte wenden Sie sich an den/die Administrator*in. Fehlermeldung: site.host doesn't match http_host");
	exit();
}

/** get request uri and match it to the corresponding module, controller and action */
$requestUri = $_SERVER['REQUEST_URI']; // get request uri from server
$requestUri = str_replace("/index.php", "", $requestUri); // remove /index.php from requested uri
$requestUri = explode("?", $requestUri)[0]; // remove get attributes from request uri

$specialmappings = file_get_contents("specialmappings.json"); // read special url mappings from json file
$specialmappings = json_decode($specialmappings, true); // convert read data into php array

$verb = $_SERVER["REQUEST_METHOD"]; // get http verb (normally GET, sometimes POST)

$standardLib = $config->getConfigValue("site.standardlib"); // get standard library used for displaying the site

// loop through special mappings to check if a url is a special url (don't use standard lib)
$use_lib = $standardLib; // holds the used library for displaying the page. if a matching special mapping is found, the standard library is overwritten and the new one is used instead.
foreach ($specialmappings as $mapping) {
	if ($mapping["verb"] == $verb && $mapping["url"] == $requestUri) {
		$use_lib = $mapping["namespace"]; // if the url and the http verb match the one in special mapping, use the namespace given in special mapping
	}
}

// get the class and action name from modules configs/urlmapping.json
$publisher = explode("/", $use_lib)[0]; // get publisher from $use_lib
$module = explode("/", $use_lib)[1]; // get module name of used module from $use_lib
$mappingpath = "lib/" . $publisher . "/" . $module . "/configs/urlmapping.json";

$modmapping = file_get_contents($mappingpath); // get module's own urlmapping.json
$modmapping = json_decode($modmapping, true); // decode json to php object

// loop through modules mapping to find the matching function for the given uri
$classpath = "";
$actionFunction = "";
$classtype = "";
$classname = "";
foreach ($modmapping as $urlmapping) {
	if ($urlmapping["url"] == $requestUri && $urlmapping["verb"] == $verb) {
		// if the url and the http verb match the one in special mapping, use the class and action given in special mapping
		$classpath = explode("#", $urlmapping["name"])[0]; // seperate class from action/function
		$classtype = explode("/", $classpath)[0]; // get the class type (folder) of the class to instanciate
		$classname = explode("/", $classpath)[1]; // get the actual class name (file) of the class to instanciate
		$actionFunction = explode("#", $urlmapping["name"])[1]; // get action/function name
	}
}

lalal dies ist eine zeile

// check if class and function were found, else raise error
if ($classpath == "" or $actionFunction == "") {
	LogHelper::addLogEntry("DEBUG", "HTTP/404: " . $requestUri);
	header("Location: /error.php?header=HTTP 404&msg=Diese Seite wurde leider nicht gefunden.&type=error");
	exit();
}

$clfullname = "\\" . $publisher . "\\" . $module . "\\" . $classtype . "\\" . $classname; // combine the classname whith variables to \publisher\module\type\classname

/** instanciate class and run given function/action */
try {
	$site = new $clfullname(); // instanciate the site's class
	$site->$actionFunction(); // run the given function

} catch (Exception $e) {
	// go to error page if instanciation and / or function didn't run properly
	LogHelper::addLogEntry("CRITICAL", $e);
	header("Location: error.php?header=Ein fataler Fehler ist aufgetreten.&msg=Ein PHP-Fehler ist aufgetreten. Bitte wenden Sie sich mit folgenden Informationen an den/die Administrator*in: Error while trying to instanciate sites class. Given class does not exist.&type=error");
}

/** respond with http 200 if everything worked out */
http_response_code(200);

/** exit after everything is done */
exit();
