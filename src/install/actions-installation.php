<?php

/**
 * Used to provide some info to the installation script: localization.
 */
require_once("../global/library.php");

use FormTools\Core;
use FormTools\General;

Core::setHooksEnabled(false);

Core::setCurrLang(General::loadField("lang_file", "lang_file", Core::getDefaultLang()));
$root_url = Core::getRootUrl();

$data = array(
	"error" => "unknown_action"
);

switch ($_GET["action"]) {
	case "init":
		$data = array(
			"is_logged_in" => false,
			"i18n" => Core::$L,
			"constants" => array(
				"root_dir" => Core::getRootDir(),
				"root_url" => "../",
				"core_version" => Core::getCoreVersion()
			)
		);
		break;
}

header("Content-Type: text/javascript");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo json_encode($data);