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
use Shopware\Models\Order\Detail;

class ArticleStockSubscriber implements EventSubscriber
{
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
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();
        $entityManager = Shopware()->Models();

        //returns a change set for the model, which contains all changed properties with the old and new value.
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($detail);

        $articleChange = $changeSet['articleNumber'];
        $quantityChange = $changeSet['quantity'];

        //init the articles
        $newArticle = null;
        $oldArticle = null;

        //calculate the difference of the position quantity
        $oldQuantity = empty($quantityChange) ? $detail->getQuantity() : $quantityChange[0];
        $newQuantity = empty($quantityChange) ? $detail->getQuantity() : $quantityChange[1];
        $quantityDiff = $oldQuantity - $newQuantity;

        $repository = $entityManager->getRepository(\Shopware\Models\Article\Detail::class);
        $article = $repository->findOneBy(['number' => $detail->getArticleNumber()]);

        // If the position article has been changed, the old article stock must be increased based on the (old) ordering quantity.
        // The stock of the new article will be reduced by the (new) ordered quantity.
        if (!empty($articleChange)) {
            /*
             * before try to get the article, check if the association field (articleNumber) is not empty,
             * otherwise the find function will throw an exception
             */
            if (!empty($articleChange[0])) {
                /** @var \Shopware\Models\Article\Detail $oldArticle */
                $oldArticle = $repository->findOneBy(['number' => $articleChange[0]]);
            }

            /*
             * before try to get the article, check if the association field (articleNumber) is not empty,
             * otherwise the find function will throw an exception
             */
            if (!empty($articleChange[1])) {
                /** @var \Shopware\Models\Article\Detail $newArticle */
                $newArticle = $repository->findOneBy(['number' => $articleChange[1]]);
            }

            //is the new articleNumber and valid model identifier?
            if ($newArticle instanceof \Shopware\Models\Article\Detail) {
                $newArticle->setInStock($newArticle->getInStock() - $newQuantity);
                $entityManager->persist($newArticle);
            }

            //was the old articleNumber and valid model identifier?
            if ($oldArticle instanceof \Shopware\Models\Article\Detail) {
                $oldArticle->setInStock($oldArticle->getInStock() + $oldQuantity);
                $entityManager->persist($oldArticle);
            }
        } elseif ($article instanceof \Shopware\Models\Article\Detail) {
            $article->setInStock($article->getInStock() + $quantityDiff);
            $entityManager->persist($article);
        }
    }

    public function postPersist(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();

        /*
         * before try to get the article, check if the association field (articleNumber) is not empty
         */
        if (!empty($detail->getArticleNumber())) {
            $entityManager = Shopware()->Models();
            $repository = $entityManager->getRepository(\Shopware\Models\Article\Detail::class);
            $article = $repository->findOneBy(['number' => $detail->getArticleNumber()]);

            if ($article instanceof \Shopware\Models\Article\Detail) {
                $article->setInStock($article->getInStock() - $detail->getQuantity());
                $entityManager->persist($article);
                $entityManager->flush();
            }
        }
    }

    public function preRemove(LifecycleEventArgs $arguments)
    {
        if ($arguments->getObject() instanceof Detail === false) {
            return; //nothing to do
        }
        /** @var Detail $detail */
        $detail = $arguments->getObject();
        $entityManager = Shopware()->Models();

        $repository = $entityManager->getRepository(\Shopware\Models\Article\Detail::class);
        $article = $repository->findOneBy(['number' => $detail->getArticleNumber()]);

        // Do not increase instock for canceled orders
        if ($detail->getOrder() && $detail->getOrder()->getOrderStatus()->getId() === -1) {
            return; //TODO is this the correct location? - i guess it would be better if we use a filter or something like that
        }

        /*
         * before try to get the article, check if the association field (articleNumber) is not empty
         */
        if (!empty($this->articleNumber) && $article instanceof \Shopware\Models\Article\Detail) {
            $article->setInStock($article->getInStock() + $detail->getQuantity());
            $entityManager->persist($article);
        }
    }
}
