import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('temporary legacy rendering preserves storage and yields to modern containers', () => {
  const result = spawnSync('php', ['tests/php-legacy.php'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});
