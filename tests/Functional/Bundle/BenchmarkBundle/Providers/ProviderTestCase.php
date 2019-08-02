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

namespace Shopware\Tests\Functional\Bundle\BenchmarkBundle\Providers;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\BenchmarkBundle\BenchmarkProviderInterface;
use Shopware\Bundle\BenchmarkBundle\Service\StatisticsService;
use Shopware\Bundle\BenchmarkBundle\StatisticsClient;
use Shopware\Bundle\BenchmarkBundle\Struct\StatisticsResponse;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Tests\Functional\Bundle\BenchmarkBundle\BenchmarkTestCase;

abstract class ProviderTestCase extends BenchmarkTestCase
{
    /**
     * @var BenchmarkProviderInterface
     */
    private $provider;

    /**
     * @group BenchmarkBundle
     */
    public function testGetArrayKeysFit()
    {
        $resultData = $this->getBenchmarkData();
        $arrayKeys = array_keys($resultData);

        static::assertCount($this::EXPECTED_KEYS_COUNT, $arrayKeys);
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetValidateTypes()
    {
        $resultData = $this->getBenchmarkData();

        if (!is_array($this::EXPECTED_TYPES)) {
            static::assertInternalType($this::EXPECTED_TYPES, $resultData);

            return;
        }

        $this->checkForTypes($resultData, $this::EXPECTED_TYPES);
    }

    /**
     * @param string $dataName
     */
    protected function installDemoData($dataName)
    {
        $dbalConnection = Shopware()->Container()->get(\Doctrine\DBAL\Connection::class);
        $basicContent = $this->openDemoDataFile('basic_setup');
        $dbalConnection->exec($basicContent);

        parent::installDemoData($dataName);
    }

    /**
     * @return array
     */
    protected function getBenchmarkData()
    {
        return $this->getProvider()->getBenchmarkData(Shopware()->Container()->get(\Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface::class)->createShopContext(1));
    }

    /**
     * @return BenchmarkProviderInterface
     */
    protected function getProvider()
    {
        if ($this->provider === null) {
            $this->provider = Shopware()->Container()->get($this::SERVICE_ID);
        }

        return $this->provider;
    }

    protected function checkForTypes(array $data, array $expectedTypes)
    {
        foreach ($data as $resultKey => $resultItem) {
            if (!$expectedTypes[$resultKey]) {
                continue;
            }

            if (is_array($expectedTypes[$resultKey])) {
                $this->checkForTypes($resultItem, $expectedTypes[$resultKey]);
                continue;
            }

            try {
                static::assertInternalType(
                    $expectedTypes[$resultKey],
                    $resultItem
                );
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                // Print custom error message
                static::fail(sprintf(
                    'Failed asserting that the value for the key %s is of type %s',
                    $resultKey,
                    $expectedTypes[$resultKey]
                ));
            }
        }
    }

    protected function getAssetsFolder()
    {
        return __DIR__ . '/assets/';
    }

    /**
     * @param int $shopId
     *
     * @return ShopContextInterface
     */
    protected function getShopContextByShopId($shopId)
    {
        return Shopware()->Container()->get(\Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface::class)->createShopContext($shopId);
    }

    protected function resetConfig()
    {
        /** @var Connection $dbalConnection */
        $dbalConnection = Shopware()->Container()->get(\Doctrine\DBAL\Connection::class);

        $dbalConnection->update('s_benchmark_config', [
            'last_order_id' => '0',
            'last_customer_id' => '0',
            'last_product_id' => '0',
        ], ['1' => '1']);
    }

    protected function sendStatistics($batchSize = null)
    {
        Shopware()->Models()->clear();
        $response = new StatisticsResponse(new \DateTime('now', new \DateTimeZone('UTC')), 'foo', false);

        $client = $this->createMock(StatisticsClient::class);

        $client
            ->method('sendStatistics')->willReturn($response);

        $service = new StatisticsService(
            Shopware()->Container()->get(\Shopware\Bundle\BenchmarkBundle\BenchmarkCollector::class),
            $client,
            Shopware()->Container()->get(\Shopware\Models\Benchmark\Repository::class),
            Shopware()->Container()->get(\Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface::class),
            Shopware()->Container()->get(\Doctrine\DBAL\Connection::class)
        );

        $config = Shopware()->Container()->get(\Shopware\Models\Benchmark\Repository::class)->findOneBy(['shopId' => 1]);

        $service->transmit($config, $config->getBatchSize());
    }
}
