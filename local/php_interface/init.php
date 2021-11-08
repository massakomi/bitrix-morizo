<?php

$eventManager = \Bitrix\Main\EventManager::getInstance();

// Сохранить в куки значение
if ($_GET['utm_source']) {
	setcookie('utm_source', $_GET['utm_source'], time() + 86400*14, '/');
}


$eventManager->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    function(\Bitrix\Main\Event $event) {
    	if (!$_COOKIE['utm_source']) {
    		return true;
    	}

        $order = $event->getParameter("ENTITY");

        $code = 'UTM_SOURCE';

        $propertyCollection = $order->getPropertyCollection();
        $property = $propertyCollection->getItemByOrderPropertyCode($code);
        if ($property !== null && !$property->getValue()) {
            $property->setField('VALUE', $_COOKIE['utm_source']);
            $order->save();
        }
    }
);
