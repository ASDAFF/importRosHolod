<?
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
/*
CREATE TABLE `b_pdev_rosholod_items` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `NAME_FULL` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ARTICLE` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CODE` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `PRICE` int(11) DEFAULT NULL,
  `XML_ID` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `TIMESTAMP_X` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `PRODUCT_HOLOD_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `xml_id` (`XML_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci

CREATE TABLE `b_pdev_rosholod_store` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `XML_ID` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `xml_id` (`XML_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci

CREATE TABLE `b_pdev_rosholod_quantity` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `XML_ID` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `STORE_ID` int(11) NOT NULL,
  `QUANTITY` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `xml_id` (`XML_ID`),
  KEY `store_id` (`STORE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
 */

$xmlLink='http://rosholod.org/files/XML/Ostatki.xml';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $xmlLink);
curl_setopt($ch, CURLOPT_FAILONERROR, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/34.0.1847.116 Chrome/34.0.1847.116 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
$xml = iconv('windows-1251','utf-8',curl_exec($ch));
curl_close($ch);

$xml=str_replace('<?xml version="1.0" encoding="windows-1251"?>
','',$xml);
$fp=fopen($_SERVER['DOCUMENT_ROOT'].'/tools/cron/rosholod.xml','w+');
fwrite($fp,trim($xml));
fclose($fp);

$xml = simplexml_load_file($_SERVER['DOCUMENT_ROOT'].'/tools/cron/rosholod.xml');
//$xml = simplexml_load_string($xml);
//$xml = simplexml_load_file($xml);
//var_dump($xml);
$mtime=microtime(true);
//Берем полный список складов
$arSoreList=array();
$dbRes=$DB->Query('SELECT * FROM b_pdev_rosholod_store');
while($arRes=$dbRes->GetNext()){
    $arSoreList[$arRes['XML_ID']]=$arRes;
}
if(isset($xml->shop->offers->{'ДетальнаяЗапись'})){
    foreach($xml->shop->offers->{'ДетальнаяЗапись'} as $item){
        $ID=$DB->forSql((string)$item->ID);
        $arFields=array(
            'NAME'=>'"'.$DB->forSql((string)$item->{'Наименование'}).'"',
            'NAME_FULL'=>'"'.$DB->forSql((string)$item->{'НаименованиеПолное'}).'"',
            'ARTICLE'=>'"'.$DB->forSql((string)$item->{'Артикул'}).'"',
            'CODE'=>'"'.$DB->forSql((string)$item->{'Код'}).'"',
            'PRICE'=>'"'.$DB->forSql((string)$item->{'Цена'}).'"',
            'XML_ID'=>'"'.$ID.'"',
            'TIMESTAMP_X'=>'"'.date('Y.m.d H:i:s').'"',
        );
        $dbRes=$DB->Query('SELECT * FROM b_pdev_rosholod_items WHERE XML_ID="'.$ID.'"');
        if($arRes=$dbRes->GetNext()){
            $DB->Update($arRes['ID'],'b_pdev_rosholod_items',$arFields);
        }else {
            $DB->Insert('b_pdev_rosholod_items', $arFields);
        }
        //Берем склады товара
        $arStore=array();
        $dbRes=$DB->Query('SELECT Q.ID, S.XML_ID, Q.QUANTITY FROM b_pdev_rosholod_store S JOIN b_pdev_rosholod_quantity Q ON Q.STORE_ID=S.ID WHERE Q.XML_ID="'.$ID.'"');
        while($arRes=$dbRes->GetNext()){
            $arStore[$arRes['XML_ID']]=$arRes;
        }

        if(isset($item->{Склады})){
            $arStoreXML=array();
            foreach($item->{Склады}->{'Склад'} as $store){
                $storeName=$DB->forSql((string)$store->{'Название'});
                if(strlen($storeName)>0){
                    $storeXML=md5($storeName);
                    $arStoreXML[$storeXML]=1;
                    if(!isset($arSoreList[$storeXML])){
                        //создаем склад
                        $arFields=array(
                            'NAME'=>'"'.$storeName.'"',
                            'XML_ID'=>'"'.$storeXML.'"',
                        );
                        $DB->Insert('b_pdev_rosholod_store', $arFields);
                        $lastID=$DB->LastID();
                        $arSoreList[$storeXML]=array('ID'=>$lastID,'NAME'=>$storeName,'XML_ID'=>$storeXML);
                    }
                    $Quantity=0;
                    $QuantityName=trim((string)$store->{'Остаток'});
                    if($QuantityName=='несколько') $Quantity=3;
                    if($QuantityName=='в наличии') $Quantity=6;
                    if($QuantityName=='много') $Quantity=11;

                    //Если склад есть то обновляем данные
                    if(isset($arStore[$storeXML])){
                        $DB->Update($arStore[$storeXML]['ID'],'b_pdev_rosholod_quantity',array('QUANTITY'=>$Quantity));
                    }else{
                        $DB->Insert('b_pdev_rosholod_quantity',array('XML_ID'=>'"'.$ID.'"','STORE_ID'=>'"'.$arSoreList[$storeXML]['ID'].'"','QUANTITY'=>$Quantity));
                    }
                }
            }
            //удаление количества если нет склада в xml
            foreach($arStore as $itemStore){
                if(!isset($arStoreXML[$itemStore['XML_ID']])){
                    $DB->Query('DELETE FROM b_pdev_rosholod_quantity WHERE ID="'.$itemStore['ID'].'"');
                }
            }
        }

    }
}
echo (microtime(true)-$mtime).'<br />';


//Импорт остатков на новосибирском складе
//в битриксе ID=1496
//В таблице складов ID=4

$sql='SELECT I.*, S.ID as STORE_ID, S.XML_ID as STORE_XML_ID, S.NAME as STORE_NAME, Q.ID as Q_ID, Q.XML_ID as PRODUCT_XML_ID, Q.QUANTITY
FROM b_pdev_rosholod_items I
JOIN b_pdev_rosholod_quantity Q ON Q.XML_ID=I.XML_ID
JOIN b_pdev_rosholod_store S ON S.ID=Q.STORE_ID
WHERE I.PRODUCT_HOLOD_ID>0
ORDER BY I.ID';

$arItems=array();
$dbRes=$DB->Query($sql);
while($arRes=$dbRes->GetNext()) {
    $arItems[$arRes['PRODUCT_HOLOD_ID']][$arRes['STORE_ID']] = $arRes;
}

$dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 29), false, false, array('ID', 'NAME'));
while ($arRes = $dbRes->GetNext()) {
    $flagUpdateQuantity=false;
    $last_quantity=0;
    $dbQuantity = CCatalogProduct::GetList(array(),array("ELEMENT_IBLOCK_ID"=>IBLOCK_PRODUCT,"ID" =>$arRes['ID']),false,array("nTopCount" => 1));
    if($arQuantity = $dbQuantity->GetNext())
    {
        $last_quantity=$arQuantity['QUANTITY'];
    }

    //если существует склад новосибирска то обновляем или добавляем данные в него
    if(isset($arItems[$arRes['ID']][4])) {
        $StoreQuantity=$arItems[$arRes['ID']][4]['QUANTITY'];
        //Проверяем есть ли уже данные об остатках данного товара и склада
        $rsStoreN = CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID' => $arRes['ID'], 'STORE_ID' => 1496), false, false, array('ID', 'AMOUNT'));
        if ($arStoreN = $rsStoreN->Fetch()) {
            //если остаток отличается от текущего то обновляем
            if ($arStoreN['AMOUNT'] != $StoreQuantity) {
                $arFields = Array(
                    "PRODUCT_ID" => $arRes['ID'],
                    "STORE_ID" => 1496,
                    "AMOUNT" => $StoreQuantity,
                );
                CCatalogStoreProduct::Update($arStoreN['ID'], $arFields);
                $flagUpdateQuantity=true;
            }
        } else {
            //Добавляем остаток на склад
            $arFields = Array(
                "PRODUCT_ID" => $arRes['ID'],
                "STORE_ID" => 1496,
                "AMOUNT" => $StoreQuantity,
            );
            CCatalogStoreProduct::Add($arFields);
            $flagUpdateQuantity=true;
        }
    }
    //если склада нет то ищем этот же и если в нем есть данные то обнуляем
    else{
        $rsStoreN = CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID' => $arRes['ID'], 'STORE_ID' => 1496), false, false, array('ID', 'AMOUNT'));
        if ($arStoreN = $rsStoreN->Fetch()) {
            if($arStoreN['AMOUNT']>0) {
                $flagUpdateQuantity=true;
                CCatalogStoreProduct::Update($arStoreN['ID'], array('AMOUNT' => 0));
            }
        }
    }

    //формируем массив складов для свойства
    $arPropsStore=array();
    if(isset($arItems[$arRes['ID']])){
        foreach($arItems[$arRes['ID']] as $storeID=>$storeItem){
            if($storeID!=4) {
                $arPropsStore[] = array('ID' => $storeItem['STORE_ID'], 'NAME' => $storeItem['STORE_NAME'], 'QUANTITY' => $storeItem['QUANTITY']);
            }
        }
    }

    CIBlockElement::SetPropertyValues($arRes['ID'], 29, serialize($arPropsStore), "STORE_ROSHOLOD");

    //Если было изменение количества
    if($flagUpdateQuantity) {
        //Берем все склады товара суммируем количество и обновляем остатки товара
        $tempNewQuantity = 0;
        $rsStoreN = CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID' => $arRes['ID']), false, false, array('ID', 'AMOUNT', 'STORE_ID'));
        while ($arStoreN = $rsStoreN->Fetch()) {
            $tempNewQuantity = $tempNewQuantity + $arStoreN['AMOUNT'];
        }

        if ($tempNewQuantity != $last_quantity) {
            CCatalogProduct::Update($arRes['ID'], array('QUANTITY' => $tempNewQuantity));
        }
        if ($tempNewQuantity <= 0) {
            CIBlockElement::SetPropertyValues($arRes['ID'], 29, 422297, "NALICHIE");
        } else {
            CIBlockElement::SetPropertyValues($arRes['ID'], 29, 422296, "NALICHIE");
        }
        \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(29, $arRes['ID']);
    }
}
echo (microtime(true)-$mtime).'<br />';
?>