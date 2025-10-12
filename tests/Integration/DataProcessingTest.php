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

describe('Map-Reduce Pattern', function () {
    it('implements map-reduce on file data', function () {
        $files = [];
        
        for ($i = 0; $i < 5; $i++) {
            $path = IntegrationTestHelper::getTestPath("data_$i.txt");
            $numbers = implode("\n", range($i * 10, ($i + 1) * 10 - 1));
            file_put_contents($path, $numbers);
            $files[] = $path;
        }

        $mapPromises = array_map(function ($path) {
            return File::readLines($path, ['skip_empty' => true])
                ->then(function ($generator) {
                    $sum = 0;
                    foreach ($generator as $line) {
                        $sum += (int) $line;
                    }
                    
                    return $sum;
                });
        }, $files);

        $sums = Promise::all($mapPromises)->await();

        $total = array_sum($sums);

        expect($total)->toBe(array_sum(range(0, 49)));
    });
});

describe('File Merging', function () {
    it('merges multiple sorted files', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'sorted1.txt' => "1\n3\n5\n7\n9",
            'sorted2.txt' => "2\n4\n6\n8\n10",
        ]);

        $lines1 = File::readLines($files['sorted1.txt'])->await();
        $lines2 = File::readLines($files['sorted2.txt'])->await();

        $mergedGenerator = function () use ($lines1, $lines2) {
            $arr1 = iterator_to_array($lines1);
            $arr2 = iterator_to_array($lines2);
            $merged = array_merge($arr1, $arr2);
            sort($merged, SORT_NUMERIC);
            
            foreach ($merged as $value) {
                yield $value . "\n";
            }
        };

        $merged = IntegrationTestHelper::getTestPath('merged.txt');
        File::writeFromGenerator($merged, $mergedGenerator())->await();

        $result = file_get_contents($merged);
        expect($result)->toBe("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n");
    });
});