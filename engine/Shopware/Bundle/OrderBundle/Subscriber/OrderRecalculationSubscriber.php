<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\OrderBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Shopware\Bundle\OrderBundle\Service\CalculationService;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class OrderRecalculationSubscriber implements EventSubscriber
{
    /** @var CalculationService */
    protected $calculationService;

    public function __construct(
        CalculationService $calculationService
    ) {
        $this->calculationService = $calculationService;
    }

    public function getSubscribedEvents()
    {
        return [
          Events::preUpdate,
          Events::preRemove,
          Events::postPersist,
        ];
    }

    public function preUpdate(LifecycleEventArgs $arguments)
    {
        /* @var $order Order */
        if ($arguments->getObject() instanceof Detail) {
            /** @var Detail $orderDetail */
            $orderDetail = $arguments->getObject();
            $order = $orderDetail->getOrder();
        } else {
            return; //nothing to do
        }

        $entityManager = Shopware()->Models(); // sorry we can't use DI cause the ModelsManager requires all Doctrine subscribers

        //returns a change set for the model, which contains all changed properties with the old and new value.
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($orderDetail);

        $articleChange = (bool) ($changeSet['articleNumber'][0] != $changeSet['articleNumber'][1]);
        $quantityChange = (bool) ($changeSet['quantity'][0] != $changeSet['quantity'][1]);
        $priceChanged = (bool) ($changeSet['price'][0] != $changeSet['price'][1]);
        $taxChanged = (bool) ($changeSet['taxRate'][0] != $changeSet['taxRate'][1]);

        // if anything in the order position has been changed, we must recalculate the totals of the order
        if ($quantityChange || $articleChange || $priceChanged || $taxChanged) {
            $this->calculationService->recalculateOrderTotals($order);
        }
    }

    public function postPersist(LifecycleEventArgs $arguments)
    {
        /* @var $order Order */
        if ($arguments->getObject() instanceof Detail) {
            /** @var Detail $orderDetail */
            $orderDetail = $arguments->getObject();
            $order = $orderDetail->getOrder();
        } else {
            return; //nothing to do
        }

        $this->calculationService->recalculateOrderTotals($order);
    }

    public function preRemove(LifecycleEventArgs $arguments)
    {
        /* @var $order Order */
        if ($arguments->getObject() instanceof Detail) {
            /** @var Detail $orderDetail */
            $orderDetail = $arguments->getObject();
            $order = $orderDetail->getOrder();
        } else {
            return; //nothing to do
        }

        $this->calculationService->recalculateOrderTotals($order);
    }
}
