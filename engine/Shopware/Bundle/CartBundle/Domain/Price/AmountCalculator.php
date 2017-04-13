<?php
declare(strict_types=1);
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

namespace Shopware\Bundle\CartBundle\Domain\Price;

use Shopware\Bundle\CartBundle\Domain\Tax\CalculatedTaxCollection;
use Shopware\Bundle\CartBundle\Domain\Tax\TaxAmountCalculatorInterface;
use Shopware\Bundle\CartBundle\Domain\Tax\TaxDetector;
use Shopware\Bundle\CartBundle\Domain\Tax\TaxRuleCollection;
use Shopware\Bundle\StoreFrontBundle\Context\ShopContextInterface;

class AmountCalculator
{
    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var TaxAmountCalculatorInterface
     */
    private $taxAmountCalculator;

    public function __construct(
        TaxDetector $taxDetector,
        PriceRounding $rounding,
        TaxAmountCalculatorInterface $taxAmountCalculator
    ) {
        $this->taxDetector = $taxDetector;
        $this->rounding = $rounding;
        $this->taxAmountCalculator = $taxAmountCalculator;
    }

    public function calculateAmount(PriceCollection $prices, ShopContextInterface $context): CartPrice
    {
        if ($this->taxDetector->isNetDelivery($context)) {
            return $this->calculateNetDeliveryAmount($prices);
        }
        if ($this->taxDetector->useGross($context)) {
            return $this->calculateGrossAmount($prices, $context);
        }

        return $this->calculateNetAmount($prices, $context);
    }

    /**
     * Calculates the amount for a new delivery.
     * `Price::price` and `Price::netPrice` are equals and taxes are empty.
     *
     * @param PriceCollection $prices
     *
     * @return CartPrice
     */
    private function calculateNetDeliveryAmount(PriceCollection $prices): CartPrice
    {
        $total = $prices->getTotalPrice();

        return new CartPrice(
            $total->getTotalPrice(),
            $total->getTotalPrice(),
            new CalculatedTaxCollection([]),
            new TaxRuleCollection([])
        );
    }

    /**
     * Calculates the amount for a gross delivery.
     * `Price::netPrice` contains the summed gross prices minus amount of calculated taxes.
     * `Price::price` contains the summed gross prices
     * Calculated taxes are based on the gross prices
     *
     * @param PriceCollection $prices
     *
     * @return CartPrice
     */
    private function calculateGrossAmount(PriceCollection $prices, ShopContextInterface $context): CartPrice
    {
        $total = $prices->getTotalPrice();

        $taxes = $this->taxAmountCalculator->calculate($prices, $context);

        $net = $total->getTotalPrice() - $taxes->getAmount();
        $net = $this->rounding->round($net);

        return new CartPrice($net, $total->getTotalPrice(), $taxes, $total->getTaxRules());
    }

    /**
     * Calculates the amount for a net based delivery, but gross prices has be be payed
     * `Price::netPrice` contains the summed net prices.
     * `Price::price` contains the summed net prices plus amount of calculated taxes
     * Calculated taxes are based on the net prices
     *
     * @param PriceCollection $prices
     *
     * @return CartPrice
     */
    private function calculateNetAmount(PriceCollection $prices, ShopContextInterface $context): CartPrice
    {
        $total = $prices->getTotalPrice();

        $taxes = $this->taxAmountCalculator->calculate($prices, $context);

        $gross = $total->getTotalPrice() + $taxes->getAmount();
        $gross = $this->rounding->round($gross);

        return new CartPrice($total->getTotalPrice(), $gross, $taxes, $total->getTaxRules());
    }
}