<?php

namespace Jobcloud\SchemaConsole\Tests\Command;

use Buzz\Exception\ClientException;
use Jobcloud\Kafka\SchemaRegistryClient\KafkaSchemaRegistryApiClient;
use Jobcloud\SchemaConsole\Command\GetLatestSchemaCommand;
use Jobcloud\SchemaConsole\Tests\AbstractSchemaRegistryTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Jobcloud\SchemaConsole\Command\GetLatestSchemaCommand
 * @covers \Jobcloud\SchemaConsole\Helper\SchemaFileHelper
 * @covers \Jobcloud\SchemaConsole\Command\AbstractSchemaCommand
 */
class GetLatestSchemaCommandTest extends AbstractSchemaRegistryTestCase
{
    protected const SCHEMA_TEST_FILE = '/tmp/test.avsc';

    public function testCommand(): void
    {
        $schema = ['a' => 'b'];

        /** @var MockObject|KafkaSchemaRegistryApiClient $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(KafkaSchemaRegistryApiClient::class, [
            'getSchemaDefinitionByVersion' => $schema,
        ]);

        $application = new Application();
        $application->add(new GetLatestSchemaCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:get:schema:latest');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'schemaName' => 'SomeSchemaName',
            'outputFile' => self::SCHEMA_TEST_FILE,
        ]);

        $commandOutput = trim($commandTester->getDisplay());

        self::assertEquals(sprintf('Schema successfully written to %s.', self::SCHEMA_TEST_FILE), $commandOutput);
        self::assertEquals(0, $commandTester->getStatusCode());

        $outputFileContents = file_get_contents(self::SCHEMA_TEST_FILE);
        self::assertEquals(json_encode($schema, JSON_THROW_ON_ERROR), $outputFileContents);
    }

    public function testMissingSchema(): void
    {
        $clientException = new ClientException('some message', 404);

        $expectedSchemaName = 'SomeSchemaName';

        /** @var MockObject|KafkaSchemaRegistryApiClient $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(KafkaSchemaRegistryApiClient::class, [
            'getSchemaDefinitionByVersion' => $clientException
        ]);

        $application = new Application();
        $application->add(new GetLatestSchemaCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:get:schema:latest');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'schemaName' => $expectedSchemaName,
            'outputFile' => self::SCHEMA_TEST_FILE,
        ]);

        $commandOutput = trim($commandTester->getDisplay());

        self::assertEquals(sprintf('Schema %s does not exist', $expectedSchemaName), $commandOutput);
        self::assertEquals(1, $commandTester->getStatusCode());
    }

    public function testUnknownClientErrorCodeThrowsException():void
    {
        $clientException = new ClientException('ERROR MESSAGE', 401);

        /** @var MockObject|KafkaSchemaRegistryApiClient $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(KafkaSchemaRegistryApiClient::class, [
            'getSchemaDefinitionByVersion' => $clientException
        ]);

        $application = new Application();
        $application->add(new GetLatestSchemaCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:get:schema:latest');
        $commandTester = new CommandTester($command);

        self::expectException(ClientException::class);
        self::expectExceptionMessage('ERROR MESSAGE');

        $commandTester->execute([
            'schemaName' => 'SomeSchemaName',
            'outputFile' => self::SCHEMA_TEST_FILE,
        ]);
    }

    public function testFailWriteToFile(): void
    {
        $failurePath = '..';

        /** @var MockObject|KafkaSchemaRegistryApiClient $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(KafkaSchemaRegistryApiClient::class, [
            'getSchemaDefinitionByVersion' => [],
        ]);

        $application = new Application();
        $application->add(new GetLatestSchemaCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:get:schema:latest');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'schemaName' => 'SomeSchemaName',
            'outputFile' => $failurePath,
        ]);

        $commandOutput = trim($commandTester->getDisplay());

        self::assertEquals(sprintf('Was unable to write schema to %s.', $failurePath), $commandOutput);
        self::assertEquals(1, $commandTester->getStatusCode());
    }
}
