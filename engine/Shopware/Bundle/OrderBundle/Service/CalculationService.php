<?php


namespace Shopware\Bundle\OrderBundle\Service;


use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class CalculationService
{

    public function recalculateOrderTotals(Order $order) {
        //$entityManager = Shopware()->Models(); // sorry we can't use DI cause the ModelsManager requires all Doctrine subscribers -> this class is injected by other subscribers

        $invoiceAmount = 0;
        $invoiceAmountNet = 0;

        // Iterate order details to recalculate the amount.
        /** @var Detail $detail */
        foreach ($order->getDetails() as $detail) {
            $price = round($detail->getPrice(), 2);

            $invoiceAmount += $price * $detail->getQuantity();

            $tax = $detail->getTax();

            $taxValue = $detail->getTaxRate();

            // additional tax checks required for sw-2238, sw-2903 and sw-3164
            if ($tax && $tax->getId() !== 0 && $tax->getId() !== null && $tax->getTax() !== null) {
                $taxValue = $tax->getTax();
            }

            if ($order->getNet()) {
                $invoiceAmountNet += round(($price * $detail->getQuantity()) / 100 * (100 + $taxValue), 2);
            } else {
                $invoiceAmountNet += round(($price * $detail->getQuantity()) / (100 + $taxValue) * 100, 2);
            }
        }

        if ($order->getTaxFree()) {
            $order->setInvoiceAmountNet($invoiceAmount + $order->getInvoiceShippingNet());
            $order->setInvoiceAmount($order->getInvoiceAmountNet());
        } elseif ($order->getNet()) {
            $order->setInvoiceAmountNet( $invoiceAmount + $order->getInvoiceShippingNet());
            $order->setInvoiceAmount( $invoiceAmountNet + $order->getInvoiceShipping());
        } else {
            $order->setInvoiceAmount($invoiceAmount + $order->getInvoiceShipping());
            $order->setInvoiceAmountNet( $invoiceAmountNet + $order->getInvoiceShippingNet());
        }
        return $order;
    }
}