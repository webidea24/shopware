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

namespace Shopware\Bundle\OrderBundle\Service;

use Shopware\Models\Order\Detail;

class StockService
{
    /**
     * decrease the stock size of the article position
     *
     * @param Detail $detail
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function addArticleDetail(Detail $detail)
    {
        $article = $this->getArticleFromDetail($detail);
        if ($article) {
            $article->setInStock($article->getInStock() - $detail->getQuantity());
            Shopware()->Models()->persist($article);
            Shopware()->Models()->flush();
        }
    }

    /**
     * update the article stock size of the old article position and the new article position
     *
     * @param Detail      $detail
     * @param string|null $oldArticleNumber
     * @param int|null    $oldQuantity
     * @param string|null $newArticleNumber
     * @param int|null    $newQuantity
     */
    public function updateArticleDetail(Detail $detail, $oldArticleNumber = null, $oldQuantity = null, $newArticleNumber = null, $newQuantity = null)
    {
        $oldQuantity = $oldQuantity === 0 || $oldQuantity > 0 ? $oldQuantity : $detail->getQuantity();
        $newQuantity = $newQuantity === 0 || $newQuantity > 0 ? $newQuantity : $detail->getQuantity();

        // If the position article has been changed, the old article stock must be increased based on the (old) ordering quantity.
        // The stock of the new article will be reduced by the (new) ordered quantity.
        if ($newArticleNumber != $oldArticleNumber) {
            //if the old article is a article in the stock, we must increase the stock to the original stock size
            /** @var \Shopware\Models\Article\Detail $oldArticle */
            $oldArticle = $this->getArticleByNumber($oldArticleNumber);
            if ($oldArticle) {
                $oldArticle->setInStock($oldArticle->getInStock() + $oldQuantity);
                Shopware()->Models()->persist($oldArticle);
            }

            //if the new article is a article in the stock, we must decrease the stock size
            /** @var \Shopware\Models\Article\Detail $newArticle */
            $newArticle = $this->getArticleByNumber($newArticleNumber);
            if ($newArticle) {
                $newArticle->setInStock($newArticle->getInStock() - $newQuantity);
                Shopware()->Models()->persist($newArticle);
            }
        } elseif ($oldQuantity != $newQuantity) {
            $article = $this->getArticleFromDetail($detail);
            if (!$article) {
                return;
            }

            //if the article is a article in the stock, we must change the stock size to the new ordered quantity
            $quantityDiff = $oldQuantity - $newQuantity;

            $article->setInStock($article->getInStock() + $quantityDiff);
            Shopware()->Models()->persist($article);
        }
    }

    /**
     * Increase the stock size of the article
     *
     * @param Detail $detail
     */
    public function removeArticleDetail(Detail $detail)
    {
        // Do not increase instock for canceled orders
        if ($detail->getOrder() && $detail->getOrder()->getOrderStatus()->getId() === -1) {
            return;
        }

        $article = $this->getArticleFromDetail($detail);
        if ($article) {
            $article->setInStock($article->getInStock() + $detail->getQuantity());
            Shopware()->Models()->persist($article);
        }
    }

    /**
     * returns the article of the article position
     *
     * @param Detail $detail
     *
     * @return \Shopware\Models\Article\Detail|null
     */
    protected function getArticleFromDetail(Detail $detail)
    {
        return (in_array($detail->getMode(), [0, 1], true) && $detail->getArticleNumber()) ? $detail->getArticleDetail() : null;
    }

    /**
     * returns a article by the ordernumber
     *
     * @param $number
     *
     * @return object|\Shopware\Models\Article\Detail|null
     */
    protected function getArticleByNumber($number)
    {
        $article = Shopware()->Models()->getRepository(\Shopware\Models\Article\Detail::class)->findOneBy(['number' => $number]);

        if ($article instanceof \Shopware\Models\Article\Detail) {
            return $article;
        }

        return null;
    }
}
