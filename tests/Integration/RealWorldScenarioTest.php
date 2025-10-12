<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Tests\Integration\Helpers\IntegrationTestHelper;

beforeEach(function () {
    IntegrationTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    IntegrationTestHelper::cleanupTestDirectory();
});

describe('Log File Rotation', function () {
    it('rotates log files', function () {
        $logFile = IntegrationTestHelper::getTestPath('app.log');
        $archive = IntegrationTestHelper::getTestPath('app.log.1');
        
        $logContent = str_repeat("Log entry\n", 1000);
        file_put_contents($logFile, $logContent);

        File::copy($logFile, $archive)
            ->then(fn () => File::write($logFile, ''))
            ->await();

        expect(file_exists($archive))->toBeTrue();
        expect(filesize($logFile))->toBe(0);
        expect(filesize($archive))->toBeGreaterThan(0);
    });
});

describe('Cache System', function () {
    it('implements file-based cache', function () {
        $cacheDir = IntegrationTestHelper::getTestPath('cache');
        mkdir($cacheDir);
        
        $cacheKey = 'user_123';
        $cacheFile = "$cacheDir/$cacheKey.cache";
        $data = json_encode(['name' => 'Alice', 'age' => 30]);

        File::write($cacheFile, $data)->await();

        $cached = File::exists($cacheFile)
            ->then(function ($exists) use ($cacheFile) {
                if ($exists) {
                    return File::read($cacheFile);
                }
                
                return null;
            })
            ->await();

        $decoded = json_decode($cached, true);
        expect($decoded['name'])->toBe('Alice');
    });
});

describe('File-Based Queue', function () {
    it('implements queue with enqueue and dequeue', function () {
        $queueDir = IntegrationTestHelper::getTestPath('queue');
        mkdir($queueDir);
        
        $items = ['task1', 'task2', 'task3', 'task4', 'task5'];
        foreach ($items as $i => $task) {
            File::write("$queueDir/" . time() . "_$i.task", $task)->await();
        }

        $files = scandir($queueDir);
        $files = array_filter($files, fn ($f) => str_ends_with($f, '.task'));
        sort($files);

        $processed = [];
        foreach ($files as $file) {
            $content = File::read("$queueDir/$file")->await();
            $processed[] = $content;
            File::delete("$queueDir/$file")->await();
        }

        expect($processed)->toHaveCount(5);
        expect($processed)->toContain('task1');
        expect(count(scandir($queueDir)))->toBe(2); 
    });
});

describe('Configuration Manager', function () {
    it('manages configuration files', function () {
        $configFile = IntegrationTestHelper::getTestPath('config.json');
        
        $defaultConfig = [
            'app_name' => 'MyApp',
            'debug' => false,
            'database' => ['host' => 'localhost', 'port' => 3306],
        ];
        
        File::write($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT))->await();

        File::read($configFile)
            ->then(fn ($json) => json_decode($json, true))
            ->then(function ($config) use ($configFile) {
                $config['debug'] = true;
                $config['database']['port'] = 5432;
                
                return File::write($configFile, json_encode($config, JSON_PRETTY_PRINT));
            })
            ->await();

        $updatedConfig = json_decode(file_get_contents($configFile), true);
        expect($updatedConfig['debug'])->toBeTrue();
        expect($updatedConfig['database']['port'])->toBe(5432);
    });
});