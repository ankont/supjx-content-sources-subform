import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('Sources uses actual shared output, generic/override layouts and once-only presentation for all modes', () => {
  const result = spawnSync('php', ['tests/php-shared-output.php'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});

test('actual Joomla FileLayout honors Sources template overrides in site/admin and inherited site templates', context => {
  const result = spawnSync('php', ['tests/php-item-layout.php'], { encoding: 'utf8' });
  if (result.status === 3) { context.skip('Local Joomla libraries required'); return; }
  assert.equal(result.status, 0, result.stdout + result.stderr);
});
