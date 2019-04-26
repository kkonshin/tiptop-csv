<?php

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('PUBLIC_AJAX_MODE', true);

use \Bitrix\Main\Page\Asset;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

global $APPLICATION;

Asset::getInstance()->addJs('/bitrix/js/main/file_upload_agent.js');

CJSCore::Init(array('fx', 'ajax', 'dd'));

?>
<div>
	<p>Формат файла для загрузки: csv</p>
	<p>
		Перевести в такой формат таблицу из Exel можно с помощью OpenOffice (Файл - сохранить как - Text CSV (.csv) (*.csv),<br>
		кодировка (Character set) Unicode UTF-8,<br>
		разделитель полей (Field delimiter) точка с запятой ; <br>
		разделитель текста (Text delimiter) двойные кавычки "
	</p>
</div>

<?

$APPLICATION->IncludeComponent(
	"bitrix:main.file.input", 
	"csv_upload", 
	array(
		"INPUT_NAME" => "TEST_NAME_INPUT",
		"MULTIPLE" => "N",
		"MODULE_ID" => "main",
		"MAX_FILE_SIZE" => "",
		"ALLOW_UPLOAD" => "F",
		"ALLOW_UPLOAD_EXT" => "csv",
		"INPUT_VALUE" => $_POST["DOPFILE"],
		"COMPONENT_TEMPLATE" => "csv_upload"
	),
	false
);?>

<?require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");?>
