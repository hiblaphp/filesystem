<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Tests\Feature\Helpers\FeatureTestHelper;

beforeEach(function () {
    FeatureTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    FeatureTestHelper::cleanupTestDirectory();
});

describe('File Watching', function () {
    it('detects when a file is modified', function () {
        $path = FeatureTestHelper::getTestPath('watched.txt');
        file_put_contents($path, 'initial content');

        $detected = false;
        $eventType = null;
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event, $changedPath) use (&$detected, &$eventType) {
            $detected = true;
            $eventType = $event;
        });

        $loop->addTimer(1, function () use ($path) {
            file_put_contents($path, 'modified content', FILE_APPEND);
        });

        $loop->addTimer(3, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect($detected)->toBeTrue();
        expect($eventType)->not->toBeNull();
    });

    it('detects multiple modifications', function () {
        $path = FeatureTestHelper::getTestPath('multi_watched.txt');
        file_put_contents($path, 'initial');

        $changes = [];
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event, $changedPath) use (&$changes) {
            $changes[] = [
                'event' => $event,
                'time' => microtime(true),
            ];
        });

        $loop->addTimer(0.5, function () use ($path) {
            file_put_contents($path, "Change 1\n", FILE_APPEND);
        });

        $loop->addTimer(1.5, function () use ($path) {
            file_put_contents($path, "Change 2\n", FILE_APPEND);
        });

        $loop->addTimer(2.5, function () use ($path) {
            file_put_contents($path, "Change 3\n", FILE_APPEND);
        });

        $loop->addTimer(4, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect(count($changes))->toBeGreaterThanOrEqual(3);
    });

    it('can watch multiple files independently', function () {
        $file1 = FeatureTestHelper::getTestPath('file1.txt');
        $file2 = FeatureTestHelper::getTestPath('file2.txt');

        file_put_contents($file1, 'content 1');
        file_put_contents($file2, 'content 2');

        $file1Changes = 0;
        $file2Changes = 0;
        $loop = EventLoop::getInstance();

        $watcher1 = File::watch($file1, function () use (&$file1Changes) {
            $file1Changes++;
        });

        $watcher2 = File::watch($file2, function () use (&$file2Changes) {
            $file2Changes++;
        });

        $loop->addTimer(1, function () use ($file1) {
            file_put_contents($file1, 'updated 1', FILE_APPEND);
        });

        $loop->addTimer(2, function () use ($file2) {
            file_put_contents($file2, 'updated 2', FILE_APPEND);
        });

        $loop->addTimer(4, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcher1);
        File::unwatch($watcher2);

        expect($file1Changes)->toBeGreaterThan(0);
        expect($file2Changes)->toBeGreaterThan(0);
    });

    it('stops detecting changes after unwatching', function () {
        $path = FeatureTestHelper::getTestPath('unwatch_test.txt');
        file_put_contents($path, 'initial');

        $changeCount = 0;
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function () use (&$changeCount) {
            $changeCount++;
        });

        $loop->addTimer(1, function () use ($path) {
            file_put_contents($path, 'change 1', FILE_APPEND);
        });

        $loop->addTimer(2, function () use ($watcherId) {
            File::unwatch($watcherId);
        });

        $loop->addTimer(3, function () use ($path) {
            file_put_contents($path, 'change 2', FILE_APPEND);
        });

        $loop->addTimer(5, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        expect($changeCount)->toBe(1);
    });

    it('provides event details in callback', function () {
        $path = FeatureTestHelper::getTestPath('detailed.txt');
        file_put_contents($path, 'content');

        $capturedEvent = null;
        $capturedPath = null;
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event, $changedPath) use (&$capturedEvent, &$capturedPath) {
            $capturedEvent = $event;
            $capturedPath = $changedPath;
        });

        $loop->addTimer(1, function () use ($path) {
            file_put_contents($path, 'modified', FILE_APPEND);
        });

        $loop->addTimer(3, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect($capturedEvent)->not->toBeNull();
        expect($capturedPath)->toBe($path);
    });

    it('handles rapid successive modifications', function () {
        $path = FeatureTestHelper::getTestPath('rapid.txt');
        file_put_contents($path, 'initial');

        $changes = [];
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event) use (&$changes) {
            $changes[] = microtime(true);
        });

        $modificationCount = 0;
        $timerId = $loop->addPeriodicTimer(0.3, function () use ($path, &$modificationCount) {
            file_put_contents($path, "Rapid change $modificationCount\n", FILE_APPEND);
            $modificationCount++;
        }, 5);

        $loop->addTimer(3, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect(count($changes))->toBeGreaterThanOrEqual(3);
    });

    it('can watch while performing other async operations', function () {
        $path = FeatureTestHelper::getTestPath('multitask.txt');
        file_put_contents($path, 'initial');

        $watcherTriggered = false;
        $timerTriggered = false;
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event) use (&$watcherTriggered) {
            $watcherTriggered = true;
        });

        $loop->addTimer(0.5, function () use (&$timerTriggered) {
            $timerTriggered = true;
        });

        $loop->addTimer(1, function () use ($path) {
            file_put_contents($path, 'modified', FILE_APPEND);
        });

        $loop->addTimer(3, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect($watcherTriggered)->toBeTrue();
        expect($timerTriggered)->toBeTrue();
    });

    it('detects file size changes', function () {
        $path = FeatureTestHelper::getTestPath('size_test.txt');
        file_put_contents($path, 'small');

        $detectedSizes = [];
        $loop = EventLoop::getInstance();

        $watcherId = File::watch($path, function ($event, $changedPath) use (&$detectedSizes) {
            if (file_exists($changedPath)) {
                $detectedSizes[] = filesize($changedPath);
            }
        });

        $loop->addTimer(1, function () use ($path) {
            file_put_contents($path, str_repeat('X', 100));
        });

        $loop->addTimer(2, function () use ($path) {
            file_put_contents($path, str_repeat('Y', 500));
        });

        $loop->addTimer(4, function () use ($loop) {
            $loop->stop();
        });

        $loop->run();

        File::unwatch($watcherId);

        expect(count($detectedSizes))->toBeGreaterThanOrEqual(2);
        expect(max($detectedSizes))->toBeGreaterThan(min($detectedSizes));
    });
});
