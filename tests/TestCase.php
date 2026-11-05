<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run the Connection.start game-id check against rewritten HTML.
     *
     * The page host is www.{base} and the path is /{game}/. gameSubdomain
     * must return the path segment, not "www", and the channel prefix built
     * from that id must match config.
     */
    protected function assertGameIdentity(string $html, string $game, string $pathname = ''): void
    {
        if ($pathname === '') {
            $pathname = '/'.$game.'/';
        }

        $script = <<<'JS'
const fs = require('fs');
const html = fs.readFileSync(0, 'utf8');
const game = process.argv[1];
const pathname = process.argv[2];
const host = process.argv[3];
const start = html.search(/(?:export\s+)?function\s+gameSubdomain\s*\(\s*hostname\s*\)/);
if (start < 0) {
  console.error('gameSubdomain missing');
  process.exit(3);
}
const open = html.indexOf('{', start);
let depth = 0;
let end = -1;
for (let i = open; i < html.length; i++) {
  if (html[i] === '{') depth++;
  else if (html[i] === '}') {
    depth--;
    if (depth === 0) { end = i; break; }
  }
}
if (end < 0) {
  console.error('gameSubdomain unterminated');
  process.exit(3);
}
let fn = html.slice(start, end + 1).replace(/^export\s+/, '');
globalThis.location = { hostname: host, pathname, protocol: 'https:' };
eval(fn);
const sub = gameSubdomain(host);
const prefix = 'game.' + sub;
const c = {
  game,
  database: { shared: true, isolatedBy: 'subdomain' },
  websocket: {
    channelPrefix: 'game.' + game,
    lobbyChannel: 'game.' + game,
    roomChannel: 'game.' + game + '.{code}',
  },
};
const w = c.websocket;
if (sub === 'www' || c.game !== sub || !c.database || c.database.shared !== true || c.database.isolatedBy !== 'subdomain' || !w || w.channelPrefix !== prefix || w.lobbyChannel !== prefix || w.roomChannel !== prefix + '.{code}') {
  console.error('mismatch ' + JSON.stringify({ sub, prefix, game }));
  process.exit(1);
}
globalThis.location = { hostname: game + '.jadidaring.test', pathname: '/', protocol: 'https:' };
const legacy = gameSubdomain(game + '.jadidaring.test');
if (legacy !== game) {
  console.error('legacy ' + legacy);
  process.exit(4);
}
JS;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            ['node', '-e', $script, $game, $pathname, 'www.jadidaring.test'],
            $descriptors,
            $pipes,
        );

        $this->assertIsResource($process);
        fwrite($pipes[0], $html);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), trim($stdout."\n".$stderr));
    }
}
