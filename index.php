<?php

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('PUBLIC_AJAX_MODE', true);

use \Bitrix\Main\Page\Asset;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
//require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

global $APPLICATION;

Asset::getInstance()->addJs('/bitrix/js/main/file_upload_agent.js');

//$APPLICATION->ShowHead();
//CUtil::InitJSCore();
CJSCore::Init(array('fx', 'ajax', 'dd'));

$APPLICATION->IncludeComponent(
	"bitrix:main.file.input",
	"csv_upload",
	array(
		"INPUT_NAME"=>"TEST_NAME_INPUT",
		"MULTIPLE"=>"N",
		"MODULE_ID"=>"main",
		"MAX_FILE_SIZE"=>"",
		"ALLOW_UPLOAD"=>"F",
		"ALLOW_UPLOAD_EXT"=>"csv",
		"INPUT_VALUE" => $_POST["DOPFILE"]
	),
	false
);?>

<?//require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");?>
<?require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");?>
