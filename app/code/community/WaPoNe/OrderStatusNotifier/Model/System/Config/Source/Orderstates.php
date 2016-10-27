<?php

class WaPoNe_OrderStatusNotifier_Model_System_Config_Source_Orderstates
{
    public function getOrderStatusesOption()
    {
        $statuses = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
        $orderStatuses = array();
        foreach ($statuses as $status)
        {
            $orderStatuses[$status["status"]] = $status["label"];
        }
        return $orderStatuses;
    }

    public function toOptionArray()
    {
        $options = array();
        foreach ($this->getOrderStatusesOption() as $code => $label) {
            $options[] = array(
                'value' => $code,
                'label' => $label
            );
        }

        return $options;
    }
}