<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if(!($USER->IsAdmin() || in_array(7, $USER->GetUserGroupArray()))){
    die('Permision denied');
}

$APPLICATION->SetTitle("Связи с поставщиком РОСХОЛОД");
$APPLICATION->AddChainItem("Инструменты", "/tools/");

include_once $_SERVER["DOCUMENT_ROOT"] . "/tools/cron/sphinxapi.php";

if(isset($_POST['action']) && $_POST['action']=='remove') {
    $APPLICATION->RestartBuffer();
    $productRosHolodID=intval($_POST['pid']);
    if($productRosHolodID>0 ) {
        $DB->Query('UPDATE b_pdev_rosholod_items SET PRODUCT_HOLOD_ID=NULL WHERE ID="'.$productRosHolodID.'"');
    }
    exit();
}
if(isset($_POST['action']) && $_POST['action']=='save') {
    $APPLICATION->RestartBuffer();
    $productRosHolodID=intval($_POST['pid']);
    $productHolod54ID=intval($_POST['id']);
    if($productRosHolodID>0 && $productHolod54ID>0) {
        $DB->Query('UPDATE b_pdev_rosholod_items SET PRODUCT_HOLOD_ID="'.$productHolod54ID.'" WHERE ID="'.$productRosHolodID.'"');
    }
    exit();
}
if(isset($_POST['action']) && $_POST['action']=='search') {
    $APPLICATION->RestartBuffer();


    $sphinx = new SphinxClient();
    $sphinx->SetServer("localhost", 9312);
    $sphinx->SetConnectTimeout(3);
    $sphinx->SetArrayResult(true);
    $sphinx->SetLimits(0, 10);
    $sphinx->SetMatchMode(SPH_MATCH_ALL);
    $arSectionID = array();
    $result = $sphinx->Query($_POST['text'], 'bitrix');

    foreach ($result['matches'] as $item) {
        $arSectionID[] = $item['id'];
    }

    $result=array('items'=>array());
    if(!empty($arSectionID)) {
        $dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 29, 'ID' => $arSectionID), false, false, array('ID', 'NAME', 'PROPERTY_CML2_ARTICLE'));
        while ($arRes = $dbRes->GetNext()) {
            unset($arRes['~ID']);
            unset($arRes['~NAME']);
            unset($arRes['~PROPERTY_CML2_ARTICLE_VALUE']);
            $result['items'][] = $arRes;
        }
    }
    echo json_encode($result);
    exit();
}

global $DB;
$arItems=array();
$arProductHolodID=array();
$sql='SELECT I.*, S.ID as STORE_ID, S.XML_ID as STORE_XML_ID, S.NAME as STORE_NAME, Q.ID as Q_ID, Q.XML_ID as PRODUCT_XML_ID, Q.QUANTITY
FROM b_pdev_rosholod_items I
JOIN b_pdev_rosholod_quantity Q ON Q.XML_ID=I.XML_ID
JOIN b_pdev_rosholod_store S ON S.ID=Q.STORE_ID
GROUP BY I.ID
ORDER BY I.ID asc
';
$dbRes=$DB->Query($sql);
while($arRes=$dbRes->GetNext()){
    $arItems[]=$arRes;
    if(intval($arRes['PRODUCT_HOLOD_ID'])>0)
        $arProductHolodID[]=$arRes['PRODUCT_HOLOD_ID'];
}
$arStore=array();
$dbRes=$DB->Query('SELECT S.XML_ID, S.NAME, Q.QUANTITY, Q.XML_ID as PRODUCT_XML_ID FROM b_pdev_rosholod_quantity Q LEFT JOIN b_pdev_rosholod_store S ON S.ID=Q.STORE_ID');
while($arRes=$dbRes->GetNext()){
    $arStore[$arRes['PRODUCT_XML_ID']][$arRes['XML_ID']]=$arRes;
}
$arProductHolod54=array();
if(!empty($arProductHolodID)){
    $dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 29, 'ID' => $arProductHolodID), false, false, array('ID', 'NAME','DETAIL_PAGE_URL', 'PROPERTY_CML2_ARTICLE'));
    while ($arRes = $dbRes->GetNext()) {
        unset($arRes['~ID']);
        unset($arRes['~NAME']);
        unset($arRes['~PROPERTY_CML2_ARTICLE_VALUE']);
        $arProductHolod54[$arRes['ID']] = $arRes;
    }
}
?>
<style>
    .storeListProduct{
        width: 100%;
    }
    .storeListProduct td{
        text-align: center;
    }
    .findNameProductRosHolod{
        width: 350px !important;
    }
    #autocomplitfind{
        position: absolute;
        top: 27px;
        left: 4px;
        list-style-type: none;
        z-index: 100;
        background-color: #FFF;
    }
    #autocomplitfind li{
        padding: 3px 8px;
        border: 1px solid #888;
    }
    .drop{
        width: 25px;
        text-align: center;
        color:red;
    }
    .selectProduct{
        position: relative;
    }
</style>
<h1>Расстановка связей с поставщиком РОСХОЛОД</h1>
<table border="1">
    <tr><th>&nbsp;</th><th>Товар с сайта</th><th>товар росхолода</th><th>склады</th></tr>
<?foreach($arItems as $item):?>
    <?if(intval($item['PRODUCT_HOLOD_ID'])>0 && isset($arProductHolod54[$item['PRODUCT_HOLOD_ID']])) continue;?>
    <tr id="ProductRosHolod_<?=$item['ID']?>">
        <?if(intval($item['PRODUCT_HOLOD_ID'])>0 && isset($arProductHolod54[$item['PRODUCT_HOLOD_ID']])):?>
            <td class="drop"><a href="javascript:void(0);" class="removeLink" data-id="<?=$item['ID']?>">X</a></td>
            <td class="selectProduct"><?=$arProductHolod54[$item['PRODUCT_HOLOD_ID']]['NAME']?> <a href="<?=$arProductHolod54[$item['PRODUCT_HOLOD_ID']]['DETAIL_PAGE_URL']?>" target="_blank">Ссылка</a></td>
        <?else:?>
            <td class="drop">&nbsp;</td>
            <td class="selectProduct">
                <input type="text" name="" data-id="<?=$item['ID']?>" class="findNameProductRosHolod"/><br />
                <?
                $sphinx = new SphinxClient();
                $sphinx->SetServer("localhost", 9312);
                $sphinx->SetConnectTimeout(3);
                $sphinx->SetArrayResult(true);
                $sphinx->SetLimits(0, 5);
                $sphinx->SetMatchMode(SPH_MATCH_ALL);
                $arSectionID = array();
                $result = $sphinx->Query($item['NAME'], 'bitrix');

                foreach ($result['matches'] as $Titem) {
                    $arSectionID[] = $Titem['id'];
                }

                $searchItem=array();
                if(!empty($arSectionID)) {
                    $dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 29, 'ID' => $arSectionID), false, false, array('ID', 'NAME', 'PROPERTY_CML2_ARTICLE'));
                    while ($arRes = $dbRes->GetNext()) {
                        unset($arRes['~ID']);
                        unset($arRes['~NAME']);
                        unset($arRes['~PROPERTY_CML2_ARTICLE_VALUE']);
                        $searchItem[] = $arRes;
                    }
                }
                ?>
                <?if(!empty($searchItem)):?>
                <?foreach($searchItem as $sitem):?>
                    <?=$sitem['PROPERTY_CML2_ARTICLE_VALUE']?> <a href="javascript:void(0)" class="searchProductHolod" data-id="<?=$item['ID']?>" data-newid="<?=$sitem['ID']?>"><?=$sitem['NAME']?></a><br />
                <?endforeach;?>
                <?endif;?>
            </td>
        <?endif;?>
        <td><?=$item['NAME_FULL']?></td>
        <td style="width: 215px;"><?if(isset($arStore[$item['XML_ID']])):?>
                <table border="1" class="storeListProduct">
                <?foreach($arStore[$item['XML_ID']] as $store):?>
                    <tr>
                        <td style="width: 100px;"><?=$store['NAME']?></td>
                        <td style="width: 50px;"><?=$store['QUANTITY']?></td>
                    </tr>
                <?endforeach;?>
                </table>
            <?endif;?>
        </td>
    </tr>
<?endforeach;?>
<?foreach($arItems as $item):?>
    <?if(!(intval($item['PRODUCT_HOLOD_ID'])>0 && isset($arProductHolod54[$item['PRODUCT_HOLOD_ID']]))) continue;?>
    <tr id="ProductRosHolod_<?=$item['ID']?>">
        <?if(intval($item['PRODUCT_HOLOD_ID'])>0 && isset($arProductHolod54[$item['PRODUCT_HOLOD_ID']])):?>
            <td class="drop"><a href="javascript:void(0);" class="removeLink" data-id="<?=$item['ID']?>">X</a></td>
            <td class="selectProduct"><?=$arProductHolod54[$item['PRODUCT_HOLOD_ID']]['PROPERTY_CML2_ARTICLE_VALUE']?> <?=$arProductHolod54[$item['PRODUCT_HOLOD_ID']]['NAME']?> <a href="<?=$arProductHolod54[$item['PRODUCT_HOLOD_ID']]['DETAIL_PAGE_URL']?>" target="_blank">Ссылка</a></td>
        <?else:?>
            <td class="drop">&nbsp;</td>
            <td class="selectProduct">
                <input type="text" name="" data-id="<?=$item['ID']?>" class="findNameProductRosHolod"/><br />
                <?
                $sphinx = new SphinxClient();
                $sphinx->SetServer("localhost", 9312);
                $sphinx->SetConnectTimeout(3);
                $sphinx->SetArrayResult(true);
                $sphinx->SetLimits(0, 5);
                $sphinx->SetMatchMode(SPH_MATCH_ALL);
                $arSectionID = array();
                $result = $sphinx->Query($item['NAME'], 'bitrix');

                foreach ($result['matches'] as $Titem) {
                    $arSectionID[] = $Titem['id'];
                }

                $searchItem=array();
                if(!empty($arSectionID)) {
                    $dbRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 29, 'ID' => $arSectionID), false, false, array('ID', 'NAME'));
                    while ($arRes = $dbRes->GetNext()) {
                        unset($arRes['~ID']);
                        unset($arRes['~NAME']);
                        $searchItem[] = $arRes;
                    }
                }
                ?>
                <?if(!empty($searchItem)):?>
                    <?foreach($searchItem as $sitem):?>
                        <a href="javascript:void(0)" class="searchProductHolod" data-id="<?=$item['ID']?>" data-newid="<?=$sitem['ID']?>"><?=$sitem['NAME']?></a><br />
                    <?endforeach;?>
                <?endif;?>
            </td>
        <?endif;?>
        <td><?=$item['NAME_FULL']?></td>
        <td style="width: 215px;"><?if(isset($arStore[$item['XML_ID']])):?>
                <table border="1" class="storeListProduct">
                    <?foreach($arStore[$item['XML_ID']] as $store):?>
                        <tr>
                            <td style="width: 100px;"><?=$store['NAME']?></td>
                            <td style="width: 50px;"><?=$store['QUANTITY']?></td>
                        </tr>
                    <?endforeach;?>
                </table>
            <?endif;?>
        </td>
    </tr>
<?endforeach;?>
</table>
<script>
    $(document).ready(function(){
        $('body').on('keyup','.findNameProductRosHolod',function(){
            var block=$(this)
            var pid=$(this).data('id');
            $.ajax({
                type: "POST",
                data: 'action=search&text='+$(this).val(),
                success: function(data){
                    ob=JSON.parse(data);
                    if(ob.items) {
                        $('#autocomplitfind').remove();
                        var ul = '<ul id="autocomplitfind" data-id="'+pid+'">';
                        for (i = 0; i <ob.items.length;i++){
                            ul = ul + '<li>'+ob.items[i].PROPERTY_CML2_ARTICLE_VALUE+' <a href="javascript:void(0)" data-id="'+ob.items[i].ID+'">'+ob.items[i].NAME+'</a></li>';
                        }
                            ul = ul + '</ul>';
                        block.after(ul);
                    }
                }
            })
        })
        $('body').on('click','#autocomplitfind li a',function(){
            $('#autocomplitfind').hide();
            var linkBlock=$(this)
            $.ajax({
                type: "POST",
                data: 'action=save&pid='+$('#autocomplitfind').data('id')+'&id='+$(linkBlock).data('id'),
                success: function(data){
                    $('#ProductRosHolod_'+$('#autocomplitfind').data('id')+' .drop').html('<a href="javascript:void(0);" class="removeLink" data-id="'+$(linkBlock).data('id')+'">X</a>');
                    $('#ProductRosHolod_'+$('#autocomplitfind').data('id')+' .selectProduct').html($(linkBlock).text());

                }
            })
        })
        $('body').on('click','.removeLink',function(){
            var linkBlock=$(this)
            $.ajax({
                type: "POST",
                data: 'action=remove&pid='+$(linkBlock).data('id'),
                success: function(data){
                    $('#ProductRosHolod_'+$(linkBlock).data('id')+' .drop').html('');
                    $('#ProductRosHolod_'+$(linkBlock).data('id')+' .selectProduct').html('<input type="text" name="" data-id="'+$(linkBlock).data('id')+'" class="findNameProductRosHolod"/>');
                }
            })
        })
        $('.searchProductHolod').on('click',function(){
            var linkBlock=$(this)
            var id=$(this).data('newid');
            var pid=$(this).data('id');
            $.ajax({
                type: "POST",
                data: 'action=save&pid='+pid+'&id='+id,
                success: function(data){
                    $('#ProductRosHolod_'+pid+' .drop').html('<a href="javascript:void(0);" class="removeLink" data-id="'+pid+'">X</a>');
                    $('#ProductRosHolod_'+pid+' .selectProduct').html($(linkBlock).text());

                }
            })
        })
    })
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
