import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

test('email provider protocol, public resolver, layouts, budgets, sanitisation and placeholders', () => {
  const result = spawnSync('php', ['tests/php-email.php'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});
test('email provider accepts multiple hosts and isolates private readers with public warning fallback',()=>{
  const result=spawnSync('php',['tests/php-email-hosts.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
});
