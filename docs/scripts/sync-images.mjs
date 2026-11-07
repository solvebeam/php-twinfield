// Copies the logos in ../images into the docs, so images/ stays the single source.
//
// images/twinfield/twinfield-icon.svg → src/assets/brand/twinfield/twinfield-icon.svg
// images/twinfield/twinfield-icon.svg → public/favicon.svg

import { cp, copyFile, mkdir, rm } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const docsDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const imagesDir = path.resolve(docsDir, '../images');
const outDir = path.join(docsDir, 'src/assets/brand');

await rm(outDir, { recursive: true, force: true });
await cp(imagesDir, outDir, { recursive: true, filter: (source) => !source.endsWith('.md') });
await mkdir(path.join(docsDir, 'public'), { recursive: true });
await copyFile(path.join(imagesDir, 'twinfield/twinfield-icon.svg'), path.join(docsDir, 'public/favicon.svg'));

console.log('Synced images/ → src/assets/brand/ and public/favicon.svg.');
