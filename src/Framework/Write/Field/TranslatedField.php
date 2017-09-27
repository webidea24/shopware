<?php declare(strict_types=1);
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

namespace Shopware\Framework\Write\Field;

use Shopware\Framework\Write\FieldAware\WriteContextAware;
use Shopware\Framework\Write\WriteContext;

class TranslatedField extends Field implements WriteContextAware
{
    /**
     * @var string
     */
    private $storageName;
    /**
     * @var string
     */
    private $referencedClassName;

    /**
     * @var WriteContext
     */
    private $writeContext;
    /**
     * @var string
     */
    private $foreignClassName;
    /**
     * @var string
     */
    private $foreignFieldName;

    /**
     * @param string $storageName
     */
    public function __construct(string $storageName, string $foreignClassName, string $foreignFieldName)
    {
        $this->foreignClassName = $foreignClassName;
        $this->foreignFieldName = $foreignFieldName;
        $this->storageName = $storageName;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (is_string($value)) {
            // load from write context the default language

            yield 'translations' => [
                $this->writeContext->get($this->foreignClassName, $this->foreignFieldName) => [
                    $key => $value,
                ],
            ];

            return;
        }

        if (is_array($value)) {
            $isNumeric = 0 === count(array_diff($value, range(0, count($value))));

            if ($isNumeric) {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ],
                    ];
                }
            } else {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ],
                    ];
                }
            }

            return;
        }
    }

    /**
     * @return string
     */
    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return string
     */
    public function getReferencedClassName(): string
    {
        return $this->referencedClassName;
    }

    /**
     * {@inheritdoc}
     */
    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }
}