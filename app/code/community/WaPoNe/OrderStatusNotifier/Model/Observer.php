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

    public function sendEmail($event)
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

    public function clearCache($observer)
    {
        Mage::log('Entrato in Observer clearCache()', null, "dexhom.log");
        Mage::app()->cleanCache();
    }
}