<?php

namespace Jobcloud\SchemaConsole\Tests\Command;

use Jobcloud\SchemaConsole\Command\GetSchemaByVersionCommand;
use Jobcloud\SchemaConsole\SchemaRegistryApi;
use Jobcloud\SchemaConsole\Tests\AbstractSchemaRegistryTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GetSchemaByVersionCommandTest extends AbstractSchemaRegistryTestCase
{

    protected const SCHEMA_TEST_FILE = '/tmp/test.avsc';

    public function testCommand():void
    {
        $schema = '{}';

        /** @var MockObject|SchemaRegistryApi $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(SchemaRegistryApi::class, [
            'getSchemaByVersion' => $schema,
        ]);

        $application = new Application();
        $application->add(new GetSchemaByVersionCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:fetch:schema');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'schemaName' => 'SomeSchemaName',
            'schemaVersion' => '1',
            'outputFile' => self::SCHEMA_TEST_FILE,

        ]);

        $fileContents = file_get_contents(self::SCHEMA_TEST_FILE);
        $commandOutput = trim($commandTester->getDisplay());

        self::assertEquals(sprintf('Schema successfully written to %s.', self::SCHEMA_TEST_FILE), $commandOutput);
        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertEquals($schema, $fileContents);
    }

    public function testCommandFailToReadFile():void
    {
        $failurePath = '..';

        /** @var MockObject|SchemaRegistryApi $schemaRegistryApi */
        $schemaRegistryApi = $this->makeMock(SchemaRegistryApi::class, [
            'getSchemaByVersion' => '{}',
        ]);

        $application = new Application();
        $application->add(new GetSchemaByVersionCommand($schemaRegistryApi));
        $command = $application->find('kafka-schema-registry:fetch:schema');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'schemaName' => 'SomeSchemaName',
            'schemaVersion' => '1',
            'outputFile' => $failurePath,

        ]);

        $commandOutput = trim($commandTester->getDisplay());

        self::assertEquals(sprintf('Was unable to write schema to %s.', $failurePath), $commandOutput);
        self::assertEquals(1, $commandTester->getStatusCode());
    }
}