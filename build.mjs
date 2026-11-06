/**
 * 配布用の zip（build/turnstile.zip）を作る
 *
 * package.json の version を ServiceProvider.php の $version に反映し、配布に必要なファイルだけを
 * Turnstile/ にまとめて zip にする。git の commit・push はしない（リリースの判断は人が行う）。
 * zip コマンド（macOS・Linux に標準で入っている）を使う。
 */
import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

const PLUGIN_NAME = 'Turnstile';
const FILES = [
  'LICENSE',
  'README.md',
  'ServiceProvider.php',
  'Hook.php',
  'Settings.php',
  'Verifier.php',
  'VerifyResult.php',
  'GET',
  'Modules',
  'assets',
  'template',
];

const { version } = JSON.parse(readFileSync('package.json', 'utf-8'));
const serviceProvider = readFileSync('ServiceProvider.php', 'utf-8');
writeFileSync(
  'ServiceProvider.php',
  serviceProvider.replace(/\$version = '[^']*';/, `$version = '${version}';`)
);

rmSync(PLUGIN_NAME, { recursive: true, force: true });
mkdirSync('build', { recursive: true });
for (const file of FILES) {
  cpSync(file, `${PLUGIN_NAME}/${file}`, { recursive: true });
}
rmSync('build/turnstile.zip', { force: true });
execFileSync('zip', ['-rqX', 'build/turnstile.zip', PLUGIN_NAME, '-x', '*.DS_Store']);
rmSync(PLUGIN_NAME, { recursive: true, force: true });

console.log(`build/turnstile.zip (v${version})`);
