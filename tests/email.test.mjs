import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('email provider protocol, public resolver, layouts, budgets, sanitisation and placeholders', () => {
  const result = spawnSync('php', ['tests/php-email.php'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});
