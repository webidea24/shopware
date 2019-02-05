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
use Shopware\Bundle\OrderBundle\Service\StockService;
use Shopware\Models\Order\Detail;

class ArticleStockSubscriber implements EventSubscriber
{
    protected $stockService;

    /**
     * ArticleStockSubscriber constructor.
     *
     * @param StockService $stockService
     */
    public function __construct(
        StockService $stockService
    ) {
        $this->stockService = $stockService;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preUpdate,
            Events::preRemove,
            Events::postPersist,
        ];
    }

    /**
     * If the position article has been changed, the old article stock must be increased based on the (old) ordering quantity.
     * The stock of the new article will be reduced by the (new) ordered quantity.
     *
     * @param LifecycleEventArgs $arguments
     */
    public function preUpdate(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();

        //returns a change set for the model, which contains all changed properties with the old and new value.
        $changeSet = Shopware()->Models()->getUnitOfWork()->getEntityChangeSet($detail);

        $this->stockService->updateArticleDetail(
            $detail,
            isset($changeSet['articleNumber']) ? $changeSet['articleNumber'][0] : null,
            isset($changeSet['quantity']) ? $changeSet['quantity'][0] : null,
            isset($changeSet['articleNumber']) ? $changeSet['articleNumber'][1] : null,
            isset($changeSet['quantity']) ? $changeSet['quantity'][1] : null
        );
    }

    /**
     * If an position is added, the stock of the article will be reduced by the ordered quantity.
     *
     * @param LifecycleEventArgs $arguments
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function postPersist(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();

        $this->stockService->addArticleDetail($detail);
    }

    /**
     * If the position is deleted, the article stock must be increased based on the ordering quantity.
     *
     * @param LifecycleEventArgs $arguments
     */
    public function preRemove(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();
        $this->stockService->removeArticleDetail($detail);
    }
}
