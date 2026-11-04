#!/usr/bin/env node
// Maintainer tool: minifies the scripts the module serves (assets/js/<name>.js -> <name>.min.js, committed next to the sources).
// The server only ever reads the .min.js files; the sources stay what the tests exercise.
//
//   npm run build           write the minified files
//   npm run build -- --check  fail when a committed file is not what the sources give
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { transform } from 'esbuild';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const SCRIPTS = ['compat', 'engine', 'head'];

export async function minified(name) {
  const source = readFileSync(join(ROOT, `assets/js/${name}.js`), 'utf8');
  const { code } = await transform(source, { minify: true, target: 'es2018', legalComments: 'none', charset: 'utf8' });

  return code;
}

export async function main(argv) {
  const stale = [];
  for (const name of SCRIPTS) {
    const target = join(ROOT, `assets/js/${name}.min.js`);
    const code = await minified(name);
    if (argv.includes('--check')) {
      if (!existsSync(target) || readFileSync(target, 'utf8') !== code) stale.push(`${name}.min.js`);
    } else {
      writeFileSync(target, code);
    }
  }
  if (stale.length) {
    console.error(`Out of date: ${stale.join(', ')}. Run npm run build.`);
    process.exit(1);
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) await main(process.argv.slice(2));
