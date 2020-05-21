<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\FunctionalTests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Symfony\Component\Finder\Finder;

class DatadirTest extends DatadirTestCase
{

    private array $multiConfigTest = [
        'failed-relationship-column',
        'create-tables-with-relationship',
    ];

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);
        $this->replaceCredentials($tempDatadir->getTmpFolder());

        $this->dropDatamodel();

        if (in_array($this->dataName(), $this->multiConfigTest)) {
            $this->prepaireData($tempDatadir->getTmpFolder());
        }

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->dropDatamodel();

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestProvider($this->getTestFileDir()),
        ];
    }

    private function replaceCredentials(string $tempDataDir): void
    {
        $configFile = $tempDataDir . '/config.json';
        $config = json_decode((string) file_get_contents($configFile), true);
        $config['parameters'] = array_merge(
            $config['parameters'],
            [
                'db' => [
                    'host' => getenv('SISENSE_HOST'),
                    'port' => getenv('SISENSE_PORT'),
                    'username' => getenv('SISENSE_USERNAME'),
                    '#password' => getenv('SISENSE_PASSWORD'),
                    'database' => getenv('SISENSE_DATAMODEL'),
                ],
            ]
        );
        file_put_contents($configFile, json_encode($config));
    }

    private function dropDatamodel(): void
    {
        $client = new Client();
        $sisenseUrl = sprintf('%s:%s', getenv('SISENSE_HOST'), getenv('SISENSE_PORT'));

        $login = $client->post(
            sprintf('%s/api/v1/authentication/login', $sisenseUrl),
            [
                'form_params' => [
                    'username' => getenv('SISENSE_USERNAME'),
                    'password' => getenv('SISENSE_PASSWORD'),
                ],
            ]
        );
        $loginJson = json_decode($login->getBody()->getContents(), true);
        $accessToken = $loginJson['access_token'];

        try {
            $datamodels = $client->get(
                sprintf('%s/api/v2/datamodels/schema', $sisenseUrl),
                [
                    'headers' => [
                        'authorization' => 'Bearer ' . $accessToken,
                    ],
                    'query' => [
                        'title' => getenv('SISENSE_DATAMODEL'),
                    ],
                ]
            );

            $datamodelsJson = json_decode($datamodels->getBody()->getContents(), true);
            $client->delete(
                sprintf('%s/api/v2/datamodels/%s', $sisenseUrl, $datamodelsJson['oid']),
                [
                    'headers' => [
                        'authorization' => 'Bearer ' . $accessToken,
                    ],
                ]
            );
        } catch (ClientException $exception) {
        } catch (ServerException $exception) {
        }
    }

    private function prepaireData(string $getTmpFolder): void
    {
        $finder = new Finder();
        $finder->directories()
            ->sortByName()
            ->in($getTmpFolder)
            ->exclude(['in', 'out'])
            ->depth(0)
        ;
        foreach ($finder as $item) {
            $this->replaceCredentials($item->getPathname());
            $this->runScript($item->getPathname());
        }
    }
}
