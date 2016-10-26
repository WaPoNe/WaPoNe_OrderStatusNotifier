<?php
/*
 * WaPoNe :: 07-06-2016
 *
 * Gestione della mail da inviare quando un ordine passa nello stato 'PENDING PAYMENT' o 'CANCELED'
 */

class Dexhom_TransactionalEmails_Model_Observer
{
    //private $cdi_IPs = array('194.183.81.238', '95.225.83.222');
    //private $products_viewed_list = array('148', '23403', '80059', '80', '84378');

    public function invoicedStatusChange($event)
    {
        $order = $event->getOrder();

        Mage::log('STATUS:'.$order->getState(), null, "dexhom.log");

        // Se l'ordine passa nello stato 'PENDING PAYMENT' o 'CANCELED'
        if ($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED
            || $order->getState() == Mage_Sales_Model_Order::STATE_NEW)
            // Chiamo la funzione
            $this->_sendStatusMail($order);
    }

    private function _sendStatusMail($order)
    {
        Mage::log('Entrato in _sendStatusMail:'.$order->getState(), null, "dexhom.log");

        $emailTemplate  = Mage::getModel('core/email_template');

        // Get email address (System->Configuration->Transactional Emails)
        $salesData['email'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/email');
        $salesData['name'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/name');
        // Creo la lista dei destinatari
        $destinatari = explode(";", Mage::getStoreConfig('transactionalemails/transactionalemails_group/email_dest_status_change'));

        $store = Mage::app()->getStore();

        $emailTemplate->loadDefault('dexhom_order_status_change');

        $email_subject = "";
        // Oggetto della mail
        if($order->getState() == Mage_Sales_Model_Order::STATE_NEW):
            $email_subject = "Dexhom: Ordine in attesa # ".$order->getIncrementId();
        elseif($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED):
            $email_subject = "Dexhom: Ordine cancellato # ".$order->getIncrementId();
        endif;

        $emailTemplate->setTemplateSubject($email_subject);

        $emailTemplate->setSenderName($salesData['name']);
        $emailTemplate->setSenderEmail($salesData['email']);

        $emailTemplateVariables['order'] = $order;
        $emailTemplateVariables['store'] = $store;
        // Setto 'frase_stato_ordine' che serve a scrivere la frase esatta nella mail
        if($order->getState() == Mage_Sales_Model_Order::STATE_NEW):
            $emailTemplateVariables['frase_stato_ordine'] = "sta effettuando il pagamento.";
        elseif($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED):
            $emailTemplateVariables['frase_stato_ordine'] = "ha cancellato il pagamento.";
        endif;
        $emailTemplateVariables['username']  = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
        $emailTemplateVariables['order_id'] = $order->getIncrementId();
        $emailTemplateVariables['store_name'] = $order->getStoreName();
        $emailTemplateVariables['store_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $emailTemplateVariables['metodo_pagamento'] = $order->getPayment()->getMethodInstance()->getTitle();
        //$emailTemplate->send($order->getCustomerEmail(), $order->getStoreName(), $emailTemplateVariables);

        //Mage::log($order->debug(), NULL, 'custom_email_template.log', true);

        $emailTemplate->send($destinatari, $order->getStoreName(), $emailTemplateVariables);
    }

    public function customerSaveAfter(Varien_Event_Observer $observer)
    {
        // Se la dispatch e' stata gia' gestita, salta il giro (per evitare la doppia chiamata)
        if(Mage::registry('customer_save_after_executed')) {
            return $this;
        }
        // Viene settata la registry 'customer_save_after_executed' per evitare la doppia chiamata
        Mage::register('customer_save_after_executed', true);

        // Se si tratta di REGISTRAZIONE NUOVA UTENZA e non AGGIORNAMENTO UTENZA ESISTENTE
        if (!$observer->getCustomer()->getOrigData()) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $observer->getEvent()->getCustomer();

            $this->_sendNewCustomerMail($customer);
        }
    }

    private function _sendNewCustomerMail($customer)
    {
        $emailTemplate  = Mage::getModel('core/email_template');

        // Get email address (System->Configuration->Transactional Emails)
        $salesData['email'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/email');
        $salesData['name'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/name');
        // Creo la lista dei destinatari
        $destinatari = explode(";", Mage::getStoreConfig('transactionalemails/transactionalemails_group/email_dest_new_customer'));

        $store = Mage::app()->getStore();

        $emailTemplate->loadDefault('dexhom_account_new');

        // Oggetto della mail
        $email_subject = "Dexhom: Nuovo Utente Registrato: ".$customer->getName();
        $emailTemplate->setTemplateSubject($email_subject);

        $emailTemplate->setSenderName($salesData['name']);
        $emailTemplate->setSenderEmail($salesData['email']);

        $emailTemplateVariables['store'] = $store;
        $emailTemplateVariables['customer'] = $customer;

        //Calcolo il numero totale degli utenti
        $emailTemplateVariables['customers_num'] = Mage::getModel('customer/customer')->getCollection()->getSize();

        $emailTemplate->send($destinatari, null, $emailTemplateVariables);
    }

    public function catalogProductView(Varien_Event_Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();

        $this->_sendNewProductViewedMail($product);
    }

    private function _sendNewProductViewedMail($product)
    {
        $customerIP = Mage::helper('core/http')->getRemoteAddr(false);
        $storeId = Mage::app()->getStore()->getId();

        if(!in_array($customerIP, $this->cdi_IPs) && $storeId=='2' && in_array($product->getId(), $this->products_viewed_list))
        {
            $emailTemplate  = Mage::getModel('core/email_template');

            // Get General email address (Admin->Configuration->General->Service Email CDI)
            $salesData['email'] = Mage::getStoreConfig('trans_email/CDI_OrderStatusMail/email');
            $salesData['name'] = Mage::getStoreConfig('trans_email/CDI_OrderStatusMail/name');

            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customer = Mage::getSingleton('customer/session')->getCustomer();
            }
            else {
                $customer = Mage::getSingleton('log/visitor');
            }

            $emailTemplate->loadDefault('cdi_product_viewed');

            $email_subject = "";
            // Oggetto della mail
            $email_subject = "CDI: Nuovo Prodotto Visitato: ".$product->getName();
            $emailTemplate->setTemplateSubject($email_subject);

            $emailTemplate->setSenderName($salesData['name']);
            $emailTemplate->setSenderEmail($salesData['email']);

            $emailTemplateVariables['product'] = $product;
            $emailTemplateVariables['customer'] = $customer;
            $emailTemplateVariables['storeId'] = $storeId;
            $emailTemplateVariables['customerIP'] = $customerIP;

            $emailTemplate->send('it@dexhom.com', null, $emailTemplateVariables);
        }
    }

    public function wishlistProductAdd($observer)
    {
        Mage::log('wishlistProductAdd', null, "dexhom.log");
        $this->_sendWishlistMail($observer);
    }

    private function _sendWishlistMail($observer)
    {
        $wishlist = $observer->getWishlist();
        $customer = Mage::getModel('customer/customer')->load($wishlist->getCustomerid());
        $wishListItemCollection = $wishlist->getItemCollection();

/*        foreach ($wishListItemCollection as $item) {
            Mage::log('ITEM:'.print_r($item->debug(), true), null, "dexhom.log");
            Mage::log('Name:'.$item->getName(), null, "dexhom.log");
            Mage::log('Price:'.$item->getPrice(), null, "dexhom.log");
            Mage::log('Qty:'.$item->getQty(), null, "dexhom.log");
        }*/

        $item = $observer->getItem();
        //Mage::log('$item:'.print_r($item->debug(), true), null, "dexhom.log");
        $lastProd = $item->getProduct();
        //Mage::log('$lastProd:'.print_r($lastProd->debug(), true), null, "dexhom.log");
        $lastProdName = $lastProd->getName();
        //Mage::log('Product Name:'.$lastProdName, null, "dexhom.log");
        $lastProdSku = $lastProd->getSku();
        //Mage::log('Product Sku:'.$lastProdSku, null, "dexhom.log");
        $lastProdQty = $item->getQty();
        //Mage::log('Product Qty:'.$lastProdQty, null, "dexhom.log");

        $emailTemplate  = Mage::getModel('core/email_template');

        // Get email address (System->Configuration->Transactional Emails)
        $salesData['email'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/email');
        $salesData['name'] = Mage::getStoreConfig('transactionalemails/transactionalemails_group/name');
        // Creo la lista dei destinatari
        $destinatari = explode(";", Mage::getStoreConfig('transactionalemails/transactionalemails_group/email_dest_wishlist'));

        $emailTemplate->loadDefault('dexhom_wishlist_product_add');

        // Oggetto della mail
        $email_subject = "Dexhom: Wishlist";

        $emailTemplate->setTemplateSubject($email_subject);

        $emailTemplate->setSenderName($salesData['name']);
        $emailTemplate->setSenderEmail($salesData['email']);

        $emailTemplateVariables['customer'] = $customer;
        $emailTemplateVariables['last_product_name'] = $lastProdName;
        $emailTemplateVariables['last_product_sku'] = $lastProdSku;
        $emailTemplateVariables['last_product_qty'] = $lastProdQty;
        $emailTemplateVariables['wishlist'] = $wishListItemCollection;

        $emailTemplate->send($destinatari, "Dexhom", $emailTemplateVariables);
    }

    public function clearCache($observer)
    {
        Mage::log('Entrato in Observer clearCache()', null, "dexhom.log");
        Mage::app()->cleanCache();
    }
}