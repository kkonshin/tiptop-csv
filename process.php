<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use Goutte\Client;

try {

	// Получаем информацию о загруженном файле из main.file.input:csv_upload

	$filePath = '';

	$jsonRequest = file_get_contents('php://input');

	$jsonRequestBody = json_decode($jsonRequest, true);

	// Парсим CSV

	if (is_array($jsonRequestBody) && !empty($jsonRequestBody)){
		$fileRes = CFile::GetByID($jsonRequestBody['element_id']);
		$fileArray = $fileRes->fetch();
		$filePath = CFile::GetPath($jsonRequestBody['element_id']);
	}

	if ((string)$filePath !== ''){
		$inputCsv = Reader::createFromPath($_SERVER["DOCUMENT_ROOT"] . (string)$filePath, "r");
	} else {
		throw new Exception('Неправильный путь к исходному файлу');
	}

	$inputCsv->setDelimiter(';');

	$headers = $inputCsv->fetchOne();

	$res = $inputCsv->addFilter(function ($row, $index) {
		return $index > 0;
	})->fetchAll();

	// KORABLIK

	// Сайт защищен от парсинга, возможна реализация через puppeteer

	$korablikClient = new Client(
		[
			"allow_redirects" => true,
			"curl" => [CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"]
		]
	);

	$korablikCrawler = $korablikClient->request('GET', $res[0][2]);

	$korablikPrice = $korablikCrawler->filter('#current_offer_price')->each(function ($node) {
		return $node->attr('data-price') . PHP_EOL;
	});

	// WILDBERRIES

	$wbClient = new Client();

	if (!empty($res)) {

		foreach ($res as $resKey => $resValue) {

			if (!empty($resValue[3])) {

				$wbCrawler = $wbClient->request('GET', $resValue[3]);

				if ($wbCrawler->getUri() === $resValue[3]) {
					$wbPrice = $wbCrawler->filter('#Price ins')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][] = trim($wbPrice[0]) . PHP_EOL;

				}
			}
		}
	}

	$headers[] = 'Цена Wildberries';
	$headers[] = 'Цена Кораблик';
	$outputCsv = Writer::createFromFileObject(new SplTempFileObject());
	$outputCsv->insertOne($headers);
	$outputCsv->insertAll($res);

	$fileName = "new_price_" . date("Y_m_d_h_i_s") . ".csv";

	ob_start();
	$outputCsvContents = $outputCsv->output($fileName);
	$outputCsvContents = ob_get_contents();
	ob_end_clean();

	// TODO получить нормальный путь к папке с файлом, обеспечить открытие диалога для скачивания файла в script.js

	// TODO разделитель

	if( file_put_contents(__DIR__ . $fileName, $outputCsvContents) ){
		echo json_encode(SITE_DIR . "test/tiptop-css/" . $fileName);
	}

} catch (Exception $e) {
	echo $e->getMessage();
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");?>