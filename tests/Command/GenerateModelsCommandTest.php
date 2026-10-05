<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Tests\Command;

use DiabloMedia\Bundle\Doctrine1Bundle\Command\GenerateModelsCommand;
use Doctrine_Import_Exception;
use Doctrine_Record;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateModelsCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/doctrine1_bundle_' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/schema');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public static function modelGenerationProvider(): array
    {
        return [
            'schema directory with default record' => [Doctrine_Record::class, 'schema'],
            'schema file with custom record'       => [GenerateModelsRecord::class, 'schema/widget.yml'],
        ];
    }

    public function testEmptySchemaFailsWithoutReportingSuccess(): void
    {
        $tester = new CommandTester(new GenerateModelsCommand(
            $this->directory . '/schema',
            $this->directory . '/models',
            Doctrine_Record::class
        ));
        $autoloaders = spl_autoload_functions();

        $this->expectException(Doctrine_Import_Exception::class);

        try {
            $tester->execute([]);
        } finally {
            self::assertSame('', $tester->getDisplay());
            self::assertSame($autoloaders, spl_autoload_functions());
        }
    }

    #[DataProvider('modelGenerationProvider')]
    public function testGeneratesModels(string $baseClass, string $schemaPath): void
    {
        file_put_contents($this->directory . '/schema/widget.yml', "GeneratedWidget:\n  columns:\n    name: string(255)\n");

        $command = new GenerateModelsCommand(
            $this->directory . '/' . $schemaPath,
            $this->directory . '/models',
            $baseClass
        );
        $tester      = new CommandTester($command);
        $autoloaders = spl_autoload_functions();

        self::assertSame('doctrine:generate-models', $command->getName());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Generated models successfully from YAML schema', $tester->getDisplay());
        self::assertSame($autoloaders, spl_autoload_functions());
        self::assertFileExists($this->directory . '/models/GeneratedWidget.php');
        self::assertStringContainsString(
            'abstract class BaseGeneratedWidget extends ' . $baseClass,
            file_get_contents($this->directory . '/models/generated/BaseGeneratedWidget.php')
        );

        $customModel = "<?php\n// Application-specific model customizations.\n";
        file_put_contents($this->directory . '/models/GeneratedWidget.php', $customModel);
        file_put_contents($this->directory . '/models/generated/BaseGeneratedWidget.php', '<?php');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame($customModel, file_get_contents($this->directory . '/models/GeneratedWidget.php'));
        self::assertStringContainsString(
            'abstract class BaseGeneratedWidget extends ' . $baseClass,
            file_get_contents($this->directory . '/models/generated/BaseGeneratedWidget.php')
        );
    }
}

class GenerateModelsRecord extends Doctrine_Record
{
}
