<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\MultipartStream;
use Keboola\Component\UserException;
use Keboola\SiSenseWriter\Api\Model\Datamodel;
use Keboola\SiSenseWriter\Api\Model\Dataset;
use Keboola\SiSenseWriter\Api\Model\Table;
use Keboola\SiSenseWriter\Config;
use Psr\Http\Message\ResponseInterface;

class Api
{
    private Config $config;

    private Client $client;

    private string $accessToken;

    public function __construct(Config $config, Client $client)
    {
        $this->config = $config;
        $this->client = $client;
    }

    public function login(): string
    {
        try {
            $response = $this->client->post(
                sprintf('%s/api/v1/authentication/login', $this->config->getUrlAddress()),
                [
                    'form_params' => [
                        'username' => $this->config->getUsername(),
                        'password' => $this->config->getPassword(),
                    ],
                ]
            );
        } catch (ClientException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (ConnectException $exception) {
            $exceptionMessage = $exception->getMessage();
            if (strstr($exception->getMessage(), 'cURL error 6: Could not resolve host')) {
                $exceptionMessage = sprintf('Could not resolve host "%s"', $this->config->getUrlAddress());
            }
            throw new UserException($exceptionMessage, $exception->getCode(), $exception);
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $responseJson['access_token'];
        return $this->accessToken;
    }

    public function uploadFile(string $dataDir, string $filename): string
    {
        try {
            $streamFile = new MultipartStream(
                [
                    [
                        'name' => 'file',
                        'filename' => $filename,
                        'contents' => file_get_contents($dataDir . $filename),
                    ],
                ]
            );
            $response = $this->client->post(
                sprintf('%s/storage/fs/upload', $this->config->getUrlAddress()),
                [
                    'headers' => $this->getHeaders([
                        'x-upload-token' => $this->validateFile($dataDir, $filename),
                        'Content-Type' => 'multipart/form-data; boundary=' . $streamFile->getBoundary(),
                    ]),
                    'body' => $streamFile,
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return $responseJson[0]['storageInfo']['path'];
    }

    public function getDatamodel(string $datamodelName): ?Datamodel
    {
        try {
            $response = $this->clientGetRequest(
                '/api/v2/datamodels/schema',
                [
                    'title' => $datamodelName,
                ]
            );
        } catch (ServerException $exception) {
            return null;
        }

        return Datamodel::build($response);
    }

    public function createDatamodel(string $datamodelName): Datamodel
    {
        try {
            $response = $this->clientPostRequest(
                '/api/v2/datamodels',
                [
                    'title' => $datamodelName,
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return Datamodel::build($response);
    }

    public function deleteDatamodel(string $datamodelId): void
    {
        try {
            $this->clientDeleteRequest(sprintf('/api/v2/datamodels/%s', $datamodelId));
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function getDataset(string $datamodelId, string $datasetName): ?Dataset
    {
        try {
            $response = $this->clientGetRequest(sprintf('/api/v2/datamodels/%s/schema/datasets', $datamodelId));
        } catch (ServerException $exception) {
            return null;
        }

        $filteredResponse = array_values(array_filter($response, function ($item) use ($datasetName) {
            return $item['name'] === $datasetName;
        }));
        if (count($filteredResponse) === 0) {
            return null;
        }

        return Dataset::build($filteredResponse[0]);
    }

    public function createDataset(
        string $datamodelId,
        string $datasetName,
        string $csvFilePath,
        string $filename
    ): Dataset {
        $connection = [
            'provider' => 'CSV',
            'schema' => $csvFilePath,
            'parameters' => [
                'ApiVersion' => 2,
                'files' => [
                    $csvFilePath,
                ],
                'unionAll' => true,
            ],
            'uiParams' => [],
            'globalTableConfigOptions' => [],
            'fileName' => $filename,
        ];

        try {
            $response = $this->clientPostRequest(
                sprintf('/api/v2/datamodels/%s/schema/datasets', $datamodelId),
                [
                    'name' => $datasetName,
                    'type' => 'extract',
                    'connection' => $connection,
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return Dataset::build($response);
    }

    public function updateDataset(
        string $datamodelId,
        string $datasetId,
        string $csvFilePath,
        string $filename
    ): Dataset {
        $connection = [
            'provider' => 'CSV',
            'schema' => $csvFilePath,
            'parameters' => [
                'ApiVersion' => 2,
                'files' => [
                    $csvFilePath,
                ],
                'unionAll' => true,
            ],
            'uiParams' => [],
            'globalTableConfigOptions' => [],
            'fileName' => $filename,
        ];

        try {
            $response = $this->clientPatchRequest(
                sprintf('/api/v2/datamodels/%s/schema/datasets/%s', $datamodelId, $datasetId),
                [
                    'type' => 'extract',
                    'connection' => $connection,
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return Dataset::build($response);
    }

    public function deleteDataset(string $datamodelId, string $datasetId): void
    {
        try {
            $this->clientDeleteRequest(sprintf(
                '/api/v2/datamodels/%s/schema/datasets/%s',
                $datamodelId,
                $datasetId
            ));
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function getTable(string $datamodelId, string $datasetId, string $tableId): ?Table
    {
        try {
            $response = $this->clientGetRequest(sprintf('/api/v2/datamodels/%s/schema/datasets', $datamodelId));
        } catch (ServerException $exception) {
            return null;
        }

        if (count($response) === 0) {
            return null;
        }
        $filteredDatasets = array_values(array_filter($response, function ($dataset) use ($datasetId) {
            return $dataset['oid'] === $datasetId;
        }));

        $tablesInDataset = $filteredDatasets[0]['schema']['tables'];
        $filteredTables = array_values(array_filter($tablesInDataset, function ($table) use ($tableId) {
            return $table['id'] === $tableId;
        }));

        if (count($filteredTables) === 0) {
            return null;
        }

        return Table::build($filteredTables[0]);
    }

    public function getTableByName(string $datamodelId, string $tableName): array
    {
        try {
            $response = $this->clientGetRequest(
                sprintf('/api/v2/datamodels/%s/schema/datasets', $datamodelId),
                ['fields' => 'oid, name, schema']
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        foreach ($response as $dataset) {
            $filteredTables = array_values(
                array_filter($dataset['schema']['tables'], function ($table) use ($tableName) {
                    return $table['id'] === $tableName;
                })
            );
            if ($filteredTables) {
                return [
                    'dataset' => Dataset::build($dataset),
                    'table' => Table::build($filteredTables[0]),
                ];
            }
        }

        throw new UserException(sprintf('Cannot find table "%s"', $tableName));
    }

    public function createTable(string $datamodelId, string $datasetId, string $tableId, array $columns): Table
    {
        try {
            $response = $this->clientPostRequest(
                sprintf('/api/v2/datamodels/%s/schema/datasets/%s/tables', $datamodelId, $datasetId),
                [
                    'id' => $tableId,
                    'columns' => Helpers::reformatColumns($columns),
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return Table::build($response);
    }

    public function updateTable(string $datamodelId, string $datasetId, string $tableId, array $columns): Table
    {
        try {
            $response = $this->clientPatchRequest(
                sprintf(
                    '/api/v2/datamodels/%s/schema/datasets/%s/tables/%s',
                    $datamodelId,
                    $datasetId,
                    $tableId
                ),
                [
                    'columns' => Helpers::reformatColumns($columns),
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return Table::build($response);
    }

    public function createRelationship(string $datamodelId, array $sourceData, array $targetData): array
    {
        try {
            return $this->clientPostRequest(
                sprintf('/api/v2/datamodels/%s/schema/relations', $datamodelId),
                [
                    'columns' => [
                        $sourceData,
                        $targetData,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function build(string $datamodelId, string $type): string
    {
        $response = $this->clientPostRequest(
            '/api/v2/builds',
            [
                'datamodelId' => $datamodelId,
                'buildType' => $type,
                'rowLimit' => 0,
            ]
        );
        return $response['oid'];
    }

    public function getBuildStatus(string $buildId): ?string
    {
        $response = $this->clientGetRequest(sprintf('/api/v2/builds/%s', $buildId));
        return is_null($response['status']) ? 'waiting' : $response['status'];
    }

    public function getDatasetName(string $datamodelName, string $tableName): string
    {
        return sprintf('%s-%s', $datamodelName, $tableName);
    }

    private function clientGetRequest(string $uri, array $query = [], array $headers = []): array
    {
        $options = [
            'headers' => $this->getHeaders($headers),
        ];
        if ($query) {
            $options['query'] = $query;
        }
        $response = $this->client->get(
            $this->config->getUrlAddress() . $uri,
            $options
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    private function clientPostRequest(string $uri, array $json, array $headers = []): array
    {
        $options = [
            'headers' => $this->getHeaders($headers),
            'json' => $json,
        ];
        $response = $this->client->post(
            $this->config->getUrlAddress() . $uri,
            $options
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    private function clientPatchRequest(string $uri, array $json, array $headers = []): array
    {
        $options = [
            'headers' => $this->getHeaders($headers),
            'json' => $json,
        ];
        $response = $this->client->patch(
            $this->config->getUrlAddress() . $uri,
            $options
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    private function clientDeleteRequest(string $uri): ResponseInterface
    {
        return $this->client->delete(
            $this->config->getUrlAddress() . $uri,
            ['headers' => $this->getHeaders()]
        );
    }

    private function getHeaders(array $additionalHeaders = []): array
    {
        return array_merge(
            ['Authorization' => 'Bearer ' . $this->accessToken],
            $additionalHeaders
        );
    }

    private function validateFile(string $dataDir, string $filename): string
    {
        try {
            $response = $this->client->post(
                sprintf('%s/storage/fs/validate_file', $this->config->getUrlAddress()),
                [
                    'headers' => $this->getHeaders(),
                    'json' => [
                        'filename' => $filename,
                        'size' => filesize($dataDir . $filename),
                    ],
                ]
            );
        } catch (ClientException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
        if ($response->getStatusCode() !== 200) {
            throw new UserException('Something has wrong. Please try again later.');
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return $responseJson['token'];
    }
}
