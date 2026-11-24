import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('request snapshots, data-only scopes and nested Sources preserve rendering boundaries', () => {
  const result = spawnSync('php', ['tests/php-performance.php'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
  const fixture = JSON.parse(result.stdout);
  assert.equal(fixture.bench[1].prepare_calls, 1);
});
