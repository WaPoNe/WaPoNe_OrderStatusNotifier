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

        // Get receiver email addresses (System->Configuration->Order Status Notifier)
        $receivers = explode(";", Mage::getStoreConfig('orderstatusnotifier/orderstatusnotifier_group/receiver_emails'));

        // Loading email template
        $emailTemplate->loadDefault('wapone_order_status_notifier');

        // Email Subject is set in the email template
        // $emailTemplate->setTemplateSubject($email_subject);

        $emailTemplate->setSenderName($salesData['name']);
        $emailTemplate->setSenderEmail($salesData['email']);

        $emailTemplateVariables['order'] = $order;
        $emailTemplateVariables['store'] = Mage::app()->getStore();
        $emailTemplateVariables['order_status'] = $orderStatusLabel;
        $emailTemplateVariables['username']  = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
        $emailTemplateVariables['order_id'] = $order->getIncrementId();
        $emailTemplateVariables['store_name'] = $order->getStoreName();
        $emailTemplateVariables['store_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $emailTemplateVariables['payment_method'] = $order->getPayment()->getMethodInstance()->getTitle();

        $emailTemplate->send($receivers, $order->getStoreName(), $emailTemplateVariables);
    }
}