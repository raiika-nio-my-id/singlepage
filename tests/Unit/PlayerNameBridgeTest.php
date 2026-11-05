<?php

namespace Tests\Unit;

use App\Services\PlayerNameBridge;
use Tests\TestCase;

class PlayerNameBridgeTest extends TestCase
{
    public function test_it_injects_the_bridge_before_the_rest_of_the_head(): void
    {
        $html = $this->bridge()->inject('<html><head><title>Ular Tangga</title></head><body><!-- jd-namegate --></body></html>');

        $bridgeAt = strpos($html, 'data-jdng-name-bridge');
        $titleAt = strpos($html, '<title>');
        $gateAt = strpos($html, 'jd-namegate');

        $this->assertIsInt($bridgeAt);
        $this->assertIsInt($titleAt);
        $this->assertIsInt($gateAt);
        $this->assertLessThan($titleAt, $bridgeAt);
        $this->assertLessThan($gateAt, $bridgeAt);
        $this->assertStringContainsString('localStorage["jdng.name"]', $html);
        $this->assertStringContainsString("'jdng.name'", $html);
        $this->assertStringNotContainsString('gamemaster', strtolower($html));
    }

    public function test_it_prepends_the_bridge_when_the_document_has_no_head(): void
    {
        $html = $this->bridge()->inject('<html><body><!-- jd-namegate -->Acme</body></html>');

        $this->assertStringStartsWith('<script data-jdng-name-bridge>', $html);
        $this->assertStringContainsString('Acme', $html);
    }

    public function test_it_leaves_pages_that_do_not_ask_for_a_name_unchanged(): void
    {
        $html = '<html><head></head><body>Acme</body></html>';

        $this->assertSame($html, $this->bridge()->inject($html));
    }

    public function test_it_injects_for_the_drawquarium_and_langitbersama_gates(): void
    {
        $bridge = $this->bridge();

        $this->assertStringContainsString('data-jdng-name-bridge', $bridge->inject('<html><head></head><body>sessionStorage.setItem("jdng.drawquarium", n)</body></html>'));
        $this->assertStringContainsString('data-jdng-name-bridge', $bridge->inject('<html><head></head><body>sessionStorage.getItem("jdng.langitbersama")</body></html>'));
    }

    public function test_it_does_not_inject_twice(): void
    {
        $bridge = $this->bridge();
        $once = $bridge->inject('<html><head></head><body><!-- jd-namegate -->Acme</body></html>');
        $twice = $bridge->inject($once);

        $this->assertSame(1, substr_count($twice, 'data-jdng-name-bridge'));
    }

    public function test_shared_name_skips_the_existing_name_gate(): void
    {
        $script = base_path('tests/js/player-name-bridge.test.cjs');
        $process = proc_open(
            ['node', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), trim($stdout."\n".$stderr));
        $this->assertStringContainsString('player-name bridge ok', $stdout);
    }

    private function bridge(): PlayerNameBridge
    {
        return new PlayerNameBridge;
    }
}
