<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Hibla\Promise\Promise;
use Tests\Integration\Helpers\IntegrationTestHelper;

beforeEach(function () {
    IntegrationTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    IntegrationTestHelper::cleanupTestDirectory();
});

describe('ETL Pipeline', function () {
    it('extracts, transforms, and loads data', function () {
        $source = IntegrationTestHelper::createCsvFile(
            'raw_data.csv',
            ['id', 'name', 'value'],
            [
                [1, 'Alice', 100],
                [2, 'Bob', 200],
                [3, 'Charlie', 300],
            ]
        );

        $rawData = File::read($source)->await();
        
        $lines = explode("\n", $rawData);
        array_shift($lines); 
        
        $transformed = array_map(function ($line) {
            if (empty($line)) {
                return null;
            }
            [$id, $name, $value] = explode(',', $line);
            
            return json_encode([
                'id' => (int) $id,
                'name' => $name,
                'value' => (int) $value * 2,
            ]);
        }, $lines);
        
        $transformed = array_filter($transformed);

        $dest = IntegrationTestHelper::getTestPath('processed_data.json');
        File::write($dest, implode("\n", $transformed))->await();

        $result = file_get_contents($dest);
        expect($result)->toContain('"value":200');
        expect($result)->toContain('"value":400');
    });
});