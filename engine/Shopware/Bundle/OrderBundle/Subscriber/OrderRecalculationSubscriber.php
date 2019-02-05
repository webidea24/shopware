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

    /**
     * OrderRecalculationSubscriber constructor.
     *
     * @param CalculationService $calculationService
     */
    public function __construct(
        CalculationService $calculationService
    ) {
        $this->calculationService = $calculationService;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
          Events::preUpdate,
          Events::preRemove,
          Events::postPersist,
        ];
    }

    /**
     * If a article position get updated, the order totals must be recalculated
     *
     * @param LifecycleEventArgs $arguments
     */
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

        $articleChange = ($changeSet['articleNumber'][0] !== $changeSet['articleNumber'][1]);
        $quantityChange = ($changeSet['quantity'][0] !== $changeSet['quantity'][1]);
        $priceChanged = ($changeSet['price'][0] !== $changeSet['price'][1]);
        $taxChanged = ($changeSet['taxRate'][0] !== $changeSet['taxRate'][1]);

        // if anything in the order position has been changed, we must recalculate the totals of the order
        if ($quantityChange || $articleChange || $priceChanged || $taxChanged) {
            $this->calculationService->recalculateOrderTotals($order);
        }
    }

    /**
     * If a article position got added to the order, the order totals must be recalculated
     *
     * @param LifecycleEventArgs $arguments
     */
    public function postPersist(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }

        /** @var Detail $orderDetail*/
        $orderDetail = $arguments->getObject();
        /** @var Order $order */
        $order = $orderDetail->getOrder();

        $this->calculationService->recalculateOrderTotals($order);
    }

    /**
     * If a article position get removed from the order, the order totals must be recalculated
     *
     * @param LifecycleEventArgs $arguments
     */
    public function preRemove(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }

        /** @var Detail $orderDetail*/
        $orderDetail = $arguments->getObject();
        /** @var Order $order */
        $order = $orderDetail->getOrder();

        $this->calculationService->recalculateOrderTotals($order);
    }
}
