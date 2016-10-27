<?php
/*
 * WaPoNe :: 27-10-2016
 *
 * Gestione della mail da inviare quando un ordine passa nello stato 'PENDING PAYMENT' o 'CANCELED'
 */

class WaPoNe_OrderStatusNotifier_Model_Observer
{

    public function notify($event)
    {
        $order = $event->getOrder();

        //Order Statuses to notify
        $statuses_to_notify = $this->_getStatuses('orderstatusnotifier/orderstatusnotifier_group/statuses');

        if (in_array($order->getStatus(), $statuses_to_notify)) {
            // Send mail
            $this->_sendEmail($order);
        }
    }

    /* WaPoNe (27-10-2016): Retrieving order statuses selected */
    private function _getStatuses($param)
    {
        $statuses = Mage::getStoreConfig($param);
        $arr_result = array();

        if (!empty($statuses)):
            $arr_result = explode(",", $statuses);
        endif;

        return $arr_result;
    }

    private function _getOrderStatusLabel($orderStatus)
    {
        $orderStatusLabel = "";

        $statuses = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
        foreach ($statuses as $status)
        {
            if($status["status"] == $orderStatus)
                return $status["label"];
        }
        return $orderStatusLabel;
    }

    /* WaPoNe (27-10-2016): Sending email */
    private function _sendEmail($order)
    {
        $orderStatusLabel = $this->_getOrderStatusLabel($order->getStatus());

        $emailTemplate  = Mage::getModel('core/email_template');

        // Get sender email address (System->Configuration->Order Status Notifier)
        $salesData['name'] = Mage::getStoreConfig('orderstatusnotifier/orderstatusnotifier_group/sender_name');
        $salesData['email'] = Mage::getStoreConfig('orderstatusnotifier/orderstatusnotifier_group/sender_email');

        // Creo la lista dei destinatari
        $destinatari = explode(";", Mage::getStoreConfig('orderstatusnotifier/orderstatusnotifier_group/receiver_emails'));

        $emailTemplate->loadDefault('wapone_order_status_notifier');

        // Oggetto della mail
        $email_subject = "Dexhom: Order #". $order->getIncrementId() ."in status ".$orderStatusLabel;

        $emailTemplate->setTemplateSubject($email_subject);

        $emailTemplate->setSenderName($salesData['name']);
        $emailTemplate->setSenderEmail($salesData['email']);

        $emailTemplateVariables['order'] = $order;
        $emailTemplateVariables['store'] = Mage::app()->getStore();
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
        $emailTemplateVariables[''] = $order->getPayment()->getMethodInstance()->getTitle();

        $emailTemplate->send($destinatari, $order->getStoreName(), $emailTemplateVariables);
    }
}